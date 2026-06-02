<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use RapportQuest\Gamification\XpManager;
use RapportQuest\Gamification\StreakManager;
use RapportQuest\Gamification\BadgeManager;
use RapportQuest\Gamification\LevelDefinitions;

session_start();

try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    die('Databaseforbindelse fejlede.');
}

$sessionId     = session_id();
$xpManager     = new XpManager($pdo);
$streakManager = new StreakManager($pdo);
$badgeManager  = new BadgeManager($pdo);

$progress   = $xpManager->getProgress($sessionId);
$earned     = $badgeManager->getEarnedBadges($sessionId);
$allBadges  = BadgeManager::getAllDefinitions();
$allLevels  = LevelDefinitions::all();
$curLevel   = LevelDefinitions::get($progress['level']);
$nextLevel  = LevelDefinitions::get($progress['level'] + 1);

$nextXp     = $xpManager->nextLevelThreshold($progress['level']);
$prevXp     = $xpManager->nextLevelThreshold($progress['level'] - 1);
$range      = $nextXp - $prevXp;
$xpInto     = $progress['xp'] - $prevXp;
$xpPct      = $range > 0 ? min(100, (int) round($xpInto / $range * 100)) : 100;

$earnedTypes = array_column($earned, 'type');
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RapportQuest — Gamification</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .gami-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .gami-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
        }
        .gami-card h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 1rem;
        }

        /* Level card */
        .level-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .level-icon {
            font-size: 3rem;
            line-height: 1;
        }
        .level-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }
        .level-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text);
        }
        .xp-progress-wrap {
            margin-top: .75rem;
        }
        .xp-progress-label {
            display: flex;
            justify-content: space-between;
            font-size: .8rem;
            color: var(--text-muted);
            margin-bottom: .3rem;
        }
        .xp-progress-bar {
            background: var(--border);
            border-radius: 999px;
            height: 12px;
            overflow: hidden;
        }
        .xp-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #818cf8);
            border-radius: 999px;
            transition: width .6s ease;
        }

        /* Streak card */
        .streak-number {
            font-size: 3.5rem;
            font-weight: 800;
            color: #f97316;
            line-height: 1;
        }
        .streak-label { color: var(--text-muted); font-size: .9rem; margin-top: .25rem; }
        .streak-flame { font-size: 2rem; }

        /* Levels roadmap */
        .levels-list {
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }
        .level-row {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .5rem .75rem;
            border-radius: 8px;
            background: var(--bg);
            transition: background .15s;
        }
        .level-row.current {
            background: #ede9fe;
            border: 2px solid var(--primary);
        }
        .level-row.unlocked { opacity: 1; }
        .level-row.locked   { opacity: .45; }
        .level-row-icon { font-size: 1.4rem; width: 2rem; text-align: center; }
        .level-row-info { flex: 1; }
        .level-row-name { font-weight: 600; font-size: .9rem; }
        .level-row-xp   { font-size: .75rem; color: var(--text-muted); }
        .level-row-badge {
            font-size: .7rem;
            font-weight: 700;
            padding: .15rem .45rem;
            border-radius: 4px;
            background: var(--primary);
            color: #fff;
        }

        /* Badges */
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: .75rem;
        }
        .badge-item {
            text-align: center;
            padding: 1rem .75rem;
            border-radius: 8px;
            background: var(--bg);
            border: 2px solid transparent;
            transition: border-color .15s;
        }
        .badge-item.earned {
            background: #ede9fe;
            border-color: var(--primary);
        }
        .badge-item.locked { opacity: .4; filter: grayscale(1); }
        .badge-icon   { font-size: 2rem; display: block; margin-bottom: .4rem; }
        .badge-label  { font-size: .8rem; font-weight: 700; }
        .badge-desc   { font-size: .7rem; color: var(--text-muted); margin-top: .2rem; }

        .back-link {
            display: inline-block;
            margin-bottom: 1.5rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <header class="site-header" style="margin-bottom:1rem;">
        <div class="logo">
            <span class="logo-icon">🏆</span>
            <h1>Gamification</h1>
        </div>
        <p class="tagline">Din fremgang og dine præstationer</p>
    </header>

    <a href="index.php" class="back-link">← Tilbage til forsiden</a>

    <div class="gami-grid">
        <!-- Level card -->
        <div class="gami-card">
            <h3>Niveau</h3>
            <div class="level-display">
                <div class="level-icon"><?= $curLevel['icon'] ?></div>
                <div>
                    <div class="level-number">Level <?= $progress['level'] ?></div>
                    <div class="level-title"><?= htmlspecialchars($curLevel['title'], ENT_QUOTES) ?></div>
                </div>
            </div>
            <div class="xp-progress-wrap">
                <div class="xp-progress-label">
                    <span><?= $progress['xp'] ?> XP</span>
                    <span>Næste: <?= $nextXp ?> XP (<?= $nextLevel['title'] ?>)</span>
                </div>
                <div class="xp-progress-bar">
                    <div class="xp-progress-fill" style="width:<?= $xpPct ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Streak card -->
        <div class="gami-card" style="text-align:center;">
            <h3>Streak</h3>
            <div class="streak-flame">🔥</div>
            <div class="streak-number"><?= $progress['streak'] ?></div>
            <div class="streak-label">
                <?php if ($progress['streak'] === 1): ?>
                    dag i træk
                <?php else: ?>
                    dage i træk
                <?php endif; ?>
            </div>
            <?php if ($progress['streak'] >= 7): ?>
            <div style="margin-top:.75rem;font-size:.85rem;color:#f97316;font-weight:600;">
                🔥🔥 Ugekriger! Bliv ved!
            </div>
            <?php elseif ($progress['streak'] >= 3): ?>
            <div style="margin-top:.75rem;font-size:.85rem;color:#f97316;font-weight:600;">
                God streak! <?= 7 - $progress['streak'] ?> dage til Ugekriger
            </div>
            <?php else: ?>
            <div style="margin-top:.75rem;font-size:.85rem;color:var(--text-muted);">
                Vend tilbage i morgen for at forlænge din streak!
            </div>
            <?php endif; ?>
        </div>

        <!-- XP summary -->
        <div class="gami-card">
            <h3>XP Oversigt</h3>
            <table style="width:100%;font-size:.9rem;border-collapse:collapse;">
                <tr>
                    <td style="padding:.4rem 0;color:var(--text-muted);">Total XP</td>
                    <td style="text-align:right;font-weight:700;"><?= $progress['xp'] ?></td>
                </tr>
                <tr>
                    <td style="padding:.4rem 0;color:var(--text-muted);">Nuværende level</td>
                    <td style="text-align:right;font-weight:700;"><?= $progress['level'] ?> — <?= htmlspecialchars($curLevel['title'], ENT_QUOTES) ?></td>
                </tr>
                <tr>
                    <td style="padding:.4rem 0;color:var(--text-muted);">XP til næste level</td>
                    <td style="text-align:right;font-weight:700;"><?= max(0, $nextXp - $progress['xp']) ?></td>
                </tr>
                <tr>
                    <td style="padding:.4rem 0;color:var(--text-muted);">Badges optjent</td>
                    <td style="text-align:right;font-weight:700;"><?= count($earned) ?> / <?= count($allBadges) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Levels roadmap -->
    <div class="gami-card" style="margin-bottom:1.25rem;">
        <h3>Level Oversigt</h3>
        <div class="levels-list">
            <?php foreach ($allLevels as $lvl => $def): ?>
            <?php
                $isCurrent  = $lvl === $progress['level'];
                $isUnlocked = $lvl <= $progress['level'];
                $cls = $isCurrent ? 'current' : ($isUnlocked ? 'unlocked' : 'locked');
            ?>
            <div class="level-row <?= $cls ?>">
                <div class="level-row-icon"><?= $def['icon'] ?></div>
                <div class="level-row-info">
                    <div class="level-row-name">Level <?= $lvl ?> — <?= htmlspecialchars($def['title'], ENT_QUOTES) ?></div>
                    <div class="level-row-xp"><?= $def['xp'] ?> XP krævet</div>
                </div>
                <?php if ($isCurrent): ?>
                    <span class="level-row-badge">DU ER HER</span>
                <?php elseif ($isUnlocked): ?>
                    <span style="color:var(--success);font-size:1.1rem;">✓</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Badges -->
    <div class="gami-card">
        <h3>Badges (<?= count($earned) ?> / <?= count($allBadges) ?>)</h3>
        <div class="badges-grid">
            <?php foreach ($allBadges as $type => $def): ?>
            <?php $isEarned = in_array($type, $earnedTypes, true); ?>
            <div class="badge-item <?= $isEarned ? 'earned' : 'locked' ?>">
                <span class="badge-icon"><?= $def['icon'] ?></span>
                <div class="badge-label"><?= htmlspecialchars($def['label'], ENT_QUOTES) ?></div>
                <div class="badge-desc"><?= htmlspecialchars($def['description'], ENT_QUOTES) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>
