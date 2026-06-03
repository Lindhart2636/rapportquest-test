<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use ExamQuest\Gamification\XpManager;
use ExamQuest\Gamification\BadgeManager;
use ExamQuest\Gamification\LevelDefinitions;
use ExamQuest\Gamification\StreakManager;

session_start();

try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    die('Databaseforbindelse fejlede.');
}

$sessionId    = session_id();
$xpManager    = new XpManager($pdo);
$badgeManager = new BadgeManager($pdo);
$streakManager = new StreakManager($pdo);

// Avatar slugs → file map (Avatar 1-10 only)
$AVATAR_BASE = 'https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/';
$avatars = [];
for ($i = 1; $i <= 10; $i++) {
    $avatars['avatar-' . $i] = [
        'file'  => 'Avatar%20' . $i . '.png',
        'label' => 'Avatar ' . $i,
    ];
}

// Handle avatar selection AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['avatar'])) {
    $key = $_POST['avatar'];
    if (array_key_exists($key, $avatars)) {
        $stmt = $pdo->prepare(
            'INSERT INTO progress (session_id, avatar) VALUES (:sid, :av)
             ON DUPLICATE KEY UPDATE avatar = :av2'
        );
        $stmt->execute([':sid' => $sessionId, ':av' => $key, ':av2' => $key]);
        $_SESSION['avatar'] = $key;
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// Handle daily login bonus claim
$bonusClaimed  = false;
$bonusAvailable = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_bonus'])) {
    $lastBonus = $_SESSION['last_bonus'] ?? null;
    $today     = date('Y-m-d');
    if ($lastBonus !== $today) {
        $xpManager->addXp($sessionId, 50);
        $_SESSION['last_bonus'] = $today;
        $bonusClaimed = true;
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'xp' => 50]);
    exit;
}

$progress      = $xpManager->getProgress($sessionId);
$earned        = $badgeManager->getEarnedBadges($sessionId);
$allBadges     = BadgeManager::getAllDefinitions();
$allLevels     = LevelDefinitions::all();
$curLevel      = LevelDefinitions::get($progress['level']);
$nextLevel     = LevelDefinitions::get($progress['level'] + 1);

$nextXp  = $xpManager->nextLevelThreshold($progress['level']);
$prevXp  = $xpManager->nextLevelThreshold($progress['level'] - 1);
$range   = $nextXp - $prevXp;
$xpInto  = $progress['xp'] - $prevXp;
$xpPct   = $range > 0 ? min(100, (int) round($xpInto / $range * 100)) : 100;

$earnedTypes    = array_column($earned, 'type');
$currentAvatar  = $progress['avatar'] ?? $_SESSION['avatar'] ?? '';

// Report stats (reports table has no session_id — count all visible reports)
$statsStmt   = $pdo->query('SELECT COUNT(*) as cnt FROM reports');
$reportCount = (int)($statsStmt->fetch()['cnt'] ?? 0);

// Recent reports
$recentStmt    = $pdo->query('SELECT original_name, created_at FROM reports ORDER BY created_at DESC LIMIT 5');
$recentReports = $recentStmt->fetchAll();

// Daily bonus
$today          = date('Y-m-d');
$bonusAvailable = ($_SESSION['last_bonus'] ?? '') !== $today;

// New avatar unlock: last avatar in earned badges or just always show avatar-5 as "new"
$newAvatarKey = 'avatar-5';

