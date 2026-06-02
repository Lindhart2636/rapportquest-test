<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use RapportQuest\Gamification\XpManager;
use RapportQuest\Gamification\BadgeManager;
use RapportQuest\Gamification\LevelDefinitions;
use RapportQuest\Dashboard\ExamReadinessCalculator;

session_start();

try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    die('Databaseforbindelse fejlede.');
}

$sessionId = session_id();
$reportId  = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Load all user reports
$reportStmt = $pdo->prepare(
    'SELECT id, original_name, status, file_size, created_at
     FROM reports ORDER BY created_at DESC LIMIT 10'
);
$reportStmt->execute();
$reports = $reportStmt->fetchAll();

// If no report specified, use latest ready one
if ($reportId === 0) {
    foreach ($reports as $r) {
        if ($r['status'] === 'ready') {
            $reportId = (int) $r['id'];
            break;
        }
    }
}

$xpManager  = new XpManager($pdo);
$badgeManager = new BadgeManager($pdo);
$progress   = $xpManager->getProgress($sessionId);
$curLevel   = LevelDefinitions::get($progress['level']);
$nextLevel  = LevelDefinitions::get($progress['level'] + 1);
$earned     = $badgeManager->getEarnedBadges($sessionId);

$nextXp     = $xpManager->nextLevelThreshold($progress['level']);
$prevXp     = $xpManager->nextLevelThreshold($progress['level'] - 1);
$range      = $nextXp - $prevXp;
$xpInto     = $progress['xp'] - $prevXp;
$xpPct      = $range > 0 ? min(100, (int) round($xpInto / $range * 100)) : 100;

// Exam readiness
$examData = null;
if ($reportId > 0) {
    $calc     = new ExamReadinessCalculator($pdo);
    $examData = $calc->calculate($sessionId, $reportId);
}

// Current report info
$currentReport = null;
if ($reportId > 0) {
    foreach ($reports as $r) {
        if ((int) $r['id'] === $reportId) {
            $currentReport = $r;
            break;
        }
    }
}

function examScoreColor(int $score): string
{
    if ($score >= 80) return '#16a34a';
    if ($score >= 60) return '#2563eb';
    if ($score >= 40) return '#d97706';
    return '#dc2626';
}

