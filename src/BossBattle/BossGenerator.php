<?php

declare(strict_types=1);

namespace ExamQuest\BossBattle;

use PDO;

/**
 * Generates open-ended exam-style questions from a report's sections and concepts.
 *
 * Question templates:
 *   - "Forklar hvad [term] er og hvordan det anvendes i rapporten."
 *   - "Beskriv den metode der kaldes [term] og hvad formålet er."
 *   - "Hvad er formålet med [term] og hvilke fordele giver det?"
 *   - "Hvordan adskiller [termA] sig fra [termB]?"
 *   - Section-based: "Hvad er rapportens konklusion?" / "Beskriv rapportens metode."
 */
class BossGenerator
{
    private const POINTS_PER_QUESTION = 50;
    private const MAX_QUESTIONS       = 10;
    private const MIN_CONCEPTS        = 3;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function generate(int $reportId): int
    {
        $concepts = $this->loadConcepts();
        $sections = $this->loadSections($reportId);

        if (count($concepts) < self::MIN_CONCEPTS) {
            throw new \RuntimeException(
                'For få fagbegreber til at generere Boss Battle (minimum ' . self::MIN_CONCEPTS . ').'
            );
        }

        $questions = array_merge(
            $this->buildConceptQuestions($concepts, $sections),
            $this->buildCompareQuestions($concepts),
            $this->buildSectionQuestions($sections)
        );

        if (empty($questions)) {
            throw new \RuntimeException('Kunne ikke generere Boss Battle-spørgsmål fra rapporten.');
        }

        shuffle($questions);
        $questions = array_slice($questions, 0, self::MAX_QUESTIONS);

        return $this->persist($reportId, $questions);
    }

    // ---------------------------------------------------------------
    // Question builders
    // ---------------------------------------------------------------

    private function buildConceptQuestions(array $concepts, array $sections): array
    {
        $questions = [];
        $templates = [
            'Forklar hvad %s er, og beskriv hvordan begrebet bruges i rapporten.',
            'Hvad er formålet med %s, og hvilken betydning har det i rapporten?',
            'Beskriv begrebet %s og giv et eksempel på dets anvendelse.',
        ];

        foreach (array_slice($concepts, 0, 12) as $i => $concept) {
            $term      = $concept['term'];
            $template  = $templates[$i % count($templates)];
            $question  = sprintf($template, $term);

            // Build model answer from sentences in report containing this term
            $modelAnswer = $this->extractModelAnswer($term, $sections);
            if ($modelAnswer === '') {
                $modelAnswer = 'Begrebet ' . $term . ' er et centralt fagbegreb inden for ' . $concept['category'] . '.';
            }

            // Keywords: term itself + related terms from same category
            $keywords = [$term];
            foreach ($concepts as $c) {
                if ($c['category'] === $concept['category'] && $c['term'] !== $term) {
                    $keywords[] = $c['term'];
                    if (count($keywords) >= 6) break;
                }
            }

            $questions[] = [
                'question_text' => $question,
                'model_answer'  => $modelAnswer,
                'keywords'      => $keywords,
                'points'        => self::POINTS_PER_QUESTION,
            ];
        }

        return $questions;
    }

    private function buildCompareQuestions(array $concepts): array
    {
        $questions = [];
        // Pair concepts from the same category
        $byCategory = [];
        foreach ($concepts as $c) {
            $byCategory[$c['category']][] = $c;
        }

        foreach ($byCategory as $category => $cats) {
            if (count($cats) < 2) continue;
            $a = $cats[0];
            $b = $cats[1];
            $questions[] = [
                'question_text' => sprintf(
                    'Hvad er forskellen og lighederne mellem %s og %s?',
                    $a['term'], $b['term']
                ),
                'model_answer'  => sprintf(
                    '%s og %s er begge begreber inden for %s. Forklar hvordan de adskiller sig i formål og anvendelse.',
                    $a['term'], $b['term'], $category
                ),
                'keywords'      => [$a['term'], $b['term'], $category],
                'points'        => self::POINTS_PER_QUESTION + 10,
            ];
            if (count($questions) >= 3) break;
        }

        return $questions;
    }