$reportId = 0;
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExamQuest — Profil</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .profile-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }
        .profile-bottom {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.25rem;
        }
        @media (max-width: 1100px) {
            .profile-layout { grid-template-columns: 1fr 1fr; }
            .profile-bottom { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 700px) {
            .profile-layout, .profile-bottom { grid-template-columns: 1fr; }
        }

        /* Cards */
        .p-card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 1.25rem;
            box-shadow: var(--shadow);
        }
        .p-card h3 {
            font-size: .7rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        /* Profile card */
        .profile-avatar-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .75rem;
        }
        .profile-avatar {
            width: 100%;
            max-width: 200px;
            aspect-ratio: 1;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            box-shadow: 0 0 20px rgba(124,58,237,.55);
        }
        .avatar-placeholder {
            width: 100%;
            max-width: 200px;
            aspect-ratio: 1;
            border-radius: 50%;
            background: var(--bg);
            border: 3px dashed var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem;
        }
        .level-pill {
            display: inline-flex; align-items: center; gap: .4rem;
            background: linear-gradient(135deg, var(--primary), var(--neon-blue));
            border-radius: 2rem; padding: .3rem 1rem;
            font-weight: 700; font-size: .9rem; color: #fff;
        }
        .stat-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: .45rem 0;
            border-bottom: 1px solid rgba(255,255,255,.06);
            font-size: .85rem;
        }
        .stat-row:last-child { border-bottom: none; }
        .stat-val { font-weight: 700; color: var(--accent); }

        /* XP bar */
        .xp-bar-wrap { width: 100%; padding: 0 .1rem; }
        .xp-label { display: flex; justify-content: space-between; font-size: .75rem; color: var(--text-muted); margin-bottom: .4rem; white-space: nowrap; }
        .xp-bar { height: 100px; background: rgba(255,255,255,.08); border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,.1); }
        .xp-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--neon-blue)); border-radius: 12px; transition: width .6s; min-width: 4px; }

        /* Clickable profile avatar */
        .profile-avatar-btn {
            position: relative; cursor: pointer;
            display: flex; justify-content: center;
            width: 100%;
        }
        .profile-avatar-btn::after {
            content: '✏️';
            position: absolute; bottom: 2px; right: 2px;
            background: var(--surface); border-radius: 50%;
            width: 22px; height: 22px;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; line-height: 22px; text-align: center;
            border: 2px solid var(--primary);
        }

        /* Avatar picker modal */
        .avatar-modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.7);
            z-index: 1000;
            align-items: center; justify-content: center;
        }
        .avatar-modal-overlay.open { display: flex; }
        .avatar-modal {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: 0 0 40px rgba(124,58,237,.5);
            border: 1px solid var(--primary);
            max-width: 480px; width: 90%;
        }
        .avatar-modal-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1rem;
        }
        .avatar-modal-header h3 { margin: 0; font-size: .85rem; letter-spacing: .08em; text-transform: uppercase; color: var(--text-muted); }
        .avatar-modal-close {
            background: none; border: none; color: var(--text-muted);
            font-size: 1.4rem; cursor: pointer; line-height: 1;
            transition: color .2s;
        }
        .avatar-modal-close:hover { color: #fff; }

        /* Avatar grid */
        .avatar-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: .6rem;
        }
        .av-opt {
            cursor: pointer;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid transparent;
            transition: border-color .2s, box-shadow .2s, transform .15s;
            background: var(--bg);
            text-align: center;
        }
        .av-opt img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
        .av-opt span { display: block; font-size: .65rem; color: var(--text-muted); padding: .2rem .3rem; }
        .av-opt:hover { border-color: var(--primary); box-shadow: 0 0 10px rgba(124,58,237,.4); transform: translateY(-2px); }
        .av-opt.selected { border-color: var(--accent); box-shadow: 0 0 14px rgba(249,115,22,.55); }

        /* New avatar unlock card */
        .unlock-card {
            background: linear-gradient(160deg, #1e0a3c, #0d1a3c);
            border: 1px solid var(--primary);
            box-shadow: 0 0 24px rgba(124,58,237,.35);
            display: flex; flex-direction: column; align-items: center; gap: .75rem;
        }
        .unlock-badge {
            font-size: .65rem; letter-spacing: .12em; text-transform: uppercase;
            background: var(--accent); color: #fff; border-radius: 2rem;
            padding: .2rem .8rem; font-weight: 700;
        }
        .unlock-avatar {
            width: 110px; height: 110px;
            border-radius: 12px; object-fit: cover;
            border: 2px solid var(--accent);
            box-shadow: 0 0 20px rgba(249,115,22,.4);
        }
        .btn-unlock {
            width: 100%; padding: .65rem;
            background: linear-gradient(135deg, var(--primary), var(--neon-blue));
            color: #fff; border: none; border-radius: var(--radius);
            font-weight: 700; cursor: pointer; font-size: .9rem;
            transition: opacity .2s;
        }
        .btn-unlock:hover { opacity: .85; }

        /* Stats */
        .stat-bar-wrap { margin-top: .4rem; }
        .stat-bar { height: 6px; background: var(--bg); border-radius: 3px; overflow: hidden; margin-bottom: .1rem; }
        .stat-bar-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg, var(--primary), var(--neon-blue)); }

        /* Nav links card */
        .nav-link-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: .5rem .6rem; border-radius: 8px;
            font-size: .85rem; color: var(--text);
            text-decoration: none;
            transition: background .15s;
            margin-bottom: .3rem;
        }
        .nav-link-item:hover { background: rgba(124,58,237,.2); }
        .nav-link-item .arrow { color: var(--text-muted); }

        /* Log */
        .log-item {
            display: flex; align-items: center; gap: .6rem;
            padding: .4rem 0; border-bottom: 1px solid rgba(255,255,255,.05);
            font-size: .8rem; color: var(--text-muted);
        }
        .log-item:last-child { border-bottom: none; }
        .log-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary); flex-shrink: 0; }

        /* Daily bonus */
        .bonus-card {
            background: linear-gradient(160deg, #0d1a2e, #1a0a30);
            border: 1px solid rgba(6,182,212,.3);
            display: flex; flex-direction: column; align-items: center; gap: .75rem;
            text-align: center;
        }
        .bonus-title { font-size: 1.1rem; font-weight: 800; color: #fff; }
        .bonus-rewards { display: flex; gap: 1rem; justify-content: center; }
        .bonus-item { display: flex; flex-direction: column; align-items: center; gap: .25rem; }
        .bonus-icon { font-size: 1.6rem; }
        .bonus-val { font-weight: 700; font-size: .9rem; color: var(--accent); }
        .bonus-lbl { font-size: .7rem; color: var(--text-muted); }
        .btn-bonus {
            padding: .65rem 2rem;
            background: linear-gradient(135deg, var(--neon-blue), var(--primary));
            color: #fff; border: none; border-radius: var(--radius);
            font-weight: 700; cursor: pointer; font-size: .9rem;
            transition: opacity .2s;
        }
        .btn-bonus:hover { opacity: .85; }
        .btn-bonus:disabled { opacity: .4; cursor: default; }

        .toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem;
            background: var(--primary); color: #fff;
            padding: .75rem 1.25rem; border-radius: var(--radius);
            box-shadow: 0 0 20px rgba(124,58,237,.6);
            font-weight: 600; opacity: 0; transform: translateY(12px);
            transition: opacity .3s, transform .3s; pointer-events: none; z-index: 9999;
        }
        .toast.show { opacity: 1; transform: none; }
    </style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>

<main class="container" style="padding-top:1.5rem; padding-bottom:2rem;">

    <!-- TOP ROW: Profile | Avatar picker | Stats + Nav -->
    <div class="profile-layout">

        <!-- 1. Profile card -->
        <div class="p-card">
            <h3>🧑 Profil</h3>
            <div class="profile-avatar-wrap">
                <div class="profile-avatar-btn" id="openAvatarModal" title="Skift avatar">
                    <?php if ($currentAvatar && isset($avatars[$currentAvatar])): ?>
                    <img id="profileAvatarImg"
                         src="<?= $AVATAR_BASE . $avatars[$currentAvatar]['file'] ?>"
                         alt="<?= htmlspecialchars($avatars[$currentAvatar]['label']) ?>"
                         class="profile-avatar">
                    <?php else: ?>
                    <div id="profileAvatarImg" class="avatar-placeholder">🧑‍💻</div>
                    <?php endif; ?>
                </div>

                <div class="level-pill">
                    ⚔️ Level <?= $progress['level'] ?>
                    <?php if ($curLevel): ?>&nbsp;·&nbsp;<?= htmlspecialchars($curLevel['title']) ?><?php endif; ?>
                </div>

                <div class="xp-bar-wrap">
                    <div class="xp-label">
                        <span><?= number_format($progress['xp']) ?> XP</span>
                        <span>Næste: <?= number_format($nextXp) ?></span>
                    </div>
                    <div class="xp-bar">
                        <div class="xp-fill" style="width:<?= $xpPct ?>%"></div>
                    </div>
                </div>
            </div>

            <div style="margin-top:1rem;">
                <div class="stat-row">
                    <span>🔥 Streak</span>
                    <span class="stat-val"><?= $progress['streak'] ?> dage</span>
                </div>
                <div class="stat-row">
                    <span>🏅 Badges</span>
                    <span class="stat-val"><?= count($earnedTypes) ?>/<?= count($allBadges) ?></span>
                </div>
                <div class="stat-row">
                    <span>📄 Rapporter</span>
                    <span class="stat-val"><?= $reportCount ?></span>
                </div>
                <div class="stat-row">
                    <span>⭐ Total XP</span>
                    <span class="stat-val"><?= number_format($progress['xp']) ?></span>
                </div>
            </div>
        </div>

        <!-- 2. Right column: Stats + Nav links -->
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            <!-- Rapport statistik -->
            <div class="p-card">
                <h3>📊 Rapport statistik</h3>
                <?php
                // XP-based score estimates
                $xpPctStat = min(100, (int)round($progress['xp'] / max(1, $nextXp) * 100));
                $badgePct  = count($allBadges) > 0 ? (int)round(count($earnedTypes) / count($allBadges) * 100) : 0;
                $levelPct  = min(100, (int)round($progress['level'] / 15 * 100));
                ?>
                <div class="stat-row">
                    <span>Antal rapporter</span>
                    <span class="stat-val"><?= $reportCount ?></span>
                </div>
                <div style="margin-top:.6rem;">
                    <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.25rem;">XP fremgang</div>
                    <div class="stat-bar"><div class="stat-bar-fill" style="width:<?= $xpPct ?>%;background:linear-gradient(90deg,var(--primary),var(--neon-blue));"></div></div>
                    <div style="font-size:.75rem;color:var(--accent);text-align:right;"><?= $xpPct ?>%</div>
                </div>
                <div style="margin-top:.4rem;">
                    <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.25rem;">Badges optjent</div>
                    <div class="stat-bar"><div class="stat-bar-fill" style="width:<?= $badgePct ?>%;background:linear-gradient(90deg,#06b6d4,#7c3aed);"></div></div>
                    <div style="font-size:.75rem;color:var(--accent);text-align:right;"><?= $badgePct ?>%</div>
                </div>
                <div style="margin-top:.4rem;">
                    <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.25rem;">Level fremgang</div>
                    <div class="stat-bar"><div class="stat-bar-fill" style="width:<?= $levelPct ?>%;background:linear-gradient(90deg,#f97316,#7c3aed);"></div></div>
                    <div style="font-size:.75rem;color:var(--accent);text-align:right;">Lvl <?= $progress['level'] ?>/15</div>
                </div>
                <a href="index.php" class="btn" style="display:block;text-align:center;margin-top:.9rem;padding:.5rem;font-size:.85rem;">
                    Start træning
                </a>
            </div>

            <!-- Navigation -->
            <div class="p-card">
                <h3>🗺️ Menu + Navigation</h3>
                <a href="dashboard.php" class="nav-link-item">📊 Dashboard <span class="arrow">›</span></a>
                <a href="gamification.php" class="nav-link-item">🏆 Gamification <span class="arrow">›</span></a>
                <a href="quiz.php" class="nav-link-item">🎯 Quiz <span class="arrow">›</span></a>
                <a href="cloze.php" class="nav-link-item">✏️ Cloze <span class="arrow">›</span></a>
                <a href="boss.php" class="nav-link-item">⚔️ Boss Battle <span class="arrow">›</span></a>
                <a href="index.php" class="nav-link-item">📤 Upload rapport <span class="arrow">›</span></a>
            </div>

        </div><!-- /right col -->
    </div><!-- /profile-layout -->

    <!-- BOTTOM ROW -->
    <div class="profile-bottom">

        <!-- Nyeste logger -->
        <div class="p-card">
            <h3>📋 Nyeste logger</h3>
            <?php if (empty($recentReports)): ?>
            <p style="color:var(--text-muted);font-size:.85rem;">Ingen rapporter endnu.</p>
            <?php else: ?>
            <?php foreach ($recentReports as $r): ?>
            <div class="log-item">
                <div class="log-dot"></div>
                <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($r['original_name']) ?>
                </span>
                <span style="flex-shrink:0;"><?= date('d/m', strtotime($r['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Tom rapport / Upload -->
        <div class="p-card" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.75rem;text-align:center;">
            <h3>📤 Tom rapport</h3>
            <div style="font-size:2.5rem;">📄</div>
            <p style="color:var(--text-muted);font-size:.85rem;">
                Hmm… jeg kunne ikke finde nok indhold i denne rapport.
            </p>
            <a href="index.php" class="btn" style="display:inline-block;padding:.6rem 1.5rem;">
                📤 Upload ny rapport
            </a>
        </div>

        <!-- Daily login bonus -->
        <div class="p-card bonus-card">
            <h3 style="color:var(--neon-blue);">🎁 Daily Login Bonus</h3>
            <div class="bonus-title">VELKOMMEN TILBAGE!</div>
            <div class="bonus-rewards">
                <div class="bonus-item">
                    <span class="bonus-icon">⚔️</span>
                    <span class="bonus-val"><?= $progress['level'] ?></span>
                    <span class="bonus-lbl">Niveau</span>
                </div>
                <div class="bonus-item">
                    <span class="bonus-icon">⭐</span>
                    <span class="bonus-val">+50</span>
                    <span class="bonus-lbl">XP bonus</span>
                </div>
                <div class="bonus-item">
                    <span class="bonus-icon">🏅</span>
                    <span class="bonus-val"><?= count($earnedTypes) ?></span>
                    <span class="bonus-lbl">Badges</span>
                </div>
            </div>
            <button class="btn-bonus" id="bonusBtn" <?= !$bonusAvailable ? 'disabled' : '' ?>>
                <?= $bonusAvailable ? 'Claim reward' : 'Allerede hentet i dag' ?>
            </button>
        </div>

    </div><!-- /profile-bottom -->

</main>

<!-- Avatar modal -->
<div class="avatar-modal-overlay" id="avatarModalOverlay">
    <div class="avatar-modal">
        <div class="avatar-modal-header">
            <h3>🎭 Vælg din avatar</h3>
            <button class="avatar-modal-close" id="closeAvatarModal">✕</button>
        </div>
        <div class="avatar-grid">
            <?php foreach ($avatars as $key => $meta): ?>
            <div class="av-opt <?= $key === $currentAvatar ? 'selected' : '' ?>"
                 data-key="<?= $key ?>"
                 title="<?= htmlspecialchars($meta['label']) ?>">
                <img src="<?= $AVATAR_BASE . $meta['file'] ?>"
                     alt="<?= htmlspecialchars($meta['label']) ?>"
                     loading="lazy">
                <span><?= htmlspecialchars($meta['label']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($currentAvatar): ?>
        <p style="margin-top:.75rem;font-size:.8rem;color:var(--text-muted);text-align:center;" id="selectedLabel">
            Valgt: <strong style="color:var(--accent);"><?= htmlspecialchars($avatars[$currentAvatar]['label'] ?? '') ?></strong>
        </p>
        <?php else: ?>
        <p style="margin-top:.75rem;font-size:.8rem;color:var(--text-muted);text-align:center;" id="selectedLabel"></p>
        <?php endif; ?>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const AVATAR_BASE = '<?= $AVATAR_BASE ?>';
const AVATARS = <?= json_encode($avatars) ?>;

// Modal open/close
function openModal() { document.getElementById('avatarModalOverlay').classList.add('open'); }
function closeModal() { document.getElementById('avatarModalOverlay').classList.remove('open'); }

document.getElementById('openAvatarModal').addEventListener('click', openModal);
document.getElementById('closeAvatarModal').addEventListener('click', closeModal);
document.getElementById('avatarModalOverlay').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
});

// Avatar selection
document.querySelectorAll('.av-opt').forEach(el => {
    el.addEventListener('click', () => {
        const key = el.dataset.key;
        document.querySelectorAll('.av-opt').forEach(x => x.classList.remove('selected'));
        el.classList.add('selected');

        const lbl = document.getElementById('selectedLabel');
        if (lbl) lbl.innerHTML = 'Valgt: <strong style="color:var(--accent);">' + (AVATARS[key]?.label ?? '') + '</strong>';

        // Update profile avatar
        const imgEl = document.getElementById('profileAvatarImg');
        const meta  = AVATARS[key];
        if (imgEl && meta) {
            if (imgEl.tagName === 'IMG') {
                imgEl.src = AVATAR_BASE + meta.file;
                imgEl.alt = meta.label;
            } else {
                const img = document.createElement('img');
                img.src = AVATAR_BASE + meta.file;
                img.alt = meta.label;
                img.className = 'profile-avatar';
                img.id = 'profileAvatarImg';
                imgEl.replaceWith(img);
            }
        }

        // Update nav avatar if present
        const navAv = document.getElementById('navAvatarImg');
        if (navAv && meta) navAv.src = AVATAR_BASE + meta.file;

        fetch('profile.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'avatar=' + encodeURIComponent(key)
        }).then(() => showToast('Avatar gemt! 🎮'));
    });
});

// Daily bonus
document.getElementById('bonusBtn')?.addEventListener('click', function() {
    if (this.disabled) return;
    fetch('profile.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'claim_bonus=1'
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            this.disabled = true;
            this.textContent = 'Allerede hentet i dag';
            showToast('🎉 +50 XP bonus hentet!');
        }
    });
});

function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2800);
}
</script>
</body>
</html>
