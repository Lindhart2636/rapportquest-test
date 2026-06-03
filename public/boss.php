<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use ExamQuest\BossBattle\BossGenerator;
use ExamQuest\Gamification\XpManager;

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

// Generate boss battle if needed
$bossStmt = $pdo->prepare(
    'SELECT id, question_text, model_answer, keywords, points
     FROM boss_battles WHERE report_id = :id ORDER BY id ASC'
);
$bossStmt->execute([':id' => $reportId]);
$questions = $bossStmt->fetchAll();

$genError = null;
if (empty($questions)) {
    try {
        $generator = new BossGenerator($pdo);
        $generator->generate($reportId);
        $bossStmt->execute([':id' => $reportId]);
        $questions = $bossStmt->fetchAll();
    } catch (\RuntimeException $e) {
        $genError = $e->getMessage();
    }
}

$sessionId = session_id();
$xpManager = new XpManager($pdo);
$progress  = $xpManager->getProgress($sessionId);

// Decode keywords JSON
foreach ($questions as &$q) {
    $q['keywords'] = json_decode($q['keywords'], true) ?? [];
}
unset($q);
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExamQuest — Boss Battle</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .boss-header {
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

        .boss-intro {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            color: #fff;
            border-radius: var(--radius);
            padding: 2rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .boss-intro h2 { font-size: 1.8rem; margin-bottom: .5rem; }
        .boss-intro p  { opacity: .8; }

        .boss-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 1.5rem;
        }
        .boss-number {
            font-size: .8rem;
            font-weight: 700;
            color: #7c3aed;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: .75rem;
        }
        .boss-question {
            font-size: 1.15rem;
            font-weight: 600;
            line-height: 1.6;
            margin-bottom: 1.25rem;
        }
        .boss-textarea {
            width: 100%;
            min-height: 140px;
            padding: .85rem 1rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: .95rem;
            font-family: inherit;
            line-height: 1.6;
            resize: vertical;
            transition: border-color .15s;
        }
        .boss-textarea:focus { outline: none; border-color: #7c3aed; }
        .char-counter { text-align: right; font-size: .8rem; color: var(--text-muted); margin-top: .25rem; }
        .submit-btn {
            margin-top: .75rem;
            padding: .75rem 2rem;
            background: #7c3aed;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s;
        }
        .submit-btn:hover:not(:disabled) { background: #6d28d9; }
        .submit-btn:disabled { background: var(--border); color: var(--text-muted); cursor: not-allowed; }

        .evaluation-box {
            margin-top: 1rem;
            padding: 1rem 1.25rem;
            border-radius: 8px;
            display: none;
        }
        .evaluation-box.great  { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .evaluation-box.good   { background: #eff6ff; border: 1px solid #bfdbfe; }
        .evaluation-box.ok     { background: #fffbeb; border: 1px solid #fde68a; }
        .evaluation-box.poor   { background: #fef2f2; border: 1px solid #fecaca; }

        .eval-score { font-size: 1.5rem; font-weight: 800; margin-bottom: .5rem; }
        .eval-feedback { font-size: .95rem; line-height: 1.6; margin-bottom: .75rem; }
        .keywords-wrap { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .5rem; }
        .kw-badge {
            padding: .2rem .5rem;
            border-radius: 4px;
            font-size: .75rem;
            font-weight: 600;
        }
        .kw-hit  { background: #dcfce7; color: #15803d; }
        .kw-miss { background: #fee2e2; color: #b91c1c; }

        .model-answer-toggle {
            background: none;
            border: none;
            color: var(--primary);
            font-size: .875rem;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
            margin-top: .5rem;
        }
        .model-answer-box {
            margin-top: .75rem;
            padding: .75rem 1rem;
            background: var(--bg);
            border-radius: 8px;
            border: 1px solid var(--border);
            font-size: .9rem;
            line-height: 1.6;
            display: none;
        }

        .next-btn {
            margin-top: .75rem;
            padding: .65rem 1.5rem;
            background: #7c3aed;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            display: none;
        }
        .next-btn:hover { background: #6d28d9; }

        /* Results */
        .results-card { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow); padding: 2rem; text-align: center; display: none; }
        .results-score { font-size: 4rem; font-weight: 800; color: #7c3aed; }
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
<?php include __DIR__ . '/nav.php'; ?>
<div class="container">
    <div class="boss-intro">
        <div style="font-size:3rem;margin-bottom:.5rem;">⚔️</div>
        <h2>Boss Battle</h2>
        <p>Besvar eksamenslignende spørgsmål med dine egne ord</p>
    </div>

    <?php if ($genError): ?>
        <div class="upload-card">
            <div class="message-area error" style="display:block;"><?= htmlspecialchars($genError, ENT_QUOTES) ?></div>
            <a href="analyse.php?id=<?= $reportId ?>" class="btn-secondary" style="display:inline-block;margin-top:1rem;">← Tilbage til analyse</a>
        </div>
    <?php elseif (empty($questions)): ?>
        <div class="upload-card">
            <p>Ingen spørgsmål fundet. Prøv at analysere rapporten igen.</p>
            <a href="analyse.php?id=<?= $reportId ?>" class="btn-secondary" style="display:inline-block;margin-top:1rem;">← Tilbage</a>
        </div>
    <?php else: ?>

    <div class="boss-header">
        <span class="progress-text" id="progress-text">Spørgsmål 1 / <?= count($questions) ?></span>
        <div>
            <div class="xp-bar-wrap">
                <div class="xp-bar-fill" id="xp-bar" style="width:<?= min(100, ($progress['xp'] / max(1, $xpManager->nextLevelThreshold($progress['level']))) * 100) ?>%"></div>
            </div>
            <div class="xp-info" id="xp-info">Level <?= $progress['level'] ?> — <?= $progress['xp'] ?> XP</div>
        </div>
    </div>

    <?php foreach ($questions as $idx => $q): ?>
    <div class="boss-card" id="boss-<?= $idx ?>" style="<?= $idx > 0 ? 'display:none;' : '' ?>">
        <div class="boss-number">⚔️ Spørgsmål <?= $idx + 1 ?> / <?= count($questions) ?> &nbsp;·&nbsp; <?= $q['points'] ?> point</div>
        <div class="boss-question"><?= htmlspecialchars($q['question_text'], ENT_QUOTES) ?></div>

        <textarea
            class="boss-textarea"
            id="answer-<?= $idx ?>"
            placeholder="Skriv dit svar her… Prøv at bruge fagbegreber og kom ind på konkrete eksempler fra rapporten."
            data-keywords="<?= htmlspecialchars(json_encode($q['keywords']), ENT_QUOTES) ?>"
            data-model="<?= htmlspecialchars($q['model_answer'], ENT_QUOTES) ?>"
            data-points="<?= $q['points'] ?>"
            oninput="updateCounter(<?= $idx ?>)"
        ></textarea>
        <div class="char-counter" id="counter-<?= $idx ?>">0 tegn</div>

        <button class="submit-btn" id="submit-<?= $idx ?>" onclick="submitAnswer(<?= $idx ?>)">
            Indsend svar
        </button>

        <div class="evaluation-box" id="eval-<?= $idx ?>">
            <div class="eval-score" id="eval-score-<?= $idx ?>"></div>
            <div class="eval-feedback" id="eval-feedback-<?= $idx ?>"></div>
            <div class="keywords-wrap" id="eval-kw-<?= $idx ?>"></div>
            <button class="model-answer-toggle" onclick="toggleModel(<?= $idx ?>)">Vis modelsvaret</button>
            <div class="model-answer-box" id="model-<?= $idx ?>">
                <?= htmlspecialchars($q['model_answer'], ENT_QUOTES) ?>
            </div>
        </div>

        <button class="next-btn" id="next-<?= $idx ?>" onclick="nextBoss(<?= $idx ?>, <?= count($questions) ?>)">
            <?= $idx + 1 < count($questions) ? 'Næste spørgsmål →' : 'Se resultat' ?>
        </button>
    </div>
    <?php endforeach; ?>

    <!-- Results -->
    <div class="results-card" id="results-card">
        <div style="font-size:3rem;margin-bottom:.5rem;">🏆</div>
        <div class="results-score" id="results-score">0</div>
        <div class="results-label">point optjent</div>
        <div class="xp-gained" id="results-xp">+0 XP</div>
        <p id="results-summary" style="color:var(--text-muted);margin-bottom:1rem;"></p>
        <div class="action-buttons">
            <a href="quiz.php?id=<?= $reportId ?>" class="btn-primary">🎯 Quiz Mode</a>
            <a href="cloze.php?id=<?= $reportId ?>" class="btn-primary">✏️ Cloze Mode</a>
            <a href="boss.php?id=<?= $reportId ?>" class="btn-secondary">🔄 Prøv igen</a>
        </div>
    </div>

    <script>
    const REPORT_ID  = <?= $reportId ?>;
    const TOTAL_Q    = <?= count($questions) ?>;
    let totalScore   = 0;
    let maxScore     = <?= array_sum(array_column($questions, 'points')) ?>;

    function updateCounter(idx) {
        const ta  = document.getElementById('answer-' + idx);
        const ctr = document.getElementById('counter-' + idx);
        ctr.textContent = ta.value.length + ' tegn';
    }

    function submitAnswer(idx) {
        const ta       = document.getElementById('answer-' + idx);
        const submitBtn = document.getElementById('submit-' + idx);
        const evalBox  = document.getElementById('eval-' + idx);
        const nextBtn  = document.getElementById('next-' + idx);
        const userAnswer = ta.value.trim();

        if (!userAnswer) {
            ta.style.borderColor = 'var(--danger)';
            return;
        }

        ta.disabled        = true;
        submitBtn.disabled = true;

        const keywords = JSON.parse(ta.dataset.keywords || '[]');
        const points   = parseInt(ta.dataset.points);

        fetch('boss_evaluate.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({answer: userAnswer, keywords: keywords, points: points})
        })
        .then(r => r.json())
        .then(data => showEvaluation(idx, data, points))
        .catch(() => {
            // Client-side fallback
            const hit = keywords.filter(kw =>
                userAnswer.toLowerCase().includes(kw.toLowerCase())
            );
            const pct = keywords.length > 0
                ? Math.round((hit.length / keywords.length) * 100)
                : 50;
            showEvaluation(idx, {
                score: Math.round(points * pct / 100),
                percentage: pct,
                feedback: pct >= 60 ? '✅ Godt svar!' : '📝 Prøv at inkludere flere fagbegreber.',
                keywords_hit: hit,
                keywords_missed: keywords.filter(kw => !hit.includes(kw))
            }, points);
        });
    }

    function showEvaluation(idx, data, points) {
        const evalBox  = document.getElementById('eval-' + idx);
        const nextBtn  = document.getElementById('next-' + idx);

        document.getElementById('eval-score-' + idx).textContent =
            data.score + ' / ' + points + ' point (' + data.percentage + '%)';

        document.getElementById('eval-feedback-' + idx).textContent = data.feedback;

        // Keyword badges
        const kwWrap = document.getElementById('eval-kw-' + idx);
        (data.keywords_hit || []).forEach(kw => {
            const span = document.createElement('span');
            span.className   = 'kw-badge kw-hit';
            span.textContent = '✓ ' + kw;
            kwWrap.appendChild(span);
        });
        (data.keywords_missed || []).forEach(kw => {
            const span = document.createElement('span');
            span.className   = 'kw-badge kw-miss';
            span.textContent = '✗ ' + kw;
            kwWrap.appendChild(span);
        });

        // Style eval box
        const pct = data.percentage;
        evalBox.className = 'evaluation-box ' +
            (pct >= 80 ? 'great' : pct >= 60 ? 'good' : pct >= 40 ? 'ok' : 'poor');
        evalBox.style.display = 'block';

        totalScore += data.score;
        nextBtn.style.display = 'inline-block';
    }

    function toggleModel(idx) {
        const box = document.getElementById('model-' + idx);
        box.style.display = box.style.display === 'none' || !box.style.display ? 'block' : 'none';
    }

    function nextBoss(current, total) {
        document.getElementById('boss-' + current).style.display = 'none';
        const next = current + 1;
        if (next < total) {
            document.getElementById('boss-' + next).style.display = 'block';
            document.getElementById('progress-text').textContent =
                'Spørgsmål ' + (next + 1) + ' / ' + total;
            window.scrollTo({top: 0, behavior: 'smooth'});
        } else {
            showResults();
        }
    }

    function showResults() {
        document.getElementById('results-score').textContent = totalScore;

        fetch('xp_update.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({report_id: REPORT_ID, xp: totalScore, source: 'boss'})
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

        const pct = maxScore > 0 ? Math.round((totalScore / maxScore) * 100) : 0;
        document.getElementById('results-summary').textContent =
            totalScore + ' ud af ' + maxScore + ' mulige point (' + pct + '%)';

        document.getElementById('results-card').style.display = 'block';
        window.scrollTo({top: 0, behavior: 'smooth'});
    }
    </script>
    <?php endif; ?>
</div>
</body>
</html>
