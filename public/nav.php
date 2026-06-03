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
    $_navAvatarFiles = [
        'robot-mascot'    => 'Robot%20mascot.png',
        'retro-robot'     => 'Retro%20futurist%20robot%20med%20neonstemning.png',
        'dyster-mage'     => 'Dyster%20mage.png',
        'deadline-1'      => 'Deadline%20herrens%201.png',
        'deadline-2'      => 'Deadline%20herrens%202.png',
        'karakter-raekke' => 'Karakterr%C3%A6kke%20med%20neon%20og%20detaljer.png',
        'hyggelig-studie' => 'Hyggelig%20studieaften.png',
    ];
    $_navAvatarUrl = 'https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/' . ($_navAvatarFiles[$_navAvatar] ?? '');
}
?>
<nav class="top-nav" aria-label="Hovednavigation">
    <a href="index.php" class="nav-brand">🎮 ExamQuest</a>
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
            <img src="<?= $_navAvatarUrl ?>" alt="Avatar" style="width:26px;height:26px;border-radius:50%;object-fit:cover;border:2px solid var(--primary);">
            <?php else: ?>🧑‍💻<?php endif; ?>
            Profil
        </a>
    </div>
</nav>
