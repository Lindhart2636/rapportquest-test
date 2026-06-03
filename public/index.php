<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExamQuest — Upload din rapport</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header class="site-header">
            <div class="logo">
                <span class="logo-icon">📜</span>
                <h1>ExamQuest</h1>
            </div>
            <p class="tagline">Gør din rapport til et læringsforløb</p>
        </header>

        <main class="upload-section">
            <div class="upload-card">
                <h2>Upload din rapport</h2>
                <p class="upload-description">
                    Upload en PDF-rapport og ExamQuest genererer automatisk quiz-spørgsmål,
                    udfyldningsopgaver og en boss-battle ud fra indholdet.
                </p>

                <div id="message-area" class="message-area" role="alert" aria-live="polite"></div>

                <form
                    id="upload-form"
                    method="POST"
                    action="upload.php"
                    enctype="multipart/form-data"
                    novalidate
                >
                    <div class="drop-zone" id="drop-zone">
                        <span class="drop-icon">📄</span>
                        <p>Træk og slip din PDF her</p>
                        <p class="or-divider">— eller —</p>
                        <label for="pdf-file" class="file-label">
                            Vælg PDF-fil
                            <input
                                type="file"
                                id="pdf-file"
                                name="report"
                                accept=".pdf,application/pdf"
                                required
                                class="file-input"
                            >
                        </label>
                        <p id="file-name" class="file-name"></p>
                    </div>

                    <progress id="upload-progress" value="0" max="100" class="upload-progress"></progress>

                    <button type="submit" class="btn-submit" id="submit-btn">
                        Start læringsforløb
                    </button>
                </form>
            </div>

            <section class="features">
                <div class="feature">
                    <span class="feature-icon">🎯</span>
                    <h3>Quiz</h3>
                    <p>Multiple-choice spørgsmål baseret på rapportens kernebegreber</p>
                </div>
                <div class="feature">
                    <span class="feature-icon">✏️</span>
                    <h3>Udfyldning</h3>
                    <p>Cloze-opgaver der træner din forståelse af fagtermer</p>
                </div>
                <div class="feature">
                    <span class="feature-icon">⚔️</span>
                    <h3>Boss Battle</h3>
                    <p>Åbne spørgsmål der tester din dybdegående forståelse</p>
                </div>
            </section>
        </main>

        <footer class="site-footer">
            <p>
                <a href="dashboard.php" style="color:var(--primary);font-weight:600;text-decoration:none;">📊 Dashboard</a>
                &nbsp;·&nbsp;
                <a href="gamification.php" style="color:var(--primary);font-weight:600;text-decoration:none;">🏆 Gamification</a>
                &nbsp;·&nbsp;
                &copy; 2026 ExamQuest
            </p>
        </footer>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
