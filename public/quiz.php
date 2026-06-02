<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use RapportQuest\Quiz\QuizGenerator;
use RapportQuest\Gamification\XpManager;

session_start();

$reportId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($reportId <= 0) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    die('Databaseforbindelse fejlede.');
}

// Verify report exists and is ready
$stmt = $pdo->prepare('SELECT id, original_name, status FROM reports WHERE id = :id');
$stmt->execute([':id' => $reportId]);
$report = $stmt->fetch();

if (!$report || $report['status'] !== 'ready') {
    header('Location: analyse.php?id=' . $reportId);
    exit;
}

// Generate quiz if needed
$quizStmt = $pdo->prepare('SELECT id, total_questions FROM quiz_sets WHERE report_id = :id LIMIT 1');
$quizStmt->execute([':id' => $reportId]);
$quizSet = $quizStmt->fetch();

if (!$quizSet) {
    try {
        $generator = new QuizGenerator($pdo);
        $quizSetId = $generator->generate($reportId);
        $quizStmt->execute([':id' => $reportId]);
        $quizSet = $quizStmt->fetch();
    } catch (\RuntimeException $e) {
        $genError = $e->getMessage();
    }
}

$sessionId = session_id();
$xpManager = new XpManager($pdo);
$progress  = $xpManager->getProgress($sessionId);

// Load questions
$questions = [];
if ($quizSet) {
    $qStmt = $pdo->prepare(
        'SELECT id, question_text, correct_answer, distractors, points
         FROM quiz_questions WHERE quiz_set_id = :qsid ORDER BY id ASC'
    );
    $qStmt->execute([':qsid' => $quizSet['id']]);
    $questions = $qStmt->fetchAll();

    // Build options arrays (shuffle correct + distractors)
    foreach ($questions as &$q) {
        $distractors = json_decode($q['distractors'], true) ?? [];
        $options = array_merge([$q['correct_answer']], $distractors);
        shuffle($options);
        $q['options'] = $options;
    }
    unset($q);
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RapportQuest — Quiz</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .quiz-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .xp-bar-wrap {
            background: var(--border);
            border-radius: 999px;
            height: 10px;
            width: 180px;
            overflow: hidden;
        }
        .xp-bar-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 999px;
            transition: width .4s ease;
        }
        .xp-info {
            font-size: .8rem;
            color: var(--text-muted);
            margin-top: .2rem;
            text-align: right;
        }
        .progress-text {
            font-weight: 600;
            color: var(--text-muted);
            font-size: .9rem;
        }
        /* Question card */
        .question-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 1.5rem;
        }
        .question-number {
            font-size: .8rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: .5rem;
        }
        .question-text {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            line-height: 1.5;
            white-space: pre-line;
        }
        .options-grid {
            display: grid;
            gap: .75rem;
        }
        .option-btn {
            width: 100%;
            padding: .85rem 1rem;
            background: var(--bg);
            border: 2px solid var(--border);
            border-radius: 8px;
            text-align: left;
            cursor: pointer;
            font-size: .95rem;
            font-family: inherit;
            transition: border-color .15s, background .15s;
            line-height: 1.4;
        }
        .option-btn:hover:not(:disabled) {
            border-color: var(--primary);
            background: #f5f3ff;
        }
        .option-btn.correct {
            border-color: var(--success);
            background: #f0fdf4;
            color: #15803d;
        }
        .option-btn.wrong {
            border-color: var(--danger);
            background: #fef2f2;
            color: var(--danger);
        }
        .option-btn.reveal {
            border-color: var(--success);
            background: #f0fdf4;
            color: #15803d;
        }
        .feedback-box {
            margin-top: 1rem;
            padding: .75rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            display: none;
        }
        .feedback-box.correct {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
            display: block;
        }
        .feedback-box.wrong {
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fecaca;
            display: block;
        }
        .next-btn {
            margin-top: 1rem;
            padding: .75rem 2rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: none;
        }
        .next-btn:hover { background: var(--primary-dark); }
        /* Results */
        .results-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            text-align: center;
            display: none;
        }
        .results-score {
            font-size: 4rem;
            font-weight: 800;
            color: var(--primary);
        }
        .results-label {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }
        .xp-gained {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
            margin: .5rem 0;
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
            text-align: center;
            text-decoration: none;
        }
        .btn-secondary:hover { background: var(--border); }
    </style>
