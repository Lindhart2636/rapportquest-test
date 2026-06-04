<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use ExamQuest\Gamification\XpManager;
use ExamQuest\Gamification\StreakManager;
use ExamQuest\Gamification\LevelDefinitions;

session_start();

$xp = 0; $level = 1; $streak = 0; $readiness = 0;
$levelTitle = 'Nybegynder';
$nextXpThreshold = 100;
$prevXpThreshold = 0;
$xpPct = 0;
$xpToday = 0;
$currentAvatar = $_SESSION['avatar'] ?? '';

$AVATAR_BASE = 'https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/';
$navAvatarUrl = '';
if ($currentAvatar && preg_match('/^avatar-(\d+)$/', $currentAvatar, $m)) {
    $navAvatarUrl = $AVATAR_BASE . 'Avatar%20' . $m[1] . '.png';
}

// Latest report ID for sidebar links
$latestReportId = 0;

try {
    $pdo = getDbConnection();
    $sessionId  = session_id();
    $xpManager  = new XpManager($pdo);
    $streakManager = new StreakManager($pdo);
    $progress   = $xpManager->getProgress($sessionId);
    $xp         = $progress['xp'];
    $level      = $progress['level'];
    $streak     = $progress['streak'];
    $lvlDef     = LevelDefinitions::get($level);
    $levelTitle = $lvlDef['title'] ?? 'Nybegynder';
    $nextXpThreshold = $xpManager->nextLevelThreshold($level);
    $prevXpThreshold = $xpManager->nextLevelThreshold($level - 1);
    $range  = $nextXpThreshold - $prevXpThreshold;
    $xpInto = $xp - $prevXpThreshold;
    $xpPct  = $range > 0 ? min(100, (int)round($xpInto / $range * 100)) : 100;
    $xpToday = $_SESSION['xp_today'] ?? 0;

    $r = $pdo->query('SELECT id FROM reports ORDER BY id DESC LIMIT 1')->fetch();
    $latestReportId = $r ? (int)$r['id'] : 0;

    $readiness = 0;
} catch (Exception $e) {
    // silently continue with defaults
}

