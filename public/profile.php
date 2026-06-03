<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use ExamQuest\Gamification\XpManager;
use ExamQuest\Gamification\BadgeManager;
use ExamQuest\Gamification\LevelDefinitions;

session_start();

try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    die('Databaseforbindelse fejlede.');
}

$sessionId  = session_id();
$xpManager  = new XpManager($pdo);
$badgeManager = new BadgeManager($pdo);

// Handle avatar selection AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['avatar'])) {
    $allowed = [
        'robot-mascot',
        'retro-robot',
        'dyster-mage',
        'deadline-1',
        'deadline-2',
        'karakter-raekke',
        'hyggelig-studie',
    ];
    $avatar = in_array($_POST['avatar'], $allowed, true) ? $_POST['avatar'] : '';
    if ($avatar !== '') {
        $stmt = $pdo->prepare(
            'INSERT INTO progress (session_id, avatar) VALUES (:sid, :av)
             ON DUPLICATE KEY UPDATE avatar = :av2'
        );
        $stmt->execute([':sid' => $sessionId, ':av' => $avatar, ':av2' => $avatar]);
        $_SESSION['avatar'] = $avatar;
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

$progress   = $xpManager->getProgress($sessionId);
$earned     = $badgeManager->getEarnedBadges($sessionId);
$allBadges  = BadgeManager::getAllDefinitions();
$allLevels  = LevelDefinitions::all();
$curLevel   = LevelDefinitions::get($progress['level']);
$nextLevel  = LevelDefinitions::get($progress['level'] + 1);

$nextXp  = $xpManager->nextLevelThreshold($progress['level']);
$prevXp  = $xpManager->nextLevelThreshold($progress['level'] - 1);
$range   = $nextXp - $prevXp;
$xpInto  = $progress['xp'] - $prevXp;
$xpPct   = $range > 0 ? min(100, (int) round($xpInto / $range * 100)) : 100;

$earnedTypes  = array_column($earned, 'type');
$currentAvatar = $progress['avatar'] ?? $_SESSION['avatar'] ?? '';

$BASE_URL = 'https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/';

$avatars = [
    'robot-mascot'    => ['file' => 'Robot%20mascot.png',                              'label' => 'Robot Mascot'],
    'retro-robot'     => ['file' => 'Retro%20futurist%20robot%20med%20neonstemning.png','label' => 'Retro Robot'],
    'dyster-mage'     => ['file' => 'Dyster%20mage.png',                               'label' => 'Dyster Mage'],
    'deadline-1'      => ['file' => 'Deadline%20herrens%201.png',                      'label' => 'Deadline Herre I'],
    'deadline-2'      => ['file' => 'Deadline%20herrens%202.png',                      'label' => 'Deadline Herre II'],
    'karakter-raekke' => ['file' => 'Karakterr%C3%A6kke%20med%20neon%20og%20detaljer.png', 'label' => 'Karakterrække'],
    'hyggelig-studie' => ['file' => 'Hyggelig%20studieaften.png',                      'label' => 'Studieaften'],
];

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
        .profile-grid {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 800px) {
            .profile-grid { grid-template-columns: 1fr; }
        }
        .profile-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        .avatar-display {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            box-shadow: 0 0 20px rgba(124,58,237,.5);
        }
        .avatar-placeholder {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: var(--bg);
            border: 3px dashed var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--primary);
        }
        .level-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: linear-gradient(135deg, var(--primary), var(--neon-blue));
            border-radius: 2rem;
            padding: .4rem 1.2rem;
            font-weight: 700;
            font-size: 1.1rem;
            color: #fff;
            text-shadow: 0 0 8px rgba(255,255,255,.4);
        }
        .xp-bar-wrap {
            width: 100%;
        }
        .xp-bar-wrap label {
            display: flex;
            justify-content: space-between;
            font-size: .85rem;
            color: var(--text-muted);
            margin-bottom: .4rem;
        }
        .xp-bar {
            height: 10px;
            background: var(--bg);
            border-radius: 5px;
            overflow: hidden;
        }
        .xp-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--neon-blue));
            border-radius: 5px;
            transition: width .6s ease;
        }
        /* Avatar selector */
        .avatar-section {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .avatar-section h2 {
            margin-bottom: 1.25rem;
        }
        .avatar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 1rem;
        }
        .avatar-option {
            cursor: pointer;
            border-radius: var(--radius);
            overflow: hidden;
            border: 3px solid transparent;
            transition: border-color .2s, box-shadow .2s, transform .15s;
            background: var(--bg);
            text-align: center;
        }
        .avatar-option img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            display: block;
        }
        .avatar-option span {
            display: block;
            font-size: .75rem;
            color: var(--text-muted);
            padding: .35rem .5rem;
        }
        .avatar-option:hover {
            border-color: var(--primary);
            box-shadow: 0 0 12px rgba(124,58,237,.5);
            transform: translateY(-3px);
        }
        .avatar-option.selected {
            border-color: var(--accent);
            box-shadow: 0 0 18px rgba(249,115,22,.6);
        }
        /* Badges */
        .badges-section {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .badge-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .badge-card {
            background: var(--bg);
            border-radius: var(--radius);
            padding: 1rem;
            text-align: center;
        }
        .badge-card .badge-icon { font-size: 2rem; }
        .badge-card .badge-name { font-size: .8rem; font-weight: 600; margin-top: .4rem; }
        .badge-card .badge-desc { font-size: .7rem; color: var(--text-muted); }
        .badge-card.earned { border: 1px solid var(--accent); box-shadow: 0 0 8px rgba(249,115,22,.3); }
        .badge-card.locked { opacity: .28; filter: grayscale(1); }
        .toast {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            background: var(--primary);
            color: #fff;
            padding: .75rem 1.25rem;
            border-radius: var(--radius);
            box-shadow: 0 0 20px rgba(124,58,237,.6);
            font-weight: 600;
            opacity: 0;
            transform: translateY(12px);
            transition: opacity .3s, transform .3s;
            pointer-events: none;
            z-index: 9999;
        }
        .toast.show { opacity: 1; transform: none; }
    </style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>

<main class="container" style="padding-top:2rem;">
    <h1 style="margin-bottom:1.5rem;">🧙 Min Profil</h1>

    <div class="profile-grid">
        <!-- Left: avatar + level + xp -->
        <div class="profile-card">
            <?php if ($currentAvatar && isset($avatars[$currentAvatar])): ?>
                <img
                    src="<?= $BASE_URL . $avatars[$currentAvatar]['file'] ?>"
                    alt="<?= htmlspecialchars($avatars[$currentAvatar]['label']) ?>"
                    class="avatar-display"
                    id="profileAvatarImg"
                >
            <?php else: ?>
                <div class="avatar-placeholder" id="profileAvatarImg">🧑‍💻</div>
            <?php endif; ?>

            <div class="level-badge">
                ⚔️ Level <?= $progress['level'] ?>
                <?php if ($curLevel): ?> — <?= htmlspecialchars($curLevel['title']) ?><?php endif; ?>
            </div>

            <div class="xp-bar-wrap">
                <label>
                    <span>XP: <?= number_format($progress['xp']) ?></span>
                    <span>Næste: <?= number_format($nextXp) ?></span>
                </label>
                <div class="xp-bar">
                    <div class="xp-bar-fill" style="width:<?= $xpPct ?>%"></div>
                </div>
            </div>

            <p style="color:var(--text-muted); font-size:.85rem; text-align:center;">
                🔥 <?= $progress['streak'] ?> dages streak
                &nbsp;·&nbsp;
                🏅 <?= count($earnedTypes) ?>/<?= count($allBadges) ?> badges
            </p>
        </div>

        <!-- Right: level roadmap snippet -->
        <div style="background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;overflow:auto;">
            <h2 style="margin-bottom:1rem;">🗺️ Level-oversigt</h2>
            <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                <thead>
                    <tr style="color:var(--text-muted);">
                        <th style="text-align:left;padding:.4rem .6rem;">Lvl</th>
                        <th style="text-align:left;padding:.4rem .6rem;">Titel</th>
                        <th style="text-align:right;padding:.4rem .6rem;">XP krævet</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allLevels as $lvl): ?>
                    <?php $isCurrentLvl = $lvl['level'] === (int)$progress['level']; ?>
                    <tr style="<?= $isCurrentLvl ? 'background:rgba(124,58,237,.15);' : '' ?> border-top:1px solid rgba(255,255,255,.05);">
                        <td style="padding:.4rem .6rem;font-weight:700;color:<?= $isCurrentLvl ? 'var(--accent)' : 'inherit' ?>;">
                            <?= $lvl['level'] ?>
                        </td>
                        <td style="padding:.4rem .6rem;"><?= htmlspecialchars($lvl['title']) ?></td>
                        <td style="padding:.4rem .6rem;text-align:right;color:var(--text-muted);">
                            <?= $lvl['level'] === 1 ? '0' : number_format($xpManager->nextLevelThreshold($lvl['level'] - 1)) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Avatar selector -->
    <section class="avatar-section">
        <h2>🎭 Vælg Avatar</h2>
        <div class="avatar-grid">
            <?php foreach ($avatars as $key => $meta): ?>
            <div
                class="avatar-option <?= $key === $currentAvatar ? 'selected' : '' ?>"
                data-key="<?= htmlspecialchars($key) ?>"
                title="<?= htmlspecialchars($meta['label']) ?>"
            >
                <img
                    src="<?= $BASE_URL . $meta['file'] ?>"
                    alt="<?= htmlspecialchars($meta['label']) ?>"
                    loading="lazy"
                >
                <span><?= htmlspecialchars($meta['label']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Badges -->
    <section class="badges-section">
        <h2>🏅 Badges</h2>
        <div class="badge-grid">
            <?php foreach ($allBadges as $type => $def):
                $isEarned = in_array($type, $earnedTypes, true);
            ?>
            <div class="badge-card <?= $isEarned ? 'earned' : 'locked' ?>">
                <div class="badge-icon"><?= $def['icon'] ?></div>
                <div class="badge-name"><?= htmlspecialchars($def['label']) ?></div>
                <div class="badge-desc"><?= htmlspecialchars($def['description'] ?? '') ?></div>
                <?php if ($isEarned): ?>
                    <div style="font-size:.7rem;color:var(--accent);margin-top:.3rem;">✓ Optjent</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<div class="toast" id="toast">Avatar gemt! 🎉</div>

<script>
const BASE_URL = '<?= $BASE_URL ?>';
const AVATARS  = <?= json_encode($avatars) ?>;

document.querySelectorAll('.avatar-option').forEach(el => {
    el.addEventListener('click', () => {
        const key = el.dataset.key;
        document.querySelectorAll('.avatar-option').forEach(x => x.classList.remove('selected'));
        el.classList.add('selected');

        // Update large avatar in profile card
        const profileImg = document.getElementById('profileAvatarImg');
        const meta = AVATARS[key];
        if (profileImg && meta) {
            if (profileImg.tagName === 'IMG') {
                profileImg.src = BASE_URL + meta.file;
                profileImg.alt = meta.label;
            } else {
                // Replace placeholder div with img
                const img = document.createElement('img');
                img.src = BASE_URL + meta.file;
                img.alt = meta.label;
                img.className = 'avatar-display';
                img.id = 'profileAvatarImg';
                profileImg.replaceWith(img);
            }
        }

        // Persist
        fetch('profile.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'avatar=' + encodeURIComponent(key)
        }).then(() => showToast());
    });
});

function showToast() {
    const t = document.getElementById('toast');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}
</script>
</body>
</html>
