<?php
/**
 * Shared top navigation bar — uses same lp-nav style as landing page.
 * Include after session_start() and DB connection.
 * Variables expected: $reportId (int, optional)
 */
$navReportId = isset($reportId) ? (int) $reportId : 0;
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$_navAvatar = $_SESSION['avatar'] ?? '';
$_navAvatarUrl = '';
if ($_navAvatar) {
    if (preg_match('/^avatar-(\d+)$/', $_navAvatar, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 10) {
        $_navAvatarUrl = 'https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/Avatar%20' . $m[1] . '.png';
    }
}
$_navLoggedIn = !empty($_SESSION['user_id']);
$_navUsername = htmlspecialchars($_SESSION['username'] ?? '');
$_AVATAR_BASE = 'https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/';
$_LOGO_URL    = $_AVATAR_BASE . 'ExamQuest%20logo%20med%20futuristisk%20design.png';
?>
<style>
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
.lp-nav-link:hover,
.lp-nav-link.active { color: #fff; background: rgba(124,58,237,.15); border-color: rgba(124,58,237,.35); }
.lp-nav-icon-link { display: inline-flex; align-items: center; gap: .4rem; }
.lp-nav-icon { width: 18px; height: 18px; object-fit: contain; filter: brightness(.7); transition: filter .15s; }
.lp-nav-icon-link:hover .lp-nav-icon,
.lp-nav-icon-link.active .lp-nav-icon { filter: brightness(1.3); }
.lp-nav-cta {
    padding: .45rem 1.2rem; border-radius: 8px;
    background: var(--primary); color: #fff;
    font-size: .875rem; font-weight: 700;
    text-decoration: none; border: none; cursor: pointer;
    box-shadow: 0 0 16px rgba(124,58,237,.4);
    transition: box-shadow .2s, transform .15s; font-family: inherit;
    display: inline-flex; align-items: center; gap: .35rem;
}
.lp-nav-cta:hover { box-shadow: 0 0 28px rgba(124,58,237,.7); transform: translateY(-1px); }
.lp-nav-avatar {
    width: 30px; height: 30px; border-radius: 50%;
    object-fit: cover; border: 2px solid var(--primary);
}
body { padding-top: 76px; }
</style>

<nav class="lp-nav" aria-label="Hovednavigation">
    <a href="index.php" class="lp-nav-logo">
        <img src="<?= $_LOGO_URL ?>" alt="ExamQuest">
    </a>
    <div class="lp-nav-links">
        <?php
        $_quizHref  = $navReportId > 0 ? "quiz.php?id=$navReportId"      : 'index.php';
        $_clozeHref = $navReportId > 0 ? "cloze.php?id=$navReportId"     : 'index.php';
        $_bossHref  = $navReportId > 0 ? "boss.php?id=$navReportId"      : 'index.php';
        $_dashHref  = $navReportId > 0 ? "dashboard.php?id=$navReportId" : 'dashboard.php';
        ?>
        <a href="<?= $_dashHref ?>" class="lp-nav-link lp-nav-icon-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <img src="<?= $_AVATAR_BASE ?>home%20ikon.png" class="lp-nav-icon" alt=""> Dashboard
        </a>
        <a href="<?= $_quizHref ?>"  class="lp-nav-link lp-nav-icon-link <?= $currentPage === 'quiz.php'  ? 'active' : '' ?>">
            <img src="<?= $_AVATAR_BASE ?>quiz%20ikon.png" class="lp-nav-icon" alt=""> Quiz
        </a>
        <a href="<?= $_clozeHref ?>" class="lp-nav-link lp-nav-icon-link <?= $currentPage === 'cloze.php' ? 'active' : '' ?>">
            <img src="<?= $_AVATAR_BASE ?>cloze%20mode%20ikon.png" class="lp-nav-icon" alt=""> Cloze
        </a>
        <a href="<?= $_bossHref ?>"  class="lp-nav-link lp-nav-icon-link <?= $currentPage === 'boss.php'  ? 'active' : '' ?>">
            <img src="<?= $_AVATAR_BASE ?>boss%20battle%20ikon.png" class="lp-nav-icon" alt=""> Boss
        </a>
        <a href="gamification.php" class="lp-nav-link lp-nav-icon-link <?= $currentPage === 'gamification.php' ? 'active' : '' ?>">
            <img src="<?= $_AVATAR_BASE ?>bagdes%20ikon.png" class="lp-nav-icon" alt=""> Badges
        </a>
        <a href="dashboard.php?tab=stats" class="lp-nav-link lp-nav-icon-link">
            <img src="<?= $_AVATAR_BASE ?>Statistik%20ikon.png" class="lp-nav-icon" alt=""> Statistik
        </a>
        <a href="profile.php" class="lp-nav-link lp-nav-icon-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
            <?php if ($_navAvatarUrl): ?>
            <img src="<?= $_navAvatarUrl ?>" class="lp-nav-avatar" alt="Avatar">
            <?php else: ?>🧑‍💻<?php endif; ?>
            <?= $_navLoggedIn ? $_navUsername : 'Profil' ?>
        </a>
        <?php if ($_navLoggedIn): ?>
        <a href="logout.php" class="lp-nav-link" style="color:var(--text-muted);">Log ud</a>
        <?php else: ?>
        <button class="lp-nav-link" onclick="openAuthModal('login')" style="cursor:pointer;background:none;border:none;font-family:inherit;font-size:.875rem;">Log ind</button>
        <button class="lp-nav-cta" onclick="openAuthModal('register')" style="cursor:pointer;">Opret konto</button>
        <?php endif; ?>
        <a href="index.php" class="lp-nav-cta">
            <img src="<?= $_AVATAR_BASE ?>upload%20ikon%201.png" style="width:20px;height:20px;object-fit:contain;" alt=""> Upload
        </a>
    </div>
</nav>
<?php require_once __DIR__ . '/auth_modal.php'; ?>
