<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use RapportQuest\Cloze\ClozeGenerator;
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

$stmt = $pdo->prepare('SELECT id, original_name, status FROM reports WHERE id = :id');
$stmt->execute([':id' => $reportId]);
$report = $stmt->fetch();

if (!$report || $report['status'] !== 'ready') {
    header('Location: analyse.php?id=' . $reportId);
    exit;
}

// Generate cloze set if needed
$clozeStmt = $pdo->prepare('SELECT id FROM cloze_sets WHERE report_id = :id LIMIT 1');
$clozeStmt->execute([':id' => $reportId]);
$clozeSet = $clozeStmt->fetch();

$genError = null;
if (!$clozeSet) {
    try {
        $generator  = new ClozeGenerator($pdo);
        $clozeSetId = $generator->generate($reportId);
        $clozeStmt->execute([':id' => $reportId]);
        $clozeSet = $clozeStmt->fetch();
    } catch (\RuntimeException $e) {
        $genError = $e->getMessage();
    }
}

$sessionId = session_id();
$xpManager = new XpManager($pdo);
$progress  = $xpManager->getProgress($sessionId);

$questions = [];
if ($clozeSet) {
    $qStmt = $pdo->prepare(
        'SELECT id, blanked_sentence, answer, points
         FROM cloze_questions WHERE cloze_set_id = :sid ORDER BY id ASC'
    );
    $qStmt->execute([':sid' => $clozeSet['id']]);
    $questions = $qStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RapportQuest — Cloze Mode</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .cloze-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .xp-bar-wrap { background: var(--border); border-radius: 999px; height: 10px; width: 180px; overflow: hidden; }
        .xp-bar-fill { height: 100%; background: var(--accent); border-radius: 999px; transition: width .4s ease; }
        .xp-info { font-size: .8rem; color: var(--text-muted); margin-top: .2rem; text-align: right; }
        .progress-text { font-weight: 600; color: var(--text-muted); font-size: .9rem; }

        .cloze-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 1.5rem;
        }
        .cloze-number {
            font-size: .8rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: .75rem;
        }
        .cloze-sentence {
            font-size: 1.1rem;
            line-height: 1.7;
            margin-bottom: 1.25rem;
            font-weight: 500;
        }
        .blank-highlight {
            color: var(--primary);
            font-weight: 700;
            font-style: italic;
        }
        .cloze-input-wrap {
            display: flex;
            gap: .75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .cloze-input {
            flex: 1;
            min-width: 200px;
            padding: .7rem 1rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color .15s;
        }
        .cloze-input:focus { outline: none; border-color: var(--primary); }
        .cloze-input.correct { border-color: var(--success); background: #f0fdf4; }
        .cloze-input.wrong   { border-color: var(--danger);  background: #fef2f2; }
        .check-btn {
            padding: .7rem 1.5rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s;
        }
        .check-btn:hover:not(:disabled) { background: var(--primary-dark); }
        .check-btn:disabled { background: var(--border); color: var(--text-muted); cursor: not-allowed; }
        .feedback-box { margin-top: .75rem; padding: .6rem .9rem; border-radius: 8px; font-weight: 500; display: none; }
        .feedback-box.correct { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; display: block; }
        .feedback-box.wrong   { background: #fef2f2; color: var(--danger); border: 1px solid #fecaca; display: block; }
        .next-btn {
            margin-top: .75rem;
            padding: .65rem 1.5rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            display: none;
        }
        .next-btn:hover { background: var(--primary-dark); }
        .hint-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: .85rem;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
        }
        .hint-btn:hover { color: var(--text); }

        /* Results */
        .results-card { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow); padding: 2rem; text-align: center; display: none; }
        .results-score { font-size: 4rem; font-weight: 800; color: var(--primary); }
        .results-label { color: var(--text-muted); margin-bottom: 1.5rem; }
        .xp-gained { font-size: 1.5rem; font-weight: 700; color: var(--accent); margin: .5rem 0; }
        .action-buttons { display: flex; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap; }
        .btn-primary { flex: 1; padding: .75rem; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; text-align: center; text-decoration: none; transition: background .2s; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary { flex: 1; padding: .75rem; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 8px; font-size: 1rem; font-weight: 600; text-align: center; text-decoration: none; }
        .btn-secondary:hover { background: var(--border); }
    </style>
</head>
<body>
<div class="container">
    <header class="site-header" style="margin-bottom:1rem;">
        <div class="logo">
            <span class="logo-icon">✏️</span>
            <h1>Cloze Mode</h1>
        </div>
        <p class="tagline"><?= htmlspecialchars($report['original_name'], ENT_QUOTES) ?></p>
    </header>

    <?php if ($genError): ?>
        <div class="upload-card">
            <div class="message-area error" style="display:block;"><?= htmlspecialchars($genError, ENT_QUOTES) ?></div>
            <a href="analyse.php?id=<?= $reportId ?>" style="display:inline-block;margin-top:1rem;" class="btn-secondary">← Tilbage til analyse</a>
        </div>
    <?php elseif (empty($questions)): ?>
        <div class="upload-card">
            <p>Ingen Cloze-opgaver fundet. Prøv at analysere rapporten igen.</p>
            <a href="analyse.php?id=<?= $reportId ?>" class="btn-secondary" style="display:inline-block;margin-top:1rem;">← Tilbage</a>
        </div>
    <?php else: ?>

    <div class="cloze-header">
        <span class="progress-text" id="progress-text">Opgave 1 / <?= count($questions) ?></span>
        <div>
            <div class="xp-bar-wrap">
                <div class="xp-bar-fill" id="xp-bar" style="width:<?= min(100, ($progress['xp'] / max(1, $xpManager->nextLevelThreshold($progress['level']))) * 100) ?>%"></div>
            </div>
            <div class="xp-info" id="xp-info">Level <?= $progress['level'] ?> — <?= $progress['xp'] ?> XP</div>
        </div>
    </div>

    <?php foreach ($questions as $idx => $q): ?>
    <?php
        // Split blanked sentence for display — highlight _____
        $displaySentence = htmlspecialchars($q['blanked_sentence'], ENT_QUOTES);
        $displaySentence = str_replace('_____', '<span class="blank-highlight">_____</span>', $displaySentence);
    ?>
    <div class="cloze-card" id="cloze-<?= $idx ?>" style="<?= $idx > 0 ? 'display:none;' : '' ?>">
        <div class="cloze-number">Opgave <?= $idx + 1 ?> / <?= count($questions) ?> &nbsp;·&nbsp; <?= $q['points'] ?> point</div>
        <div class="cloze-sentence"><?= $displaySentence ?></div>

        <div class="cloze-input-wrap">
            <input
                type="text"
                class="cloze-input"
                id="input-<?= $idx ?>"
                placeholder="Skriv det manglende begreb…"
                data-answer="<?= htmlspecialchars($q['answer'], ENT_QUOTES) ?>"
                data-points="<?= $q['points'] ?>"
                data-idx="<?= $idx ?>"
                autocomplete="off"
                spellcheck="false"
                onkeydown="if(event.key==='Enter') checkAnswer(<?= $idx ?>)"
            >
            <button class="check-btn" id="check-<?= $idx ?>" onclick="checkAnswer(<?= $idx ?>)">Tjek</button>
        </div>

        <div style="margin-top:.4rem;">
            <button class="hint-btn" onclick="showHint(<?= $idx ?>, '<?= htmlspecialchars($q['answer'], ENT_JS) ?>')">Vis hint</button>
        </div>

        <div class="feedback-box" id="feedback-<?= $idx ?>"></div>
        <button class="next-btn" id="next-<?= $idx ?>" onclick="nextCloze(<?= $idx ?>, <?= count($questions) ?>)">
            <?= $idx + 1 < count($questions) ? 'Næste opgave →' : 'Se resultat' ?>
        </button>
    </div>
    <?php endforeach; ?>

    <!-- Results -->
    <div class="results-card" id="results-card">
        <div style="font-size:3rem;margin-bottom:.5rem;">✏️</div>
        <div class="results-score" id="results-score">0</div>
        <div class="results-label">point optjent</div>
        <div class="xp-gained" id="results-xp">+0 XP</div>
        <p id="results-summary" style="color:var(--text-muted);margin-bottom:1rem;"></p>
        <div class="action-buttons">
            <a href="quiz.php?id=<?= $reportId ?>" class="btn-primary">🎯 Quiz Mode</a>
            <a href="boss.php?id=<?= $reportId ?>" class="btn-primary">⚔️ Boss Battle</a>
            <a href="cloze.php?id=<?= $reportId ?>" class="btn-secondary">🔄 Prøv igen</a>
        </div>
    </div>

    <script>
    const REPORT_ID  = <?= $reportId ?>;
    const TOTAL_Q    = <?= count($questions) ?>;
    let totalScore   = 0;
    let correctCount = 0;

    function checkAnswer(idx) {
        const input    = document.getElementById('input-' + idx);
        const checkBtn = document.getElementById('check-' + idx);
        const feedback = document.getElementById('feedback-' + idx);
        const nextBtn  = document.getElementById('next-' + idx);
        const answer   = input.dataset.answer;
        const points   = parseInt(input.dataset.points);
        const userVal  = input.value.trim();

        if (!userVal) { return; }

        input.disabled   = true;
        checkBtn.disabled = true;

        fetch('cloze_check.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({user_answer: userVal, correct_answer: answer, points: points})
        })
        .then(r => r.json())
        .then(data => {
            if (data.correct) {
                input.classList.add('correct');
                feedback.className   = 'feedback-box correct';
                totalScore  += data.score;
                correctCount += 1;
            } else {
                input.classList.add('wrong');
                feedback.className = 'feedback-box wrong';
            }
            feedback.textContent  = data.feedback;
            nextBtn.style.display = 'inline-block';
        })
        .catch(() => {
            // Fallback: simple client-side check
            const norm = s => s.toLowerCase().trim().replace(/[^\wæøå\s]/gi, '').replace(/\s+/g, ' ');
            const correct = norm(userVal) === norm(answer);
            if (correct) {
                input.classList.add('correct');
                feedback.className  = 'feedback-box correct';
                feedback.textContent = '✅ Korrekt! +' + points + ' point';
                totalScore  += points;
                correctCount += 1;
            } else {
                input.classList.add('wrong');
                feedback.className  = 'feedback-box wrong';
                feedback.textContent = '❌ Forkert. Korrekt svar: ' + answer;
            }
            nextBtn.style.display = 'inline-block';
        });
    }

    function showHint(idx, answer) {
        const input = document.getElementById('input-' + idx);
        if (input.disabled) return;
        // Show first letter + length
        const hint = answer[0] + '_'.repeat(answer.length - 1) + ' (' + answer.length + ' tegn)';
        const fb   = document.getElementById('feedback-' + idx);
        fb.className    = 'feedback-box wrong';
        fb.textContent  = '💡 Hint: ' + hint;
    }

    function nextCloze(current, total) {
        document.getElementById('cloze-' + current).style.display = 'none';
        const next = current + 1;
        if (next < total) {
            document.getElementById('cloze-' + next).style.display = 'block';
            document.getElementById('progress-text').textContent =
                'Opgave ' + (next + 1) + ' / ' + total;
            document.getElementById('input-' + next).focus();
        } else {
            showResults();
        }
    }

    function showResults() {
        document.getElementById('results-score').textContent = totalScore;

        fetch('xp_update.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({report_id: REPORT_ID, xp: totalScore, source: 'cloze'})
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