</head>
<body>
<div class="container">
    <header class="site-header" style="margin-bottom:1rem;">
        <div class="logo">
            <span class="logo-icon">🎯</span>
            <h1>Quiz Mode</h1>
        </div>
        <p class="tagline"><?= htmlspecialchars($report['original_name'], ENT_QUOTES) ?></p>
    </header>

    <?php if (!empty($genError)): ?>
        <div class="upload-card">
            <div class="message-area error" style="display:block;">
                <?= htmlspecialchars($genError, ENT_QUOTES) ?>
            </div>
            <a href="analyse.php?id=<?= $reportId ?>" class="btn-secondary" style="display:inline-block;margin-top:1rem;">← Tilbage til analyse</a>
        </div>
    <?php elseif (empty($questions)): ?>
        <div class="upload-card">
            <p>Ingen spørgsmål fundet. Prøv at analysere rapporten igen.</p>
            <a href="analyse.php?id=<?= $reportId ?>" class="btn-secondary" style="display:inline-block;margin-top:1rem;">← Tilbage</a>
        </div>
    <?php else: ?>
    <div class="quiz-header">
        <span class="progress-text" id="progress-text">Spørgsmål 1 / <?= count($questions) ?></span>
        <div>
            <div class="xp-bar-wrap">
                <div class="xp-bar-fill" id="xp-bar" style="width:<?= min(100, ($progress['xp'] / max(1, $xpManager->nextLevelThreshold($progress['level']))) * 100) ?>%"></div>
            </div>
            <div class="xp-info" id="xp-info">Level <?= $progress['level'] ?> — <?= $progress['xp'] ?> XP</div>
        </div>
    </div>

    <!-- Question cards (hidden except current) -->
    <?php foreach ($questions as $idx => $q): ?>
    <div class="question-card" id="q-<?= $idx ?>" style="<?= $idx > 0 ? 'display:none;' : '' ?>">
        <div class="question-number">Spørgsmål <?= $idx + 1 ?> / <?= count($questions) ?> &nbsp;·&nbsp; <?= $q['points'] ?> point</div>
        <div class="question-text"><?= htmlspecialchars($q['question_text'], ENT_QUOTES) ?></div>
        <div class="options-grid">
            <?php foreach ($q['options'] as $oi => $option): ?>
            <button
                class="option-btn"
                data-question="<?= $idx ?>"
                data-correct="<?= htmlspecialchars($q['correct_answer'], ENT_QUOTES) ?>"
                data-points="<?= $q['points'] ?>"
                data-qid="<?= $q['id'] ?>"
                onclick="handleAnswer(this)"
            ><?= htmlspecialchars($option, ENT_QUOTES) ?></button>
            <?php endforeach; ?>
        </div>
        <div class="feedback-box" id="feedback-<?= $idx ?>"></div>
        <button class="next-btn" id="next-<?= $idx ?>" onclick="nextQuestion(<?= $idx ?>, <?= count($questions) ?>)">
            <?= $idx + 1 < count($questions) ? 'Næste spørgsmål →' : 'Se resultat' ?>
        </button>
    </div>
    <?php endforeach; ?>

    <!-- Results card -->
    <div class="results-card" id="results-card">
        <div style="font-size:3rem;margin-bottom:.5rem;">🏆</div>
        <div class="results-score" id="results-score">0</div>
        <div class="results-label">point optjent</div>
        <div class="xp-gained" id="results-xp">+0 XP</div>
        <p id="results-summary" style="color:var(--text-muted);margin-bottom:1rem;"></p>
        <div class="action-buttons">
            <a href="cloze.php?id=<?= $reportId ?>" class="btn-primary">✏️ Cloze Mode</a>
            <a href="boss.php?id=<?= $reportId ?>" class="btn-primary">⚔️ Boss Battle</a>
            <a href="quiz.php?id=<?= $reportId ?>" class="btn-secondary">🔄 Tag quizzen igen</a>
        </div>
    </div>

    <script>
    const REPORT_ID   = <?= $reportId ?>;
    const TOTAL_Q     = <?= count($questions) ?>;
    let answered      = 0;
    let totalScore    = 0;
    let correctCount  = 0;

    function handleAnswer(btn) {
        const qIdx      = parseInt(btn.dataset.question);
        const correct   = btn.dataset.correct;
        const points    = parseInt(btn.dataset.points);
        const card      = document.getElementById('q-' + qIdx);
        const feedback  = document.getElementById('feedback-' + qIdx);
        const nextBtn   = document.getElementById('next-' + qIdx);
        const allBtns   = card.querySelectorAll('.option-btn');

        // Disable all options
        allBtns.forEach(b => b.disabled = true);

        const isCorrect = btn.textContent.trim() === correct.trim();

        if (isCorrect) {
            btn.classList.add('correct');
            feedback.textContent = '✅ Korrekt! +' + points + ' point';
            feedback.className   = 'feedback-box correct';
            totalScore   += points;
            correctCount += 1;
        } else {
            btn.classList.add('wrong');
            // Reveal correct answer
            allBtns.forEach(b => {
                if (b.textContent.trim() === correct.trim()) b.classList.add('reveal');
            });
            feedback.textContent = '❌ Forkert. Det rigtige svar er markeret med grønt.';
            feedback.className   = 'feedback-box wrong';
        }

        answered++;
        nextBtn.style.display = 'inline-block';
    }

    function nextQuestion(current, total) {
        document.getElementById('q-' + current).style.display = 'none';
        const next = current + 1;
        if (next < total) {
            document.getElementById('q-' + next).style.display = 'block';
            document.getElementById('progress-text').textContent =
                'Spørgsmål ' + (next + 1) + ' / ' + total;
        } else {
            showResults();
        }
    }

    function showResults() {
        document.getElementById('results-score').textContent = totalScore;

        fetch('xp_update.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({report_id: REPORT_ID, xp: totalScore, source: 'quiz'})
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('results-xp').textContent = '+' + data.xp_gained + ' XP';
            document.getElementById('xp-bar').style.width = data.xp_pct + '%';
            document.getElementById('xp-info').textContent =
                'Level ' + data.level + ' — ' + data.xp + ' XP';
            if (data.levelled_up) {
                document.getElementById('results-xp').textContent +=
                    ' 🎉 Level op! Du er nu level ' + data.level + '!';
            }
        })
        .catch(() => {
            document.getElementById('results-xp').textContent = '+' + totalScore + ' XP';
        });

        const pct = TOTAL_Q > 0 ? Math.round((correctCount / TOTAL_Q) * 100) : 0;
        document.getElementById('results-summary').textContent =
            correctCount + ' ud af ' + TOTAL_Q + ' korrekte (' + pct + '%)';

        document.getElementById('results-card').style.display = 'block';
        window.scrollTo({top: 0, behavior: 'smooth'});
    }
    </script>
    <?php endif; ?>
</div>
</body>
</html>
