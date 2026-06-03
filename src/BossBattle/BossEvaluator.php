<?php

declare(strict_types=1);

namespace ExamQuest\BossBattle;

/**
 * Evaluates a free-text answer against a model answer using keyword matching and relevance scoring.
 *
 * Scoring:
 *   - Keyword coverage  : 60% of total points
 *   - Answer length     : 20% (rewards substantive answers)
 *   - Sentence quality  : 20% (rewards structured answers with multiple sentences)
 */
class BossEvaluator
{
    private const KEYWORD_WEIGHT  = 0.60;
    private const LENGTH_WEIGHT   = 0.20;
    private const QUALITY_WEIGHT  = 0.20;

    private const MIN_GOOD_LENGTH = 80;   // characters
    private const MAX_SCORED_LEN  = 500;

    /**
     * @param  string   $userAnswer
     * @param  string[] $keywords
     * @param  int      $maxPoints
     * @return array{score: int, percentage: int, feedback: string, keywords_hit: string[], keywords_missed: string[]}
     */
    public function evaluate(string $userAnswer, array $keywords, int $maxPoints): array
    {
        $userAnswer = trim($userAnswer);

        if (mb_strlen($userAnswer) < 5) {
            return [
                'score'         => 0,
                'percentage'    => 0,
                'feedback'      => 'Du svarede ikke eller svaret var for kort.',
                'keywords_hit'  => [],
                'keywords_missed' => $keywords,
            ];
        }

        $lowerAnswer = mb_strtolower($userAnswer, 'UTF-8');

        // 1. Keyword coverage
        $hit    = [];
        $missed = [];
        foreach ($keywords as $kw) {
            $lowerKw = mb_strtolower($kw, 'UTF-8');
            if (str_contains($lowerAnswer, $lowerKw)) {
                $hit[] = $kw;
            } else {
                $missed[] = $kw;
            }
        }

        $keywordRatio = count($keywords) > 0
            ? count($hit) / count($keywords)
            : 0.5;

        // 2. Length score
        $answerLen   = mb_strlen($userAnswer);
        $lengthRatio = min(1.0, $answerLen / self::MAX_SCORED_LEN);
        if ($answerLen >= self::MIN_GOOD_LENGTH) {
            $lengthRatio = max($lengthRatio, 0.5);
        }

        // 3. Quality score — number of distinct sentences
        $sentenceCount  = max(1, preg_match_all('/[.!?]/', $userAnswer));
        $qualityRatio   = min(1.0, $sentenceCount / 4);

        // Weighted total
        $rawScore   = (
            $keywordRatio * self::KEYWORD_WEIGHT +
            $lengthRatio  * self::LENGTH_WEIGHT  +
            $qualityRatio * self::QUALITY_WEIGHT
        );

        $score      = (int) round($rawScore * $maxPoints);
        $percentage = (int) round($rawScore * 100);

        $feedback = $this->buildFeedback($percentage, $hit, $missed, $answerLen);

        return [
            'score'           => $score,
            'percentage'      => $percentage,
            'feedback'        => $feedback,
            'keywords_hit'    => $hit,
            'keywords_missed' => $missed,
        ];
    }

    private function buildFeedback(int $pct, array $hit, array $missed, int $len): string
    {
        $lines = [];

        if ($pct >= 80) {
            $lines[] = '🏆 Fremragende svar! Du dækker emnet grundigt.';
        } elseif ($pct >= 60) {
            $lines[] = '✅ Godt svar! Du rammer de vigtigste punkter.';
        } elseif ($pct >= 40) {
            $lines[] = '📝 Acceptabelt svar, men der er plads til forbedring.';
        } else {
            $lines[] = '❌ Svaret mangler dybde. Gennemgå nøglebegreberne igen.';
        }

        if (!empty($hit)) {
            $lines[] = 'Nøglebegreber du nævnte: ' . implode(', ', $hit) . '.';
        }
        if (!empty($missed)) {
            $lines[] = 'Nøglebegreber du manglede: ' . implode(', ', $missed) . '.';
        }
        if ($len < self::MIN_GOOD_LENGTH) {
            $lines[] = 'Prøv at uddybe svaret med flere sætninger.';
        }

        return implode(' ', $lines);
    }
}
