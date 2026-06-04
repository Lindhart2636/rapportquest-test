<?php
/**
 * Shared top navigation bar.
 * Include after session_start() and DB connection.
 * Variables expected: $reportId (int, optional)
 */
$navReportId = isset($reportId) ? (int) $reportId : 0;
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$_navAvatar = $_SESSION['avatar'] ?? '';
$_navAvatarUrl = '';
if ($_navAvatar) {
    // Avatar 1-10
    if (preg_match('/^avatar-(\d+)$/', $_navAvatar, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 10) {
        $_navAvatarUrl = 'https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/Avatar%20' . $m[1] . '.png';
    }
}
?>
<nav class="top-nav" aria-label="Hovednavigation">
    <a href="index.php" class="nav-brand">
        <img src="https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/ExamQuest%20logo%20med%20futuristisk%20design.png" alt="ExamQuest" style="height:28px;width:auto;vertical-align:middle;margin-right:.4rem;">
        ExamQuest
    </a>
    <div class="nav-links">
        <?php if ($navReportId > 0): ?>
        <a href="quiz.php?id=<?= $navReportId ?>"       class="nav-link <?= $currentPage === 'quiz.php'         ? 'active' : '' ?>">🎯 Quiz</a>
        <a href="cloze.php?id=<?= $navReportId ?>"      class="nav-link <?= $currentPage === 'cloze.php'        ? 'active' : '' ?>">✏️ Cloze</a>
        <a href="boss.php?id=<?= $navReportId ?>"       class="nav-link <?= $currentPage === 'boss.php'         ? 'active' : '' ?>">⚔️ Boss</a>
        <a href="dashboard.php?id=<?= $navReportId ?>"  class="nav-link <?= $currentPage === 'dashboard.php'    ? 'active' : '' ?>">📊 Dashboard</a>
        <?php else: ?>
        <a href="dashboard.php"  class="nav-link <?= $currentPage === 'dashboard.php'  ? 'active' : '' ?>">📊 Dashboard</a>
        <?php endif; ?>
        <a href="gamification.php" class="nav-link <?= $currentPage === 'gamification.php' ? 'active' : '' ?>">🏆</a>
        <a href="profile.php" class="nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>" style="display:inline-flex;align-items:center;gap:.4rem;">
            <?php if ($_navAvatarUrl): ?>
            <img id="navAvatarImg" src="<?= $_navAvatarUrl ?>" alt="Avatar" style="width:26px;height:26px;border-radius:50%;object-fit:cover;border:2px solid var(--primary);">
            <?php else: ?>🧑‍💻<?php endif; ?>
            Profil
        </a>
    </div>
</nav>
