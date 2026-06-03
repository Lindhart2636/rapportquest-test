<?php

declare(strict_types=1);

namespace ExamQuest\Analysis;

use PDO;

/**
 * Orchestrates the full analysis pipeline for a report.
 *
 * Pipeline:
 *   1. Extract text from PDF
 *   2. Normalise text
 *   3. Detect chapters / sections
 *   4. Match concepts
 *   5. Score relevance
 *   6. Persist results to DB
 */
class ReportAnalyser
{
    private PdfExtractor    $extractor;
    private TextNormalizer  $normalizer;
    private ChapterDetector $chapterDetector;
    private ConceptMatcher  $matcher;
    private RelevanceScorer $scorer;
    private PDO             $pdo;

    public function __construct(PDO $pdo, string $conceptsJsonPath)
    {
        $this->pdo             = $pdo;
        $this->extractor       = new PdfExtractor();
        $this->normalizer      = new TextNormalizer();
        $this->chapterDetector = new ChapterDetector();
        $this->matcher         = new ConceptMatcher($conceptsJsonPath, $this->normalizer);
        $this->scorer          = new RelevanceScorer();
    }

    /**
     * Analyses a report and returns a summary.
     *
     * @return array{
     *   report_id: int,
     *   sections: int,
     *   concepts_found: int,
     *   top_concepts: array,
     *   categories: array
     * }
     */
    public function analyse(int $reportId): array
    {
        // 1. Load report record
        $stmt = $this->pdo->prepare('SELECT file_path, status FROM reports WHERE id = :id');
        $stmt->execute([':id' => $reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            throw new \RuntimeException("Rapport #{$reportId} ikke fundet.");
        }

        // Mark as processing
        $this->updateStatus($reportId, 'processing');

        try {
            // 2. Extract text
            $rawText = $this->extractor->extract($report['file_path']);

            if (mb_strlen(trim($rawText)) < 50) {
                throw new \RuntimeException('Kunne ikke udtrække tekst fra PDF-filen.');
            }

            // 3. Normalise
            $normalizedText = $this->normalizer->normalize($rawText);

            // 4. Detect sections
            $sections = $this->chapterDetector->detect($normalizedText);

            // 5. Match concepts
            $matches = $this->matcher->match($normalizedText);

            // 6. Score
            $scored = $this->scorer->score($matches);

            // 7. Persist
            $this->persistSections($reportId, $sections);
            $this->persistConcepts($scored);

            // Mark as ready
            $this->updateStatus($reportId, 'ready');

            // Build summary
            $categories = [];
            foreach ($scored as $c) {
                $cat = $c['category'];
                $categories[$cat] = ($categories[$cat] ?? 0) + 1;
            }
            arsort($categories);

            return [
                'report_id'      => $reportId,
                'sections'       => count($sections),
                'concepts_found' => count($scored),
                'top_concepts'   => array_slice($scored, 0, 10),
                'categories'     => $categories,
            ];

        } catch (\Throwable $e) {
            $this->updateStatus($reportId, 'error');
            throw $e;
        }
    }

    private function updateStatus(int $reportId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE reports SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $reportId]);
    }

    private function persistSections(int $reportId, array $sections): void
    {
        // Remove old sections if re-analysing
        $del = $this->pdo->prepare('DELETE FROM report_sections WHERE report_id = :id');
        $del->execute([':id' => $reportId]);

        $ins = $this->pdo->prepare(
            'INSERT INTO report_sections (report_id, section_type, title, content, position)
             VALUES (:report_id, :type, :title, :content, :position)'
        );

        foreach ($sections as $s) {
            $ins->execute([
                ':report_id' => $reportId,
                ':type'      => $s['type'],
                ':title'     => mb_substr($s['title'], 0, 255),
                ':content'   => $s['content'],
                ':position'  => $s['position'],
            ]);
        }
    }

    private function persistConcepts(array $scored): void
    {
        // Upsert concepts (they are shared across reports)
        $upsert = $this->pdo->prepare(
            'INSERT INTO concepts (term, category, weight, synonyms)
             VALUES (:term, :category, :weight, :synonyms)
             ON DUPLICATE KEY UPDATE category = VALUES(category), weight = VALUES(weight)'
        );

        foreach ($scored as $c) {
            $upsert->execute([
                ':term'     => $c['term'],
                ':category' => $c['category'],
                ':weight'   => $c['weight'],
                ':synonyms' => json_encode([]),
            ]);
        }
    }
}
