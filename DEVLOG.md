# DEVLOG

---

## 2026-06-02

## Milestone 1 — Projektopsætning

## Implementerede funktioner
- Mappestruktur oprettet (`public/`, `src/`, `config/`, `database/`, `data/`, `uploads/`)
- `composer.json` konfigureret med smalot/pdfparser
- Database schema oprettet (alle 10 tabeller)
- Upload side (`public/index.php`) implementeret
- Begrebsdatabase (`data/concepts.json`) oprettet med alle kategorier fra CLAUDE.md
- `.gitignore` konfigureret

## Oprettede filer
- `.gitignore`
- `composer.json`
- `config/database.php`
- `data/concepts.json`
- `database/schema.sql`
- `public/css/style.css`
- `public/index.php`
- `public/js/app.js`
- `src/Analysis/.gitkeep`
- `src/BossBattle/.gitkeep`
- `src/Cloze/.gitkeep`
- `src/Quiz/.gitkeep`
- `uploads/.gitkeep`
- `DEVLOG.md`

## Ændrede filer
- Ingen

## Databaseændringer
- Schema oprettet: `database/schema.sql`
  - Tabeller: reports, report_sections, concepts, quiz_sets, quiz_questions, cloze_sets, cloze_questions, boss_battles, progress, badges

## Kendte problemer
- Ingen

## Næste milestone
Milestone 2 — PDF Upload (afventer godkendelse)

---

## 2026-06-02

## Milestone 2 — PDF Upload

## Implementerede funktioner
- `upload.php` — server-side upload handler med komplet validering
- Filvalidering: MIME-type, magic bytes (`%PDF-`), extension, størrelse (max 20 MB), tom fil
- Upload-fejlkoder håndteres med brugervenlige beskeder
- Unik filnavngenerering med `random_bytes()`
- Databaseintegration: rapport gemmes i `reports`-tabellen med udløbsdato (+30 dage)
- Atomisk fejlhåndtering: fil slettes hvis DB-insert fejler
- `analyse.php` — bekræftelsesside efter succesfuld upload
- `app.js` opdateret: AJAX-upload med XHR, progressbar, timeout-håndtering
- Progressbar tilføjet til `index.php` og `style.css`

## Oprettede filer
- `public/upload.php`
- `public/analyse.php`

## Ændrede filer
- `public/index.php` (progressbar tilføjet)
- `public/js/app.js` (AJAX-upload, progressbar, fejlhåndtering)
- `public/css/style.css` (progressbar styling)

## Databaseændringer
- Ingen nye tabeller (brug af eksisterende `reports`-tabel fra Milestone 1)

## Kendte problemer
- Ingen

## Næste milestone
Milestone 3 — Rapportanalyse og Begrebsdatabase (afventer godkendelse)

---

## 2026-06-02

## Milestone 3 — Rapportanalyse og Begrebsdatabase

## Implementerede funktioner
- `PdfExtractor` — tekst-ekstraktion via smalot/pdfparser med OCR-fallback (Tesseract + ImageMagick)
- `TextNormalizer` — normalisering, tokenisering, sætningstokenisering, toLower
- `ChapterDetector` — genkender nummererede overskrifter, ALL-CAPS og kolon-overskrifter; klassificerer 15+ sektionstyper (indledning, metode, konklusion m.fl.)
- `ConceptMatcher` — whole-word matching mod begrebsdatabasen inkl. synonymer; returnerer fundne sætninger per begreb
- `RelevanceScorer` — scorer 0-100 via `weight × log(1 + count)` normaliseret til max
- `ReportAnalyser` — orkestrerer hele pipeline; gemmer sektioner og begreber i DB; sætter report-status
- `analyse.php` opdateret — viser analyseresultater med stat-kort, top-10 begrebstabel og knapper til Quiz/Cloze/Boss Battle
- Composer autoload opdateret (PSR-4: `RapportQuest\\`)

## Oprettede filer
- `src/Analysis/PdfExtractor.php`
- `src/Analysis/TextNormalizer.php`
- `src/Analysis/ChapterDetector.php`
- `src/Analysis/ConceptMatcher.php`
- `src/Analysis/RelevanceScorer.php`
- `src/Analysis/ReportAnalyser.php`

## Ændrede filer
- `public/analyse.php` (fuld analyse-visning)

## Databaseændringer
- Brug af `report_sections` (INSERT/DELETE ved re-analyse)
- Upsert på `concepts`-tabellen

## Kendte problemer
- OCR (Tesseract) ikke tilgængeligt i dette miljø — aktiveres automatisk når `tesseract` er i PATH

## Næste milestone
Milestone 4 — Quiz Mode (afventer godkendelse)

---

## 2026-06-02

## Milestone 4 — Quiz Mode

## Implementerede funktioner
- `QuizGenerator` — genererer op til 20 multiple-choice spørgsmål i 3 typer: definition, kategori og applikation
- Distraktorsvar vælges automatisk fra andre begreber i begrebsdatabasen
- Spørgsmål og svar gemmes i `quiz_sets` + `quiz_questions` tabellerne
- `XpManager` — håndterer XP og level-progression (10 level-tærskler)
- `quiz.php` — interaktiv quizside med feedback, progressbar, XP-display og resultatskærm
- `xp_update.php` — AJAX-endpoint til XP-opdatering efter quiz-gennemførelse
- Level op-besked vises ved nyt level

## Oprettede filer
- `src/Quiz/QuizGenerator.php`
- `src/Gamification/XpManager.php`
- `public/quiz.php`
- `public/xp_update.php`

## Ændrede filer
- Ingen

## Databaseændringer
- Brug af `quiz_sets` og `quiz_questions` (INSERT ved generering, DELETE ved regenerering)
- Brug af `progress` via XpManager (INSERT/UPDATE)

## Kendte problemer
- Ingen

## Næste milestone
Milestone 5 — Cloze Mode (afventer godkendelse)

---

## 2026-06-02

## Milestone 5 — Cloze Mode

## Implementerede funktioner
- `ClozeGenerator` — genererer fill-in-the-blank opgaver fra rapportens sektioner og begrebsdatabasen; foretrækker høj-vægt begreber og kontekstrige sætninger; maks ét spørgsmål per begreb
- `ClozeEvaluator` — 3-trins svarvalidering: eksakt match, normaliseret match, 80%-præfiksmatch (halvt point)
- `cloze.php` — interaktiv Cloze-side med tekstinput, hint-funktion (første bogstav + længde), Enter-tast support, feedback og XP-display
- `cloze_check.php` — AJAX-endpoint der evaluerer svar server-side via ClozeEvaluator
- Hint-system viser første bogstav og ordlængde uden at afsløre svaret
- XP-integration via eksisterende `xp_update.php`

## Oprettede filer
- `src/Cloze/ClozeGenerator.php`
- `src/Cloze/ClozeEvaluator.php`
- `public/cloze.php`
- `public/cloze_check.php`

## Ændrede filer
- Ingen

## Databaseændringer
- Brug af `cloze_sets` og `cloze_questions` (INSERT ved generering, DELETE ved regenerering)

## Kendte problemer
- Ingen

## Næste milestone
Milestone 6 — Boss Battle (afventer godkendelse)