function examScoreLabel(int $score): string
{
    if ($score >= 80) return 'Klar til eksamen! 🎓';
    if ($score >= 60) return 'Godt på vej ✅';
    if ($score >= 40) return 'Fortsæt øvelsen 📝';
    return 'Mere øvelse kræves 💪';
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RapportQuest — Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .dash-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
        }
        .dash-card h3 {
            font-size: .8rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: .75rem;
        }

        /* Exam readiness score */
        .exam-score-wrap {
            text-align: center;
            padding: 1rem 0;
        }
        .exam-score-circle {
            width: 140px;
            height: 140px;
            margin: 0 auto 1rem;
            position: relative;
        }
        .exam-score-circle svg { transform: rotate(-90deg); }
        .exam-score-text {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .exam-score-number {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
        }
        .exam-score-pct { font-size: .85rem; color: var(--text-muted); }
        .exam-score-label {
            font-size: 1rem;
            font-weight: 600;
            margin-top: .25rem;
        }

        /* Breakdown table */
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .875rem;
        }
        .breakdown-table th {
            text-align: left;
            padding: .4rem .5rem;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid var(--border);
        }
        .breakdown-table td {
            padding: .5rem .5rem;
            border-bottom: 1px solid var(--border);
        }
        .breakdown-bar-wrap {
            background: var(--border);
            border-radius: 999px;
            height: 7px;
            width: 80px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
        }
        .breakdown-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: var(--primary);
        }

        /* Stats row */
        .stat-row {
            display: flex;
            align-items: baseline;
            gap: .5rem;
            margin-bottom: .6rem;
        }
        .stat-big {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }
        .stat-unit { font-size: .875rem; color: var(--text-muted); }

        /* XP bar */
        .xp-bar-wrap { background: var(--border); border-radius: 999px; height: 10px; overflow: hidden; margin: .4rem 0; }
        .xp-bar-fill { height: 100%; background: linear-gradient(90deg, var(--primary), #818cf8); border-radius: 999px; }

        /* Report switcher */
        .report-select-wrap {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            flex-wrap: wrap;
        }
        .report-select {
            flex: 1;
            min-width: 200px;
            padding: .5rem .75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: .9rem;
            font-family: inherit;
            background: var(--surface);
        }

        /* Recent badges */
        .badges-row {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .badge-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem .65rem;
            background: #ede9fe;
            border-radius: 999px;
            font-size: .8rem;
            font-weight: 600;
            color: var(--primary);
        }

        /* Activity modes */
        .modes-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: .75rem;
            margin-top: 1.25rem;
        }
        .mode-btn {
            display: block;
            text-align: center;
            padding: 1rem .5rem;
            background: var(--bg);
            border-radius: 8px;
            border: 2px solid var(--border);
            text-decoration: none;
            color: var(--text);
            font-weight: 600;
            font-size: .85rem;
            transition: border-color .15s, background .15s;
        }
        .mode-btn:hover { border-color: var(--primary); background: #f5f3ff; }
        .mode-btn-icon { font-size: 1.6rem; display: block; margin-bottom: .3rem; }

        .no-report-msg {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="container">
    <header class="site-header" style="margin-bottom:1.25rem;">
        <div class="logo">
            <span class="logo-icon">📊</span>
            <h1>Dashboard</h1>
        </div>
        <p class="tagline">Din eksamensforberedelse på ét blik</p>
    </header>

    <!-- Report switcher -->
    <?php if (!empty($reports)): ?>
    <div class="report-select-wrap">
        <label for="report-select" style="font-weight:600;font-size:.9rem;">Rapport:</label>
        <select id="report-select" class="report-select" onchange="switchReport(this.value)">
            <?php foreach ($reports as $r): ?>
            <option value="<?= $r['id'] ?>" <?= (int) $r['id'] === $reportId ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['original_name'], ENT_QUOTES) ?>
                (<?= $r['status'] === 'ready' ? '✓' : $r['status'] ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <a href="index.php" style="color:var(--primary);font-size:.875rem;font-weight:600;text-decoration:none;">+ Ny rapport</a>
    </div>
    <?php endif; ?>

    <?php if ($reportId === 0 || !$currentReport): ?>
    <div class="dash-card no-report-msg">
        <p style="font-size:2rem;margin-bottom:.75rem;">📄</p>
        <p>Upload en rapport for at se dit dashboard.</p>
        <a href="index.php" class="btn-submit" style="display:inline-block;margin-top:1rem;padding:.65rem 1.5rem;text-decoration:none;width:auto;">Upload rapport</a>
    </div>
    <?php else: ?>

    <div class="dashboard-grid">

        <!-- Eksamen Klar score -->
        <div class="dash-card">
            <h3>Eksamen Klar-score</h3>
            <?php if ($examData): ?>
            <?php $scoreColor = examScoreColor($examData['score']); ?>
            <div class="exam-score-wrap">
                <div class="exam-score-circle">
                    <svg width="140" height="140" viewBox="0 0 140 140">
                        <circle cx="70" cy="70" r="58" fill="none" stroke="var(--border)" stroke-width="12"/>
                        <circle cx="70" cy="70" r="58" fill="none"
                            stroke="<?= $scoreColor ?>" stroke-width="12"
                            stroke-dasharray="<?= round(2 * M_PI * 58) ?>"
                            stroke-dashoffset="<?= round(2 * M_PI * 58 * (1 - $examData['score'] / 100)) ?>"
                            stroke-linecap="round"/>
                    </svg>
                    <div class="exam-score-text">
                        <span class="exam-score-number" style="color:<?= $scoreColor ?>"><?= $examData['score'] ?></span>
                        <span class="exam-score-pct">/ 100</span>
                    </div>
                </div>
                <div class="exam-score-label" style="color:<?= $scoreColor ?>">
                    <?= examScoreLabel($examData['score']) ?>
                </div>
            </div>
            <?php else: ?>
            <p style="color:var(--text-muted);text-align:center;padding:1rem 0;">Gennemfør aktiviteter for at se din score.</p>
            <?php endif; ?>
        </div>

        <!-- XP & Level -->
        <div class="dash-card">
            <h3>XP & Niveau</h3>
            <div class="stat-row">
                <span class="stat-big"><?= $curLevel['icon'] ?> <?= $progress['level'] ?></span>
                <span class="stat-unit"><?= htmlspecialchars($curLevel['title'], ENT_QUOTES) ?></span>
            </div>
            <div class="xp-bar-wrap">
                <div class="xp-bar-fill" style="width:<?= $xpPct ?>%"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:.8rem;color:var(--text-muted);margin-bottom:1rem;">
                <span><?= $progress['xp'] ?> XP</span>
                <span>Næste: <?= $nextXp ?> XP</span>
            </div>
            <div class="stat-row">
                <span class="stat-big" style="color:#f97316;">🔥 <?= $progress['streak'] ?></span>
                <span class="stat-unit">dages streak</span>
            </div>
        </div>

        <!-- Score breakdown -->
        <?php if ($examData): ?>
        <div class="dash-card">
            <h3>Score Fordeling</h3>
            <table class="breakdown-table">
                <thead>
                    <tr>
                        <th>Aktivitet</th>
                        <th>Score</th>
                        <th>Vægt</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($examData['breakdown'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['label'], ENT_QUOTES) ?></td>
                        <td><strong><?= $row['score'] ?>%</strong></td>
                        <td style="color:var(--text-muted)"><?= $row['weight'] ?></td>
                        <td>
                            <div class="breakdown-bar-wrap">
                                <div class="breakdown-bar-fill" style="width:<?= $row['score'] ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <details style="margin-top:1rem;">
                <summary style="cursor:pointer;font-size:.8rem;color:var(--primary);font-weight:600;">Vis beregning</summary>
                <div style="margin-top:.75rem;font-size:.8rem;color:var(--text-muted);line-height:1.8;">
                    <?php foreach ($examData['breakdown'] as $row): ?>
                    <?= htmlspecialchars($row['label'], ENT_QUOTES) ?>: <?= $row['score'] ?> × <?= $row['weight'] ?> = <strong><?= $row['weighted'] ?> point</strong> (<?= $row['detail'] ?>)<br>
                    <?php endforeach; ?>
                    <strong>Total: <?= $examData['score'] ?> / 100</strong>
                </div>
            </details>
        </div>
        <?php endif; ?>

        <!-- Badges -->
        <div class="dash-card">
            <h3>Badges (<?= count($earned) ?>)</h3>
            <?php if (!empty($earned)): ?>
            <div class="badges-row">
                <?php foreach ($earned as $badge): ?>
                <div class="badge-chip" title="<?= htmlspecialchars($badge['description'], ENT_QUOTES) ?>">
                    <span><?= $badge['icon'] ?></span>
                    <span><?= htmlspecialchars($badge['label'], ENT_QUOTES) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:var(--text-muted);font-size:.9rem;">Gennemfør aktiviteter for at optjene badges.</p>
            <?php endif; ?>
            <a href="gamification.php" style="display:inline-block;margin-top:1rem;font-size:.875rem;color:var(--primary);font-weight:600;text-decoration:none;">
                Se alle badges →
            </a>
        </div>

        <!-- Activity stats -->
        <?php if ($examData): ?>
        <div class="dash-card">
            <h3>Aktivitetsstatistik</h3>
            <?php $s = $examData['stats']; ?>
            <table style="width:100%;font-size:.875rem;border-collapse:collapse;">
                <tr>
                    <td style="padding:.35rem 0;color:var(--text-muted);">Total XP</td>
                    <td style="text-align:right;font-weight:700;"><?= $s['total_xp'] ?></td>
                </tr>
                <tr>
                    <td style="padding:.35rem 0;color:var(--text-muted);">Quiz-spørgsmål genereret</td>
                    <td style="text-align:right;font-weight:700;"><?= $s['quiz_count'] ?></td>
                </tr>
                <tr>
                    <td style="padding:.35rem 0;color:var(--text-muted);">Cloze-opgaver genereret</td>
                    <td style="text-align:right;font-weight:700;"><?= $s['cloze_count'] ?></td>
                </tr>
                <tr>
                    <td style="padding:.35rem 0;color:var(--text-muted);">Boss Battle-spørgsmål</td>
                    <td style="text-align:right;font-weight:700;"><?= $s['boss_count'] ?></td>
                </tr>
                <tr>
                    <td style="padding:.35rem 0;color:var(--text-muted);">Streak</td>
                    <td style="text-align:right;font-weight:700;"><?= $s['streak_days'] ?> dage</td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

    </div>

    <!-- Activity buttons -->
    <div class="dash-card" style="margin-bottom:1.5rem;">
        <h3>Start en aktivitet</h3>
        <div class="modes-grid">
            <a href="quiz.php?id=<?= $reportId ?>" class="mode-btn">
                <span class="mode-btn-icon">🎯</span>Quiz
            </a>
            <a href="cloze.php?id=<?= $reportId ?>" class="mode-btn">
                <span class="mode-btn-icon">✏️</span>Cloze
            </a>
            <a href="boss.php?id=<?= $reportId ?>" class="mode-btn">
                <span class="mode-btn-icon">⚔️</span>Boss Battle
            </a>
        </div>
    </div>

    <?php endif; ?>
</div>

<script>
function switchReport(id) {
    window.location.href = 'dashboard.php?id=' + id;
}
</script>
</body>
</html>
