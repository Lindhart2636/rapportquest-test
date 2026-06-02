<?php

declare(strict_types=1);

namespace RapportQuest\Quiz;

use PDO;

/**
 * Generates a multiple-choice quiz from a report's analysed concepts and sections.
 *
 * Question types produced:
 *   - Definition  : "Hvad er [term]?"          — answer is a sentence containing the term
 *   - Category    : "Hvilken kategori tilhører [term]?"  — answer is the category
 *   - Application : "Hvilken metode/tilgang beskriver følgende? [sentence]"
 */
class QuizGenerator
{
    private const POINTS_PER_QUESTION = 10;
    private const MAX_QUESTIONS       = 20;
    private const MIN_CONCEPTS        = 3;
    private const DISTRACTORS_COUNT   = 3;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Generate (or regenerate) a quiz set for the given report.
     * Returns the quiz_set id.
     */
    public function generate(int $reportId): int
    {
        $concepts  = $this->loadConcepts($reportId);
        $sections  = $this->loadSections($reportId);

        if (count($concepts) < self::MIN_CONCEPTS) {
            throw new \RuntimeException(
                'For få fagbegreber fundet til at generere en quiz (minimum ' . self::MIN_CONCEPTS . ').'
            );
        }

        $questions = $this->buildQuestions($concepts, $sections);

        // Limit and shuffle
        shuffle($questions);
        $questions = array_slice($questions, 0, self::MAX_QUESTIONS);

        return $this->persist($reportId, $questions);
    }

    // ---------------------------------------------------------------
    // Question builders
    // ---------------------------------------------------------------

    private function buildQuestions(array $concepts, array $sections): array
    {
        $allTerms  = array_column($concepts, 'term');
        $questions = [];

        foreach ($concepts as $concept) {
            $term     = $concept['term'];
            $category = $concept['category'];

            // 1. Definition question — use a sentence from the report
            $sentence = $this->findSentenceForTerm($term, $sections);
            if ($sentence !== null) {
                $distractors = $this->pickDistractorTerms($term, $allTerms);
                $questions[] = [
                    'question_text'  => "Hvad er begrebet \"{$term}\"?",
                    'correct_answer' => $sentence,
                    'distractors'    => $distractors,
                    'concept_id'     => $concept['id'],
                    'points'         => self::POINTS_PER_QUESTION,
                    'type'           => 'definition',
                ];
            }

            // 2. Category question
            $categoryDistractors = $this->pickDistractorCategories($category, $concepts);
            if (count($categoryDistractors) >= self::DISTRACTORS_COUNT) {
                $questions[] = [
                    'question_text'  => "Hvilken fagkategori tilhører begrebet \"{$term}\"?",
                    'correct_answer' => $category,
                    'distractors'    => $categoryDistractors,
                    'concept_id'     => $concept['id'],
                    'points'         => self::POINTS_PER_QUESTION,
                    'type'           => 'category',
                ];
            }

            // 3. Application question — show sentence, ask for term
            if ($sentence !== null) {
                $blanked = $this->blankTerm($sentence, $term);
                if ($blanked !== $sentence) { // only if term was actually found in sentence
                    $distractors = $this->pickDistractorTerms($term, $allTerms);
                    $questions[] = [
                        'question_text'  => "Hvilket begreb mangler i følgende sætning?\n\"{$blanked}\"",
                        'correct_answer' => $term,
                        'distractors'    => $distractors,
                        'concept_id'     => $concept['id'],
                        'points'         => self::POINTS_PER_QUESTION + 5, // slightly harder
                        'type'           => 'application',
                    ];
                }
            }
        }

        return $questions;
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function findSentenceForTerm(string $term, array $sections): ?string
    {
        $lowerTerm = mb_strtolower($term, 'UTF-8');

        foreach ($sections as $section) {
            $sentences = $this->splitSentences($section['content']);
            foreach ($sentences as $sentence) {
                if (str_contains(mb_strtolower($sentence, 'UTF-8'), $lowerTerm)) {
                    $clean = trim($sentence);
                    if (mb_strlen($clean) >= 20 && mb_strlen($clean) <= 300) {
                        return $clean;
                    }
                }
            }
        }
        return null;
    }

    private function splitSentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-ZÆØÅ\d])/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($parts ?: [], fn($s) => mb_strlen(trim($s)) > 10);
    }

    private function blankTerm(string $sentence, string $term): string
    {
        $pattern = '/(?<![a-zæøå0-9])' . preg_quote($term, '/') . '(?![a-zæøå0-9])/ui';
        $result  = preg_replace($pattern, '_____', $sentence, 1);
        return $result ?? $sentence;
    }

    private function pickDistractorTerms(string $correctTerm, array $allTerms): array
    {
        $pool = array_filter($allTerms, fn($t) => $t !== $correctTerm);
        $pool = array_values($pool);
        shuffle($pool);
        return array_slice($pool, 0, self::DISTRACTORS_COUNT);
    }

    private function pickDistractorCategories(string $correctCategory, array $concepts): array
    {
        $categories = array_unique(array_column($concepts, 'category'));
        $pool       = array_filter($categories, fn($c) => $c !== $correctCategory);
        $pool       = array_values($pool);
        shuffle($pool);
        return array_slice($pool, 0, self::DISTRACTORS_COUNT);
    }

    // ---------------------------------------------------------------
    // Data loading
    // ---------------------------------------------------------------

    private function loadConcepts(int $reportId): array
    {
        // Load concepts matched to this report via report_sections content
        // We use the global concepts table (populated during analysis)
        $stmt = $this->pdo->prepare(
            'SELECT id, term, category, weight FROM concepts ORDER BY weight DESC LIMIT 50'
        );
        $stmt->execute();
        return $stmt->fetchAll();
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
        // Remove existing quiz for this report
        $del = $this->pdo->prepare('DELETE FROM quiz_sets WHERE report_id = :id');
        $del->execute([':id' => $reportId]);

        // Create quiz set
        $ins = $this->pdo->prepare(
            'INSERT INTO quiz_sets (report_id, title, total_questions)
             VALUES (:report_id, :title, :total)'
        );
        $ins->execute([
            ':report_id' => $reportId,
            ':title'     => 'Quiz — Rapport #' . $reportId,
            ':total'     => count($questions),
        ]);
        $quizSetId = (int) $this->pdo->lastInsertId();

        // Insert questions
        $insQ = $this->pdo->prepare(
            'INSERT INTO quiz_questions
                (quiz_set_id, question_text, correct_answer, distractors, concept_id, points)
             VALUES
                (:quiz_set_id, :question_text, :correct_answer, :distractors, :concept_id, :points)'
        );

        foreach ($questions as $q) {
            $insQ->execute([
                ':quiz_set_id'    => $quizSetId,
                ':question_text'  => $q['question_text'],
                ':correct_answer' => $q['correct_answer'],
                ':distractors'    => json_encode($q['distractors'], JSON_UNESCAPED_UNICODE),
                ':concept_id'     => $q['concept_id'],
                ':points'         => $q['points'],
            ]);
        }

        return $quizSetId;
    }
}
