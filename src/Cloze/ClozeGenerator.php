<?php

declare(strict_types=1);

namespace ExamQuest\Cloze;

use PDO;

/**
 * Generates fill-in-the-blank (cloze) tasks from a report's sections and matched concepts.
 *
 * Strategy:
 *   - Iterates report sections looking for sentences that contain a known concept term.
 *   - Blanks out the term with _____.
 *   - Prefers high-weight concepts and longer, context-rich sentences.
 *   - Avoids duplicates (same term only once per set).
 */
class ClozeGenerator
{
    private const POINTS_PER_QUESTION = 5;
    private const MAX_QUESTIONS       = 20;
    private const MIN_SENTENCE_LEN    = 25;
    private const MAX_SENTENCE_LEN    = 350;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Generate (or regenerate) a cloze set for the given report.
     * Returns the cloze_set id.
     */
    public function generate(int $reportId): int
    {
        $concepts = $this->loadConcepts();
        $sections = $this->loadSections($reportId);

        $questions = $this->buildQuestions($concepts, $sections);

        if (empty($questions)) {
            throw new \RuntimeException(
                'Ingen passende sætninger fundet til Cloze-opgaver. Rapporten kan være for kort.'
            );
        }

        // Limit and shuffle
        shuffle($questions);
        $questions = array_slice($questions, 0, self::MAX_QUESTIONS);

        return $this->persist($reportId, $questions);
    }

    // ---------------------------------------------------------------
    // Builder
    // ---------------------------------------------------------------

    private function buildQuestions(array $concepts, array $sections): array
    {
        $questions  = [];
        $usedTerms  = [];

        // Sort concepts by weight descending so best concepts are preferred
        usort($concepts, fn($a, $b) => $b['weight'] <=> $a['weight']);

        foreach ($concepts as $concept) {
            $term   = $concept['term'];
            $lowerT = mb_strtolower($term, 'UTF-8');

            if (in_array($lowerT, $usedTerms, true)) {
                continue;
            }

            // Also check synonyms — collect all variants
            $variants   = array_merge([$term], $concept['synonyms'] ?? []);
            $matchedAny = false;

            foreach ($variants as $variant) {
                $lowerV   = mb_strtolower($variant, 'UTF-8');
                $sentence = $this->findBestSentence($lowerV, $sections);

                if ($sentence === null) {
                    continue;
                }

                $blanked = $this->blankVariant($sentence, $variant);
                if ($blanked === $sentence) {
                    continue; // term not actually in sentence
                }

                $questions[] = [
                    'original_sentence' => $sentence,
                    'blanked_sentence'  => $blanked,
                    'answer'            => $variant,
                    'concept_id'        => $concept['id'],
                    'points'            => self::POINTS_PER_QUESTION,
                ];

                $usedTerms[] = $lowerT;
                $matchedAny  = true;
                break;
            }

            if (count($questions) >= self::MAX_QUESTIONS * 2) {
                break; // We have enough candidates
            }
        }

        return $questions;
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function findBestSentence(string $lowerTerm, array $sections): ?string
    {
        $candidates = [];

        foreach ($sections as $section) {
            if (empty(trim($section['content']))) {
                continue;
            }
            foreach ($this->splitSentences($section['content']) as $sentence) {
                $clean = trim($sentence);
                $len   = mb_strlen($clean);

                if ($len < self::MIN_SENTENCE_LEN || $len > self::MAX_SENTENCE_LEN) {
                    continue;
                }
                if (!str_contains(mb_strtolower($clean, 'UTF-8'), $lowerTerm)) {
                    continue;
                }
                // Score: prefer longer sentences (more context) but not too long
                $score        = min($len, 200);
                $candidates[] = ['sentence' => $clean, 'score' => $score];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
        return $candidates[0]['sentence'];
    }

    private function blankVariant(string $sentence, string $variant): string
    {
        $pattern = '/(?<![a-zæøå0-9])' . preg_quote($variant, '/') . '(?![a-zæøå0-9])/ui';
        $result  = preg_replace($pattern, '_____', $sentence, 1);
        return $result ?? $sentence;
    }

    private function splitSentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-ZÆØÅ\d])/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($parts ?: [], fn($s) => mb_strlen(trim($s)) > 10);
    }

    // ---------------------------------------------------------------
    // Data loading
    // ---------------------------------------------------------------

    private function loadConcepts(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, term, category, weight FROM concepts ORDER BY weight DESC LIMIT 60'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Attach empty synonyms (synonyms live in JSON file, not DB — good enough for matching)
        foreach ($rows as &$row) {
            $row['synonyms'] = [];
        }
        return $rows;
    }

    private function loadSections(int $reportId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT section_type, title, content FROM report_sections
             WHERE report_id = :id ORDER BY position ASC'
        );
        $stmt->execute([':id' => $reportId]);
        return $stmt->fetchAll();
    }

    // ---------------------------------------------------------------
    // Persistence
    // ---------------------------------------------------------------

    private function persist(int $reportId, array $questions): int
    {
        $del = $this->pdo->prepare('DELETE FROM cloze_sets WHERE report_id = :id');
        $del->execute([':id' => $reportId]);

        $ins = $this->pdo->prepare(
            'INSERT INTO cloze_sets (report_id, title) VALUES (:report_id, :title)'
        );
        $ins->execute([
            ':report_id' => $reportId,
            ':title'     => 'Cloze — Rapport #' . $reportId,
        ]);
        $clozeSetId = (int) $this->pdo->lastInsertId();

        $insQ = $this->pdo->prepare(
            'INSERT INTO cloze_questions
                (cloze_set_id, original_sentence, blanked_sentence, answer, concept_id, points)
             VALUES
                (:set_id, :original, :blanked, :answer, :concept_id, :points)'
        );

        foreach ($questions as $q) {
            $insQ->execute([
                ':set_id'     => $clozeSetId,
                ':original'   => $q['original_sentence'],
                ':blanked'    => $q['blanked_sentence'],
                ':answer'     => $q['answer'],
                ':concept_id' => $q['concept_id'],
                ':points'     => $q['points'],
            ]);
        }

        return $clozeSetId;
    }
}