$currentPage = 'index.php';
$LOGO_URL = $AVATAR_BASE . 'ExamQuest%20logo%20med%20futuristisk%20design.png';
$qUrl     = $latestReportId ? "quiz.php?id={$latestReportId}"  : '#upload';
$cUrl     = $latestReportId ? "cloze.php?id={$latestReportId}" : '#upload';
$bUrl     = $latestReportId ? "boss.php?id={$latestReportId}"  : '#upload';
$noReport = !$latestReportId;
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExamQuest — Drop din rapport</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* ── Layout ── */
        body { display: flex; flex-direction: column; min-height: 100vh; overflow-x: hidden; }

        .app-shell {
            display: grid;
            grid-template-columns: 220px 1fr;
            grid-template-rows: 56px 1fr auto;
            min-height: 100vh;
        }

        /* ── Top bar ── */
        .top-bar {
            grid-column: 1 / -1;
            display: flex; align-items: center; gap: 1rem;
            padding: 0 1.25rem;
            background: var(--surface);
            border-bottom: 1px solid rgba(255,255,255,.07);
            position: sticky; top: 0; z-index: 100;
        }
        .top-bar-logo {
            display: flex; align-items: center; gap: .5rem;
            text-decoration: none; flex-shrink: 0;
        }
        .top-bar-logo img { height: 32px; width: auto; }
        .top-bar-logo span {
            font-weight: 900; font-size: 1.1rem;
            background: linear-gradient(135deg, #a78bfa, #06b6d4);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            letter-spacing: .03em;
        }
        .top-bar-divider { flex: 1; }
        .top-bar-stat {
            display: flex; align-items: center; gap: .4rem;
            background: rgba(255,255,255,.06); border-radius: 2rem;
            padding: .3rem .9rem; font-size: .85rem; font-weight: 600;
        }
        .top-bar-stat .icon { font-size: 1rem; }

        /* ── Sidebar ── */
        .sidebar {
            background: var(--surface);
            border-right: 1px solid rgba(255,255,255,.07);
            display: flex; flex-direction: column;
            padding: 1.25rem 0;
        }
        .sidebar-nav { flex: 1; }
        .sidebar-link {
            display: flex; align-items: center; gap: .75rem;
            padding: .65rem 1.25rem;
            color: var(--text-muted); text-decoration: none;
            font-size: .9rem; font-weight: 500;
            border-left: 3px solid transparent;
            transition: color .15s, background .15s, border-color .15s;
        }
        .sidebar-link:hover { color: var(--text); background: rgba(124,58,237,.1); }
        .sidebar-link.active { color: #fff; background: rgba(124,58,237,.18); border-left-color: var(--primary); }
        .sidebar-link .s-icon { font-size: 1.1rem; width: 22px; text-align: center; }
        .sidebar-link .s-img { width: 22px; height: 22px; object-fit: contain; filter: brightness(.7); transition: filter .15s; }
        .sidebar-link:hover .s-img, .sidebar-link.active .s-img { filter: brightness(1.2); }
        .sidebar-disabled { opacity: .4; pointer-events: none; }

        .sidebar-user {
            padding: 1rem 1.25rem;
            border-top: 1px solid rgba(255,255,255,.07);
        }
        .sidebar-user-row {
            display: flex; align-items: center; gap: .65rem;
            margin-bottom: .6rem;
        }
        .sidebar-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--primary);
            flex-shrink: 0;
        }
        .sidebar-avatar-placeholder {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--bg); border: 2px solid var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }
        .sidebar-username { font-weight: 700; font-size: .85rem; }
        .sidebar-level { font-size: .75rem; color: var(--text-muted); }
        .sidebar-xp-bar { height: 5px; background: rgba(255,255,255,.08); border-radius: 3px; overflow: hidden; }
        .sidebar-xp-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--neon-blue)); border-radius: 3px; }
        .sidebar-xp-label { display: flex; justify-content: space-between; font-size: .7rem; color: var(--text-muted); margin-top: .3rem; }

        /* ── Main content ── */
        .main-content {
            display: flex; flex-direction: column;
            overflow: hidden;
        }

        /* ── Hero ── */
        .hero {
            position: relative;
            overflow: hidden;
            flex: 1;
            min-height: 0;
            height: 100%;
        }
        .hero-bg {
            position: absolute; inset: 0;
            background-image: url('<?= $AVATAR_BASE ?>Hyggelig%20studieaften.png');
            background-size: cover; background-position: center top;
            filter: brightness(.65);
        }
        .hero-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(to right, rgba(10,10,26,.7) 40%, rgba(10,10,26,.05));
        }
        .hero-content {
            position: relative; z-index: 1;
            padding: 2.5rem 2rem 2rem;
            max-width: 480px;
        }
        .hero-content h1 {
            font-size: 2rem; font-weight: 900; line-height: 1.15;
            margin-bottom: .4rem; color: #fff;
        }
        .hero-content h1 span {
            background: linear-gradient(135deg, var(--primary), var(--neon-blue));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .hero-content p { color: rgba(255,255,255,.75); font-size: .95rem; margin-bottom: 1.5rem; }

        .hero-actions { display: flex; flex-direction: column; gap: .75rem; align-items: flex-start; }
        .btn-hero {
            display: inline-flex; align-items: center; gap: .5rem;
            background: var(--primary); color: #fff;
            padding: .75rem 1.75rem; border-radius: var(--radius);
            font-weight: 700; font-size: 1rem; text-decoration: none;
            border: none; cursor: pointer;
            box-shadow: 0 0 20px rgba(124,58,237,.4);
            transition: box-shadow .2s, transform .15s;
        }
        .btn-hero:hover { box-shadow: 0 0 30px rgba(124,58,237,.7); transform: translateY(-2px); }
        .btn-secondary {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(255,255,255,.1); color: #fff;
            padding: .65rem 1.25rem; border-radius: var(--radius);
            font-weight: 600; font-size: .9rem; text-decoration: none;
            border: 1px solid rgba(255,255,255,.2); cursor: pointer;
            transition: background .2s;
        }
        .btn-secondary:hover { background: rgba(255,255,255,.18); }

        .postit {
            background: #f5d042; color: #1a1a1a;
            padding: .65rem .9rem; border-radius: 4px;
            font-size: .82rem; font-weight: 600; max-width: 210px;
            box-shadow: 3px 3px 0 rgba(0,0,0,.25);
            transform: rotate(-1.5deg); margin-top: .75rem;
            font-family: 'Comic Sans MS', cursive, sans-serif;
        }

        /* Upload modal */
        .upload-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.75); z-index: 200;
            align-items: center; justify-content: center;
        }
        .upload-overlay.open { display: flex; }
        .upload-modal {
            background: var(--surface); border-radius: var(--radius);
            padding: 2rem; max-width: 480px; width: 90%;
            box-shadow: 0 0 40px rgba(124,58,237,.5);
            border: 1px solid var(--primary);
        }
        .upload-modal h2 { margin-bottom: .5rem; }
        .upload-modal p { color: var(--text-muted); font-size: .9rem; margin-bottom: 1.25rem; }
        .modal-close {
            float: right; background: none; border: none;
            color: var(--text-muted); font-size: 1.4rem; cursor: pointer;
        }

        /* ── Bottom stats bar ── */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1px;
            background: rgba(255,255,255,.07);
            border-top: 1px solid rgba(255,255,255,.07);
        }
        .stats-bar-item {
            background: var(--surface);
            padding: .9rem 1.25rem;
        }
        .stats-bar-label { font-size: .7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .08em; margin-bottom: .2rem; }
        .stats-bar-value { font-size: 1.5rem; font-weight: 900; color: #fff; line-height: 1; }
        .stats-bar-sub { font-size: .72rem; color: var(--text-muted); margin-top: .15rem; }
        .stats-bar-value .accent { color: var(--accent); }

        /* Readiness circle */
        .readiness-wrap { display: flex; align-items: center; gap .75rem; }
        .readiness-circle { position: relative; width: 52px; height: 52px; flex-shrink: 0; }
        .readiness-circle svg { transform: rotate(-90deg); }
        .readiness-circle .pct-text {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 800; color: #fff;
        }
        .readiness-desc { font-size: .72rem; color: var(--text-muted); margin-top: .2rem; }

        @media (max-width: 900px) {
            .app-shell { grid-template-columns: 1fr; }
            .sidebar { display: none; }
            .hero { grid-template-columns: 1fr; }
            .hero-visual { display: none; }
            .stats-bar { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="app-shell">

    <!-- TOP BAR -->
    <header class="top-bar">
        <a href="index.php" class="top-bar-logo">
            <img src="<?= $LOGO_URL ?>" alt="ExamQuest logo">
        </a>

        <div class="top-bar-divider"></div>

        <div class="top-bar-stat">
            <span class="icon">⚡</span>
            Level <?= $level ?>
        </div>
        <div class="top-bar-stat">
            <?= number_format($xp) ?> / <?= number_format($nextXpThreshold) ?> XP
        </div>
        <div class="top-bar-stat">
            <span class="icon">🔥</span>
            <?= $streak ?>
        </div>
    </header>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <nav class="sidebar-nav">
            <a href="index.php"     class="sidebar-link active">
                <span class="s-icon">🏠</span> Hjem
            </a>
            <a href="<?= $qUrl ?>" class="sidebar-link <?= $noReport ? 'sidebar-disabled' : '' ?>">
                <span class="s-icon">🎯</span> Quiz
            </a>
            <a href="<?= $cUrl ?>" class="sidebar-link <?= $noReport ? 'sidebar-disabled' : '' ?>">
                <span class="s-icon">✏️</span> Cloze
            </a>
            <a href="<?= $bUrl ?>" class="sidebar-link <?= $noReport ? 'sidebar-disabled' : '' ?>">
                <span class="s-icon">⚔️</span> Boss Battle
            </a>
            <a href="dashboard.php" class="sidebar-link">
                <span class="s-icon">📊</span> Statistik
            </a>
            <a href="gamification.php" class="sidebar-link">
                <span class="s-icon">🏅</span> Badges
            </a>
            <a href="profile.php" class="sidebar-link">
                <span class="s-icon">👤</span> Profil
            </a>
        </nav>

        <div class="sidebar-user">
            <div class="sidebar-user-row">
                <?php if ($navAvatarUrl): ?>
                <img src="<?= $navAvatarUrl ?>" alt="Avatar" class="sidebar-avatar">
                <?php else: ?>
                <div class="sidebar-avatar-placeholder">🧑‍💻</div>
                <?php endif; ?>
                <div>
                    <div class="sidebar-username">Spiller</div>
                    <div class="sidebar-level">Level <?= $level ?> · <?= htmlspecialchars($levelTitle) ?></div>
                </div>
            </div>
            <div class="sidebar-xp-bar">
                <div class="sidebar-xp-fill" style="width:<?= $xpPct ?>%"></div>
            </div>
            <div class="sidebar-xp-label">
                <span><?= number_format($xp) ?> XP</span>
                <span><?= number_format($nextXpThreshold) ?> XP</span>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="main-content">

        <!-- HERO -->
        <section class="hero">
            <div class="hero-bg"></div>
            <div class="hero-overlay"></div>

            <div class="hero-content">
                <h1>Drop din rapport.<br><span>Vi gør den eksamen-klar.</span></h1>
                <p>Upload din rapport og få interaktive aktiviteter, der gør dig klar til mundtlig eksamen.</p>

                <div class="hero-actions">
                    <button class="btn-hero" id="openUpload">📤 Upload rapport</button>
                    <a href="#how" class="btn-secondary">⊙ Sådan virker det</a>
                </div>

                <div class="postit">Du er tættere på eksamen end du tror. 😊</div>
            </div>

        </section>

        <!-- HOW IT WORKS -->
        <section id="how" style="padding:1rem 2rem 1.5rem; display:grid; grid-template-columns:repeat(3,1fr); gap:1rem;">
            <a href="<?= $qUrl ?>" style="background:var(--surface);border-radius:var(--radius);padding:1rem;text-align:center;text-decoration:none;display:block;transition:box-shadow .2s;" onmouseover="this.style.boxShadow='0 0 16px rgba(124,58,237,.4)'" onmouseout="this.style.boxShadow='none'">
                <img src="<?= $AVATAR_BASE ?>quiz%20ikon.png" style="width:48px;height:48px;object-fit:contain;margin-bottom:.5rem;">
                <div style="font-weight:700;margin-bottom:.25rem;color:#fff;">Quiz</div>
                <div style="font-size:.8rem;color:var(--text-muted);">Multiple-choice baseret på rapportens kernebegreber</div>
            </a>
            <a href="<?= $cUrl ?>" style="background:var(--surface);border-radius:var(--radius);padding:1rem;text-align:center;text-decoration:none;display:block;transition:box-shadow .2s;" onmouseover="this.style.boxShadow='0 0 16px rgba(6,182,212,.4)'" onmouseout="this.style.boxShadow='none'">
                <img src="<?= $AVATAR_BASE ?>cloze%20mode%20ikon.png" style="width:48px;height:48px;object-fit:contain;margin-bottom:.5rem;">
                <div style="font-weight:700;margin-bottom:.25rem;color:#fff;">Cloze</div>
                <div style="font-size:.8rem;color:var(--text-muted);">Udfyldningsopgaver der træner fagtermer</div>
            </a>
            <a href="<?= $bUrl ?>" style="background:var(--surface);border-radius:var(--radius);padding:1rem;text-align:center;text-decoration:none;display:block;transition:box-shadow .2s;" onmouseover="this.style.boxShadow='0 0 16px rgba(249,115,22,.4)'" onmouseout="this.style.boxShadow='none'">
                <img src="<?= $AVATAR_BASE ?>boss%20battle%20ikon.png" style="width:48px;height:48px;object-fit:contain;margin-bottom:.5rem;">
                <div style="font-weight:700;margin-bottom:.25rem;color:#fff;">Boss Battle</div>
                <div style="font-size:.8rem;color:var(--text-muted);">Åbne spørgsmål der tester dybdegående forståelse</div>
            </a>
        </section>

        <!-- STATS BAR -->
        <div class="stats-bar">
            <div class="stats-bar-item">
                <div class="stats-bar-label">XP</div>
                <div class="stats-bar-value"><?= number_format($xp) ?> <span style="font-size:1rem;">⚡</span></div>
                <div class="stats-bar-sub"><?= $xpToday > 0 ? '+' . $xpToday . ' XP i dag' : 'Begynd at optjene XP' ?></div>
            </div>
            <div class="stats-bar-item">
                <div class="stats-bar-label">Level</div>
                <div class="stats-bar-value"><?= $level ?></div>
                <div class="stats-bar-sub">Næste: <?= number_format($nextXpThreshold - $xp) ?> XP</div>
            </div>
            <div class="stats-bar-item">
                <div class="stats-bar-label">Streak</div>
                <div class="stats-bar-value"><?= $streak ?> dage <span style="font-size:1rem;">🔥</span></div>
                <div class="stats-bar-sub"><?= $streak >= 3 ? 'Du er on fire!' : 'Bliv ved hver dag!' ?></div>
            </div>
            <div class="stats-bar-item">
                <div class="stats-bar-label">Eksamen Klar-score</div>
                <div style="display:flex;align-items:center;gap:.65rem;margin-top:.15rem;">
                    <?php
                    $r = 22; $circ = 2 * M_PI * $r;
                    $dash = $circ * $readiness / 100;
                    ?>
                    <div class="readiness-circle">
                        <svg width="52" height="52" viewBox="0 0 52 52">
                            <circle cx="26" cy="26" r="<?= $r ?>" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="5"/>
                            <circle cx="26" cy="26" r="<?= $r ?>" fill="none"
                                stroke="<?= $readiness >= 70 ? '#06b6d4' : ($readiness >= 40 ? '#f97316' : '#7c3aed') ?>"
                                stroke-width="5"
                                stroke-dasharray="<?= round($dash, 1) ?> <?= round($circ, 1) ?>"
                                stroke-linecap="round"/>
                        </svg>
                        <div class="pct-text"><?= $readiness ?>%</div>
                    </div>
                    <div class="readiness-desc"><?= $readiness >= 70 ? 'Du er godt på vej! 🎉' : ($readiness >= 40 ? 'Fortsæt!' : 'Upload en rapport') ?></div>
                </div>
            </div>
        </div>

    </div><!-- /main-content -->

</div><!-- /app-shell -->

<!-- UPLOAD MODAL -->
<div class="upload-overlay" id="uploadOverlay">
    <div class="upload-modal">
        <button class="modal-close" id="closeUpload">✕</button>
        <h2>📤 Upload din rapport</h2>
        <p>Vi analyserer din PDF og genererer quiz, cloze og boss battle automatisk.</p>

        <div id="message-area" class="message-area" role="alert" aria-live="polite"></div>

        <form id="upload-form" method="POST" action="upload.php" enctype="multipart/form-data" novalidate>
            <div class="drop-zone" id="drop-zone">
                <span class="drop-icon">📄</span>
                <p>Træk og slip din PDF her</p>
                <p class="or-divider">— eller —</p>
                <label for="pdf-file" class="file-label">
                    Vælg PDF-fil
                    <input type="file" id="pdf-file" name="report" accept=".pdf,application/pdf" required class="file-input">
                </label>
                <p id="file-name" class="file-name"></p>
            </div>
            <progress id="upload-progress" value="0" max="100" class="upload-progress"></progress>
            <button type="submit" class="btn-submit" id="submit-btn">Start læringsforløb</button>
        </form>
    </div>
</div>

<script src="js/app.js"></script>
<script>
const overlay = document.getElementById('uploadOverlay');
document.getElementById('openUpload').addEventListener('click', () => overlay.classList.add('open'));
document.getElementById('closeUpload').addEventListener('click', () => overlay.classList.remove('open'));
overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
</script>
</body>
</html>
