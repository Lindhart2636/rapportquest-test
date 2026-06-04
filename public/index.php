<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use ExamQuest\Gamification\XpManager;
use ExamQuest\Gamification\StreakManager;
use ExamQuest\Gamification\LevelDefinitions;

session_start();

$xp = 0; $level = 1; $streak = 0;
$nextXpThreshold = 100;
$prevXpThreshold = 0;
$xpPct = 0;
$currentAvatar = $_SESSION['avatar'] ?? '';

$AVATAR_BASE = 'https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/';
$LOGO_URL    = $AVATAR_BASE . 'ExamQuest%20logo%20med%20futuristisk%20design.png';

$latestReportId = 0;

try {
    $pdo = getDbConnection();
    $sessionId     = session_id();
    $xpManager     = new XpManager($pdo);
    $progress      = $xpManager->getProgress($sessionId);
    $xp            = $progress['xp'];
    $level         = $progress['level'];
    $streak        = $progress['streak'];
    $lvlDef        = LevelDefinitions::get($level);
    $nextXpThreshold = $xpManager->nextLevelThreshold($level);
    $prevXpThreshold = $xpManager->nextLevelThreshold($level - 1);
    $range  = $nextXpThreshold - $prevXpThreshold;
    $xpInto = $xp - $prevXpThreshold;
    $xpPct  = $range > 0 ? min(100, (int)round($xpInto / $range * 100)) : 100;

    $r = $pdo->query('SELECT id FROM reports ORDER BY id DESC LIMIT 1')->fetch();
    $latestReportId = $r ? (int)$r['id'] : 0;
} catch (Exception $e) {}

$qUrl     = $latestReportId ? "quiz.php?id={$latestReportId}"  : '#upload';
$cUrl     = $latestReportId ? "cloze.php?id={$latestReportId}" : '#upload';
$bUrl     = $latestReportId ? "boss.php?id={$latestReportId}"  : '#upload';
$noReport = !$latestReportId;

