<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use ExamQuest\Analysis\ReportAnalyser;
use ExamQuest\Gamification\BadgeManager;
use ExamQuest\Gamification\XpManager;
use ExamQuest\Gamification\StreakManager;

session_start();

$reportId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($reportId <= 0) {
    header('Location: index.php');
    exit;
}

// Load report record
try {
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare('SELECT id, original_name, file_size, status, created_at FROM reports WHERE id = :id');
    $stmt->execute([':id' => $reportId]);
    $report = $stmt->fetch();
} catch (PDOException $e) {
    $report = null;
}

if (!$report) {
    header('Location: index.php');
    exit;
}

$analysisResult = null;
$analysisError  = null;

$sessionId = session_id();

// Award first_upload badge and record streak on first visit
try {
    $xpManager     = new XpManager($pdo);
    $streakManager = new StreakManager($pdo);
    $badgeManager  = new BadgeManager($pdo);
    $xpManager->getProgress($sessionId); // ensure progress row exists
    $streakManager->recordActivity($sessionId);
    $badgeManager->checkAndAward($sessionId, ['uploaded' => true]);
} catch (Throwable) {}

// Run analysis if not already done
if (in_array($report['status'], ['pending', 'error'], true)) {
    try {
        $conceptsPath   = __DIR__ . '/../data/concepts.json';
        $analyser       = new ReportAnalyser($pdo, $conceptsPath);
        $analysisResult = $analyser->analyse($reportId);

        // Refresh report status
        $stmt = $pdo->prepare('SELECT status FROM reports WHERE id = :id');
        $stmt->execute([':id' => $reportId]);
        $report['status'] = $stmt->fetchColumn();

    } catch (Throwable $e) {
        $analysisError = $e->getMessage();
    }
} elseif ($report['status'] === 'ready') {
    // Load previously computed results
    try {
        $secStmt = $pdo->prepare('SELECT COUNT(*) FROM report_sections WHERE report_id = :id');
        $secStmt->execute([':id' => $reportId]);
        $sectionCount = (int) $secStmt->fetchColumn();

        $topStmt = $pdo->prepare(
            'SELECT c.term, c.category, c.weight
             FROM concepts c
             ORDER BY c.weight DESC
             LIMIT 10'
        );
        $topStmt->execute();
        $topConcepts = $topStmt->fetchAll();

        $analysisResult = [
            'report_id'      => $reportId,
            'sections'       => $sectionCount,
            'concepts_found' => count($topConcepts),
            'top_concepts'   => $topConcepts,
            'categories'     => [],
        ];
    } catch (PDOException $e) {
        $analysisError = 'Kunne ikke hente analyseresultater.';
    }
}

$fileSizeKB = round(($report['file_size'] ?? 0) / 1024, 1);
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExamQuest — Analyse</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .analysis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        .stat-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.25rem;
            text-align: center;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }
        .stat-label {
            color: var(--text-muted);
            font-size: .875rem;
            margin-top: .25rem;
        }
        .concepts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .concepts-table th,
        .concepts-table td {
            padding: .6rem .75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: .9rem;
        }
        .concepts-table th {
            background: var(--bg);
            font-weight: 600;
            color: var(--text-muted);
        }
        .score-bar {
            height: 8px;
            background: var(--primary);
            border-radius: 4px;
            min-width: 4px;
        }
        .badge-cat {
            display: inline-block;
            padding: .2rem .5rem;
            border-radius: 4px;
            font-size: .75rem;
            font-weight: 600;
            background: #ede9fe;
            color: var(--primary);
        }
        .alert-error {
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: .75rem 1rem;
            margin: 1rem 0;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        .btn-primary {
            flex: 1;
            padding: .75rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background .2s;
        }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary {
            flex: 1;
            padding: .75rem;
            background: var(--bg);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background .2s;
        }
        .btn-secondary:hover { background: var(--border); }
    </style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
    <div class="container">
        <header class="site-header">
            <div class="logo">
                <span class="logo-icon">📜</span>
                <h1>ExamQuest</h1>
            </div>
            <p class="tagline">Gør din rapport til et læringsforløb</p>
        </header>

        <main>
            <div class="upload-card">
                <h2>
                    <?php if ($report['status'] === 'ready'): ?>
                        ✅ Analyse fuldført
                    <?php elseif ($report['status'] === 'error'): ?>
                        ❌ Analysefejl
                    <?php else: ?>
                        ⏳ Analyserer…
                    <?php endif; ?>
                </h2>
                <p class="upload-description">
                    <strong><?= htmlspecialchars($report['original_name'], ENT_QUOTES) ?></strong>
                    (<?= $fileSizeKB ?> KB)
                </p>

                <?php if ($analysisError): ?>
                    <div class="alert-error">
                        <?= htmlspecialchars($analysisError, ENT_QUOTES) ?>
                    </div>
                <?php endif; ?>

                <?php if ($analysisResult): ?>
                    <div class="analysis-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?= $analysisResult['sections'] ?></div>
                            <div class="stat-label">Kapitler fundet</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $analysisResult['concepts_found'] ?></div>
                            <div class="stat-label">Fagbegreber identificeret</div>
                        </div>
                        <?php if (!empty($analysisResult['categories'])): ?>
                        <div class="stat-card">
                            <div class="stat-number"><?= count($analysisResult['categories']) ?></div>
                            <div class="stat-label">Fagkategorier</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($analysisResult['top_concepts'])): ?>
                    <h3 style="margin-bottom:.5rem;">Top fagbegreber</h3>
                    <table class="concepts-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Begreb</th>
                                <th>Kategori</th>
                                <th>Relevans</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analysisResult['top_concepts'] as $i => $concept): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($concept['term'], ENT_QUOTES) ?></td>
                                <td><span class="badge-cat"><?= htmlspecialchars($concept['category'], ENT_QUOTES) ?></span></td>
                                <td>
                                    <div class="score-bar" style="width:<?= isset($concept['score']) ? $concept['score'] : ($concept['weight'] * 10) ?>px"></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <?php if ($report['status'] === 'ready'): ?>
                    <div class="action-buttons">
                        <a href="quiz.php?id=<?= $reportId ?>" class="btn-primary">🎯 Start Quiz</a>
                        <a href="cloze.php?id=<?= $reportId ?>" class="btn-primary">✏️ Cloze Mode</a>
                        <a href="boss.php?id=<?= $reportId ?>" class="btn-primary">⚔️ Boss Battle</a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div style="margin-top:1.5rem;">
                    <a href="index.php" class="btn-secondary">← Upload ny rapport</a>
                </div>
            </div>
        </main>

        <footer class="site-footer">
            <p>&copy; 2026 ExamQuest</p>
        </footer>
    </div>
</body>
</html>
