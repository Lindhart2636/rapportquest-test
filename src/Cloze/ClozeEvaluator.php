<?php

declare(strict_types=1);

namespace ExamQuest\Cloze;

/**
 * Evaluates a user's answer to a cloze question.
 *
 * Matching strategy (in order of strictness):
 *   1. Exact match (case-insensitive, trimmed)
 *   2. Normalised match — strip punctuation, collapse whitespace
 *   3. Prefix match — user typed at least 80% of the answer
 */
class ClozeEvaluator
{
    /**
     * @return array{correct: bool, score: int, feedback: string}
     */
    public function evaluate(string $userAnswer, string $correctAnswer, int $points): array
    {
        $userNorm    = $this->normalise($userAnswer);
        $correctNorm = $this->normalise($correctAnswer);

        if ($userNorm === '') {
            return ['correct' => false, 'score' => 0, 'feedback' => 'Du svarede ikke.'];
        }

        // 1. Exact (normalised) match
        if ($userNorm === $correctNorm) {
            return [
                'correct'  => true,
                'score'    => $points,
                'feedback' => '✅ Korrekt! +' . $points . ' point',
            ];
        }

        // 2. Check if correct answer contains user answer as a whole word (partial credit trigger)
        if (str_contains($correctNorm, $userNorm) && mb_strlen($userNorm) >= 3) {
            $ratio = mb_strlen($userNorm) / mb_strlen($correctNorm);
            if ($ratio >= 0.80) {
                $partial = (int) round($points * 0.5);
                return [
                    'correct'  => true,
                    'score'    => $partial,
                    'feedback' => '✅ Næsten! Halvt point (+' . $partial . '). Fuldt svar: ' . $correctAnswer,
                ];
            }
        }

        return [
            'correct'  => false,
            'score'    => 0,
            'feedback' => '❌ Forkert. Det korrekte svar er: ' . $correctAnswer,
        ];
    }

    private function normalise(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/[^\pL\pN\s]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text ?? '');
    }
}