$navAvatarUrl = '';
if ($currentAvatar && preg_match('/^avatar-(\d+)$/', $currentAvatar, $m)) {
    $navAvatarUrl = $AVATAR_BASE . 'Avatar%20' . $m[1] . '.png';
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExamQuest — Gør din rapport eksamen-klar</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* ─── Reset & base ─── */
        html { scroll-behavior: smooth; }
        body { overflow-x: hidden; }

        /* ─── Landing nav ─── */
        .lp-nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 200;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 2rem;
            height: 76px;
            background: rgba(10,10,26,.85);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(124,58,237,.18);
            transition: background .3s;
        }
        .lp-nav-logo img {
            height: 56px; width: auto;
            padding: 10px 0; margin: 10px 0;
        }
        .lp-nav-links { display: flex; align-items: center; gap: .5rem; }
        .lp-nav-link {
            padding: .4rem .85rem; border-radius: 8px;
            color: var(--text-muted); font-size: .875rem; font-weight: 500;
            text-decoration: none; border: 1px solid transparent;
            transition: all .15s;
        }
        .lp-nav-link:hover { color: #fff; background: rgba(124,58,237,.15); border-color: var(--border); }
        .lp-nav-icon-link { display: inline-flex; align-items: center; gap: .4rem; }
        .lp-nav-icon { width: 18px; height: 18px; object-fit: contain; filter: brightness(.7); transition: filter .15s; }
        .lp-nav-icon-link:hover .lp-nav-icon { filter: brightness(1.3); }
        .lp-nav-cta {
            padding: .45rem 1.2rem; border-radius: 8px;
            background: var(--primary); color: #fff;
            font-size: .875rem; font-weight: 700;
            text-decoration: none; border: none; cursor: pointer;
            box-shadow: 0 0 16px rgba(124,58,237,.4);
            transition: box-shadow .2s, transform .15s;
        }
        .lp-nav-cta:hover { box-shadow: 0 0 28px rgba(124,58,237,.7); transform: translateY(-1px); }
        .lp-nav-avatar {
            width: 30px; height: 30px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--primary);
        }

        /* ─── Hero ─── */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex; align-items: center;
            overflow: hidden;
            padding-top: 76px;
        }
        .hero-bg {
            position: absolute; inset: 0;
            background-image: url('<?= $AVATAR_BASE ?>Hyggelig%20studieaften.png');
            background-size: cover; background-position: center 30%;
            filter: brightness(.55);
        }
        .hero-gradient {
            position: absolute; inset: 0;
            background: linear-gradient(
                105deg,
                rgba(10,10,26,.92) 0%,
                rgba(10,10,26,.7)  45%,
                rgba(10,10,26,.15) 100%
            );
        }
        .hero-content {
            position: relative; z-index: 1;
            max-width: 600px;
            padding: 4rem 2rem 4rem 5vw;
        }
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(124,58,237,.2); border: 1px solid rgba(124,58,237,.45);
            border-radius: 999px; padding: .3rem .9rem;
            font-size: .8rem; font-weight: 700; color: #a78bfa;
            letter-spacing: .06em; text-transform: uppercase;
            margin-bottom: 1.5rem;
        }
        .hero-title {
            font-size: clamp(2.2rem, 5vw, 3.6rem);
            font-weight: 900; line-height: 1.1;
            color: #fff; margin-bottom: 1.25rem;
        }
        .hero-title .grad {
            background: linear-gradient(135deg, #a78bfa 0%, #7c3aed 50%, #06b6d4 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-sub {
            font-size: 1.1rem; color: rgba(255,255,255,.72);
            line-height: 1.65; margin-bottom: 2.25rem; max-width: 480px;
        }
        .hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
        .btn-cta {
            display: inline-flex; align-items: center; gap: .55rem;
            padding: .85rem 2rem; border-radius: 10px;
            background: var(--primary); color: #fff;
            font-size: 1rem; font-weight: 800; text-decoration: none;
            border: none; cursor: pointer;
            box-shadow: 0 0 28px rgba(124,58,237,.5);
            transition: box-shadow .2s, transform .15s;
        }
        .btn-cta:hover { box-shadow: 0 0 44px rgba(124,58,237,.8); transform: translateY(-2px); }
        .btn-ghost {
            display: inline-flex; align-items: center; gap: .55rem;
            padding: .8rem 1.6rem; border-radius: 10px;
            background: rgba(255,255,255,.08); color: #fff;
            font-size: .95rem; font-weight: 600; text-decoration: none;
            border: 1px solid rgba(255,255,255,.2);
            transition: background .2s;
        }
        .btn-ghost:hover { background: rgba(255,255,255,.16); }
        .hero-stats {
            display: flex; gap: 2.5rem; margin-top: 3rem;
            flex-wrap: wrap; align-items: flex-start;
        }
        .hero-stats > div { text-align: left; }
        .hero-stat-num {
            font-size: 1.9rem; font-weight: 900; color: #fff; line-height: 1;
        }
        .hero-stat-num .accent { color: var(--accent); }
        .hero-stat-label { font-size: .78rem; color: rgba(255,255,255,.5); margin-top: .2rem; }

        /* ─── Section shared ─── */
        section { padding: 5rem 2rem; }
        .section-inner { max-width: 1100px; margin: 0 auto; }
        .section-eyebrow {
            font-size: .75rem; font-weight: 800; letter-spacing: .12em;
            text-transform: uppercase; color: var(--primary);
            margin-bottom: .75rem;
        }
        .section-title {
            font-size: clamp(1.7rem, 3vw, 2.4rem);
            font-weight: 900; color: #fff; line-height: 1.2;
            margin-bottom: .75rem;
        }
        .section-sub {
            color: var(--text-muted); font-size: 1rem;
            max-width: 560px; line-height: 1.65;
        }

        /* ─── Features ─── */
        .features-section {
            background: var(--surface);
            position: relative;
            overflow: hidden;
        }
        .features-section-bg {
            position: absolute;
            right: -60px; top: 0; bottom: 0;
            width: auto; aspect-ratio: auto;
            background-image: url('<?= $AVATAR_BASE ?>Godt%20g%C3%A5et%20image.png');
            background-size: contain; background-repeat: no-repeat; background-position: right center;
            opacity: .12;
            pointer-events: none;
            left: auto;
            min-width: 40%;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-top: 3rem;
        }
        .feature-card {
            background: var(--bg-mid);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem 1.75rem;
            text-decoration: none;
            display: block;
            text-align: center;
            transition: border-color .2s, box-shadow .2s, transform .2s;
        }
        .feature-card:hover {
            border-color: var(--border-bright);
            box-shadow: 0 0 28px rgba(124,58,237,.3);
            transform: translateY(-4px);
        }
        .feature-card-img {
            width: 72px; height: 72px; object-fit: contain;
            margin: 0 auto 1.25rem;
            display: block;
            filter: drop-shadow(0 0 12px rgba(124,58,237,.5));
        }
        .feature-card-tag {
            display: inline-block; padding: .2rem .6rem; border-radius: 5px;
            font-size: .7rem; font-weight: 800; letter-spacing: .07em;
            text-transform: uppercase; margin-bottom: .65rem;
        }
        .tag-quiz  { background: rgba(124,58,237,.2); color: #a78bfa; border: 1px solid rgba(124,58,237,.4); }
        .tag-cloze { background: rgba(6,182,212,.15);  color: #67e8f9; border: 1px solid rgba(6,182,212,.35); }
        .tag-boss  { background: rgba(249,115,22,.15); color: #fdba74; border: 1px solid rgba(249,115,22,.35); }
        .feature-card h3 { font-size: 1.2rem; font-weight: 800; color: #fff; margin-bottom: .5rem; }
        .feature-card p  { font-size: .9rem; color: var(--text-muted); line-height: 1.6; }
        .feature-card-arrow {
            display: inline-flex; align-items: center; gap: .4rem;
            margin-top: 1rem; font-size: .85rem; font-weight: 700;
            color: var(--primary); text-decoration: none;
        }

        /* ─── How it works ─── */
        .steps-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem; margin-top: 3rem; position: relative;
        }
        .steps-grid::before {
            content: '';
            position: absolute; top: calc(1.5rem + 28px); left: 12.5%; right: 12.5%;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--neon-blue));
            opacity: .3;
        }
        .step {
            text-align: center;
            padding: 1.5rem 1rem;
        }
        .step-num {
            width: 56px; height: 56px; border-radius: 50%;
            background: var(--surface); border: 2px solid var(--border-bright);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.2rem; font-weight: 900; color: var(--primary);
            box-shadow: 0 0 16px rgba(124,58,237,.3);
            margin: 0 auto 1rem;
        }
        .step h4 { font-size: .95rem; font-weight: 800; color: #fff; margin-bottom: .4rem; }
        .step p  { font-size: .82rem; color: var(--text-muted); line-height: 1.55; }

        /* ─── XP bar section ─── */
        .player-bar-section {
            background: linear-gradient(135deg, #0d0d2b 0%, #13132b 100%);
            border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);
            padding: 2.5rem 2rem;
        }
        .player-bar-inner {
            max-width: 1100px; margin: 0 auto;
            display: flex; align-items: center; gap: 3rem; flex-wrap: wrap;
        }
        .player-stat {
            display: flex; align-items: center; gap: .75rem;
        }
        .player-stat-icon { font-size: 1.8rem; filter: drop-shadow(0 0 8px var(--primary)); }
        .player-stat-val  { font-size: 1.5rem; font-weight: 900; color: #fff; line-height: 1; }
        .player-stat-lbl  { font-size: .75rem; color: var(--text-muted); margin-top: .1rem; }
        .player-xp-wrap { flex: 1; min-width: 200px; }
        .player-xp-label { display: flex; justify-content: space-between; font-size: .75rem; color: var(--text-muted); margin-bottom: .4rem; }
        .player-xp-bar   { height: 8px; background: rgba(255,255,255,.08); border-radius: 999px; overflow: hidden; border: 1px solid rgba(255,255,255,.06); }
        .player-xp-fill  { height: 100%; background: linear-gradient(90deg, var(--primary), var(--neon-blue)); border-radius: 999px; box-shadow: 0 0 8px var(--primary-glow); }

        /* ─── CTA section ─── */
        .cta-section {
            background: radial-gradient(ellipse at 50% 0%, rgba(124,58,237,.25) 0%, transparent 70%), var(--bg);
            text-align: center; padding: 6rem 2rem;
        }
        .cta-section .section-title { margin: 0 auto .75rem; }
        .cta-section .section-sub   { margin: 0 auto 2.5rem; }

        /* ─── Upload modal ─── */
        .upload-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.8); z-index: 500;
            align-items: center; justify-content: center;
        }
        .upload-overlay.open { display: flex; }
        .upload-modal {
            background: var(--surface); border-radius: var(--radius);
            padding: 2rem; max-width: 500px; width: 90%;
            box-shadow: 0 0 50px rgba(124,58,237,.5);
            border: 1px solid var(--primary);
        }
        .upload-modal h2 { margin-bottom: .4rem; }
        .upload-modal p.desc { color: var(--text-muted); font-size: .9rem; margin-bottom: 1.5rem; }
        .modal-close {
            float: right; background: none; border: none;
            color: var(--text-muted); font-size: 1.5rem; cursor: pointer;
            line-height: 1;
        }

        /* ─── Footer ─── */
        .lp-footer {
            background: var(--surface);
            border-top: 1px solid var(--border);
            padding: 2rem;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 1rem;
        }
        .lp-footer-links { display: flex; gap: 1.5rem; flex-wrap: wrap; }
        .lp-footer-link { color: var(--text-muted); font-size: .85rem; text-decoration: none; transition: color .15s; }
        .lp-footer-link:hover { color: var(--primary); }
        .lp-footer-copy { font-size: .8rem; color: var(--text-muted); }

        @media (max-width: 900px) {
            .features-grid { grid-template-columns: 1fr; }
            .steps-grid { grid-template-columns: 1fr 1fr; }
            .steps-grid::before { display: none; }
            .lp-nav { padding: 0 1rem; }
            .hero-content { padding: 3rem 1.25rem; }
        }
        @media (max-width: 560px) {
            .steps-grid { grid-template-columns: 1fr; }
            .lp-nav-links .lp-nav-link { display: none; }
        }
    </style>
</head>
<body>

<!-- ══════════ NAV ══════════ -->
<nav class="lp-nav">
    <a href="index.php" class="lp-nav-logo">
        <img src="<?= $LOGO_URL ?>" alt="ExamQuest">
    </a>
    <div class="lp-nav-links">
        <a href="dashboard.php" class="lp-nav-link lp-nav-icon-link">
            <img src="<?= $AVATAR_BASE ?>home%20ikon.png" class="lp-nav-icon" alt=""> Dashboard
        </a>
        <a href="gamification.php" class="lp-nav-link lp-nav-icon-link">
            <img src="<?= $AVATAR_BASE ?>bagdes%20ikon.png" class="lp-nav-icon" alt=""> Badges
        </a>
        <a href="dashboard.php?tab=stats" class="lp-nav-link lp-nav-icon-link">
            <img src="<?= $AVATAR_BASE ?>Statistik%20ikon.png" class="lp-nav-icon" alt=""> Statistik
        </a>
        <a href="profile.php" class="lp-nav-link" style="display:inline-flex;align-items:center;gap:.4rem;">
            <?php if ($navAvatarUrl): ?>
            <img src="<?= $navAvatarUrl ?>" class="lp-nav-avatar" alt="">
            <?php else: ?>🧑‍💻<?php endif; ?>
            <?php if (!empty($_SESSION['user_id'])): ?>
            <?= htmlspecialchars($_SESSION['username'] ?? 'Profil') ?>
            <?php else: ?>Profil<?php endif; ?>
        </a>
        <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="logout.php" class="lp-nav-link">Log ud</a>
        <?php else: ?>
        <button class="lp-nav-link" onclick="openAuthModal('login')"    style="cursor:pointer;background:none;border:none;font-family:inherit;">Log ind</button>
        <button class="lp-nav-cta"  onclick="openAuthModal('register')" style="cursor:pointer;border:none;font-family:inherit;">Opret konto</button>
        <?php endif; ?>
        <button class="lp-nav-cta" id="navUploadBtn"><img src="https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/upload%20ikon%201.png" style="width:20px;height:20px;object-fit:contain;vertical-align:middle;margin-right:.35rem;"> Upload</button>
    </div>
</nav>

<!-- ══════════ HERO ══════════ -->
<section class="hero" id="top">
    <div class="hero-bg"></div>
    <div class="hero-gradient"></div>

    <div class="hero-content">
        <div class="hero-eyebrow">✦ Eksamensforberedelse gjort sjovt</div>
        <h1 class="hero-title">
            Drop din rapport.<br>
            <span class="grad">Vi gør den<br>eksamen-klar.</span>
        </h1>
        <p class="hero-sub">
            Upload din PDF-rapport og få automatisk genererede quiz, cloze-opgaver og boss battles, der træner dig i at mestre dit pensum til den mundtlige eksamen.
        </p>
        <div class="hero-actions">
            <button class="btn-cta" id="heroUploadBtn"><img src="https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/upload%20ikon%201.png" style="width:20px;height:20px;object-fit:contain;vertical-align:middle;margin-right:.35rem;"> Upload</button>
            <a href="#features" class="btn-ghost">Se funktioner ↓</a>
        </div>
        <div class="hero-stats">
            <div>
                <div class="hero-stat-num">⚡ <?= number_format($xp) ?></div>
                <div class="hero-stat-label">XP optjent</div>
            </div>
            <div>
                <div class="hero-stat-num">Lvl <?= $level ?></div>
                <div class="hero-stat-label">Dit niveau</div>
            </div>
            <div>
                <div class="hero-stat-num"><span class="accent"><?= $streak ?></span> 🔥</div>
                <div class="hero-stat-label">Dages streak</div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════ PLAYER BAR ══════════ -->
<div class="player-bar-section">
    <div class="player-bar-inner">
        <div class="player-stat">
            <span class="player-stat-icon">⚡</span>
            <div>
                <div class="player-stat-val"><?= number_format($xp) ?> XP</div>
                <div class="player-stat-lbl">Samlet XP</div>
            </div>
        </div>
        <div class="player-stat">
            <span class="player-stat-icon">🏆</span>
            <div>
                <div class="player-stat-val">Level <?= $level ?></div>
                <div class="player-stat-lbl"><?= htmlspecialchars(LevelDefinitions::get($level)['title'] ?? 'Nybegynder') ?></div>
            </div>
        </div>
        <div class="player-stat">
            <span class="player-stat-icon">🔥</span>
            <div>
                <div class="player-stat-val"><?= $streak ?> dage</div>
                <div class="player-stat-lbl">Streak</div>
            </div>
        </div>
        <div class="player-xp-wrap">
            <div class="player-xp-label">
                <span>Fremgang mod niveau <?= $level + 1 ?></span>
                <span><?= $xpPct ?>%</span>
            </div>
            <div class="player-xp-bar">
                <div class="player-xp-fill" style="width:<?= $xpPct ?>%"></div>
            </div>
        </div>
        <a href="profile.php" class="btn-ghost" style="white-space:nowrap;">Se profil →</a>
    </div>
</div>

<!-- ══════════ FEATURES ══════════ -->
<section class="features-section" id="features">
    <div class="features-section-bg"></div>
    <div class="section-inner">
        <div class="section-eyebrow">Læringsaktiviteter</div>
        <h2 class="section-title">Tre måder at mestre dit pensum</h2>
        <p class="section-sub">Hver aktivitet er genereret direkte fra din rapport og tilpasset til at styrke præcist det, du skal op i.</p>

        <div class="features-grid">
            <!-- Quiz -->
            <a href="<?= $qUrl ?>" class="feature-card" <?= $noReport ? 'onclick="openUpload(event)"' : '' ?>>
                <img src="<?= $AVATAR_BASE ?>quiz%20ikon.png" class="feature-card-img" alt="Quiz">
                <div class="feature-card-tag tag-quiz">Quiz</div>
                <h3>Multiple-choice Quiz</h3>
                <p>Test din viden med spørgsmål baseret på rapportens kernebegreber. Optjen XP for hvert rigtigt svar.</p>
                <span class="feature-card-arrow">Start quiz →</span>
            </a>

            <!-- Cloze -->
            <a href="<?= $cUrl ?>" class="feature-card" <?= $noReport ? 'onclick="openUpload(event)"' : '' ?>>
                <img src="<?= $AVATAR_BASE ?>cloze%20mode%20ikon.png" class="feature-card-img" alt="Cloze">
                <div class="feature-card-tag tag-cloze">Cloze</div>
                <h3>Udfyldningsopgaver</h3>
                <p>Træn fagtermer og centrale begreber ved at udfylde de manglende ord i sætninger fra din rapport.</p>
                <span class="feature-card-arrow">Start cloze →</span>
            </a>

            <!-- Boss Battle -->
            <a href="<?= $bUrl ?>" class="feature-card" <?= $noReport ? 'onclick="openUpload(event)"' : '' ?>>
                <img src="<?= $AVATAR_BASE ?>boss%20battle%20ikon.png" class="feature-card-img" alt="Boss Battle">
                <div class="feature-card-tag tag-boss">Boss Battle</div>
                <h3>Boss Battle</h3>
                <p>Besvar åbne spørgsmål og bevis din dybdegående forståelse. Det sværeste niveau — til dem der vil ace eksamen.</p>
                <span class="feature-card-arrow">Kæmp mod bossen →</span>
            </a>
        </div>
    </div>
</section>

<!-- ══════════ HOW IT WORKS ══════════ -->
<section id="how">
    <div class="section-inner">
        <div class="section-eyebrow">Kom i gang</div>
        <h2 class="section-title">Fra rapport til eksamensparat på minutter</h2>
        <p class="section-sub">ExamQuest analyserer din rapport og bygger personlige læringsaktiviteter automatisk — ingen opsætning krævet.</p>

        <div class="steps-grid">
            <div class="step">
                <div class="step-num">1</div>
                <h4>Upload din rapport</h4>
                <p>Træk og slip din PDF-rapport. Vi understøtter alle standardformater.</p>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <h4>Analysen starter</h4>
                <p>Systemet identificerer kernebegreber, fagtermer og centrale pointer i rapporten.</p>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <h4>Vælg din aktivitet</h4>
                <p>Quiz, cloze eller boss battle — vælg den aktivitet der passer til dit niveau.</p>
            </div>
            <div class="step">
                <div class="step-num">4</div>
                <h4>Optjen XP &amp; level op</h4>
                <p>Lær ved at spille. Byg streak, optjen badges og se din fremgang i dashboard.</p>
            </div>
        </div>
    </div>
</section>

<!-- ══════════ CTA ══════════ -->
<section class="cta-section">
    <div class="section-inner">
        <div class="section-eyebrow" style="text-align:center;">Klar til eksamen?</div>
        <h2 class="section-title" style="text-align:center;">Start din læringsrejse nu</h2>
        <p class="section-sub" style="text-align:center;margin:0 auto 2.5rem;">
            Upload din rapport og gå fra usikker til eksamensparat — én aktivitet ad gangen.
        </p>
        <div style="display:flex;justify-content:center;gap:1rem;flex-wrap:wrap;">
            <button class="btn-cta" id="ctaUploadBtn"><img src="https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/upload%20ikon%201.png" style="width:20px;height:20px;object-fit:contain;vertical-align:middle;margin-right:.35rem;"> Upload</button>
            <?php if ($latestReportId): ?>
            <a href="<?= $qUrl ?>" class="btn-ghost">Fortsæt træning →</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ══════════ FOOTER ══════════ -->
<footer class="lp-footer">
    <img src="<?= $LOGO_URL ?>" alt="ExamQuest" style="height:36px;width:auto;opacity:.7;">
    <div class="lp-footer-links">
        <a href="dashboard.php"    class="lp-footer-link">📊 Dashboard</a>
        <a href="gamification.php" class="lp-footer-link">🏆 Badges</a>
        <a href="profile.php"      class="lp-footer-link">👤 Profil</a>
    </div>
    <span class="lp-footer-copy">© 2026 ExamQuest</span>
</footer>

<!-- ══════════ UPLOAD MODAL ══════════ -->
<div class="upload-overlay" id="uploadOverlay">
    <div class="upload-modal">
        <button class="modal-close" id="closeUpload">✕</button>
        <h2>📤 Upload</h2>
        <p class="desc">Vi analyserer din PDF og genererer quiz, cloze og boss battle automatisk.</p>

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

<?php require_once __DIR__ . '/auth_modal.php'; ?>
<script src="js/app.js"></script>
<script>
const overlay = document.getElementById('uploadOverlay');

function openUpload(e) {
    if (e) e.preventDefault();
    overlay.classList.add('open');
}

document.getElementById('heroUploadBtn').addEventListener('click', openUpload);
document.getElementById('navUploadBtn').addEventListener('click', openUpload);
document.getElementById('ctaUploadBtn').addEventListener('click', openUpload);
document.getElementById('closeUpload').addEventListener('click', () => overlay.classList.remove('open'));
overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        const target = document.querySelector(a.getAttribute('href'));
        if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
    });
});

// Darken nav on scroll
window.addEventListener('scroll', () => {
    document.querySelector('.lp-nav').style.background =
        window.scrollY > 40 ? 'rgba(10,10,26,.97)' : 'rgba(10,10,26,.85)';
});
</script>
</body>
</html>
