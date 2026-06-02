<?php
/**
 * Shared top navigation bar.
 * Include after session_start() and DB connection.
 * Variables expected: $reportId (int, optional)
 */
$navReportId = isset($reportId) ? (int) $reportId : 0;
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<nav class="top-nav" aria-label="Hovednavigation">
    <a href="index.php" class="nav-brand">📜 RapportQuest</a>
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
    </div>
</nav>