    private function buildSectionQuestions(array $sections): array
    {
        $questions  = [];
        $sectionMap = [
            'conclusion'      => ['Hvad er rapportens vigtigste konklusion, og hvilke anbefalinger gives der?', 8],
            'method'          => ['Beskriv den metode der er brugt i rapporten og begrund valget.', 7],
            'problem_statement' => ['Redegør for rapportens problemformulering og dens centrale undersøgelsesspørgsmål.', 7],
            'analysis'        => ['Hvad er de vigtigste fund i rapportens analyse?', 6],
            'introduction'    => ['Hvad er rapportens formål og baggrund ifølge indledningen?', 5],
        ];

        foreach ($sections as $section) {
            if (!isset($sectionMap[$section['section_type']])) continue;
            [$question, $weight] = $sectionMap[$section['section_type']];

            $content     = mb_substr(strip_tags($section['content']), 0, 500);
            $modelAnswer = $content ?: 'Se rapportens ' . $section['title'] . '-afsnit.';

            $questions[] = [
                'question_text' => $question,
                'model_answer'  => $modelAnswer,
                'keywords'      => $this->extractKeywords($section['content']),
                'points'        => self::POINTS_PER_QUESTION,
            ];
        }

        return $questions;
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function extractModelAnswer(string $term, array $sections): string
    {
        $lowerTerm = mb_strtolower($term, 'UTF-8');
        $best      = '';

        foreach ($sections as $section) {
            $sentences = $this->splitSentences($section['content']);
            foreach ($sentences as $s) {
                if (str_contains(mb_strtolower($s, 'UTF-8'), $lowerTerm)) {
                    $len = mb_strlen($s);
                    if ($len > mb_strlen($best) && $len <= 400) {
                        $best = trim($s);
                    }
                }
            }
        }

        return $best;
    }

    private function extractKeywords(string $text): array
    {
        // Simple frequency-based keyword extraction
        $words = preg_split('/\s+/', mb_strtolower($text, 'UTF-8')) ?: [];
        $freq  = [];
        $stop  = ['og', 'at', 'er', 'en', 'et', 'af', 'til', 'i', 'på', 'for', 'med',
                  'den', 'det', 'de', 'som', 'har', 'vil', 'kan', 'der', 'om', 'men',
                  'fra', 'ikke', 'var', 'han', 'hun', 'vi', 'the', 'of', 'and', 'to'];

        foreach ($words as $word) {
            $word = preg_replace('/[^\pL]/u', '', $word);
            if (mb_strlen($word) < 4 || in_array($word, $stop, true)) continue;
            $freq[$word] = ($freq[$word] ?? 0) + 1;
        }

        arsort($freq);
        return array_keys(array_slice($freq, 0, 8));
    }

    private function splitSentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-ZÆØÅ\d])/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($parts ?: [], fn($s) => mb_strlen(trim($s)) > 15);
    }

    // ---------------------------------------------------------------
    // Data loading
    // ---------------------------------------------------------------

    private function loadConcepts(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, term, category, weight FROM concepts ORDER BY weight DESC LIMIT 30'
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
        $del = $this->pdo->prepare('DELETE FROM boss_battles WHERE report_id = :id');
        $del->execute([':id' => $reportId]);

        $ins = $this->pdo->prepare(
            'INSERT INTO boss_battles (report_id, question_text, model_answer, keywords, points)
             VALUES (:report_id, :question_text, :model_answer, :keywords, :points)'
        );

        $firstId = null;
        foreach ($questions as $q) {
            $ins->execute([
                ':report_id'     => $reportId,
                ':question_text' => $q['question_text'],
                ':model_answer'  => $q['model_answer'],
                ':keywords'      => json_encode($q['keywords'], JSON_UNESCAPED_UNICODE),
                ':points'        => $q['points'],
            ]);
            if ($firstId === null) {
                $firstId = (int) $this->pdo->lastInsertId();
            }
        }

        return $firstId ?? 0;
    }
}
