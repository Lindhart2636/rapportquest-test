<?php

declare(strict_types=1);

namespace ExamQuest\Analysis;

/**
 * Computes a 0-100 relevance score for each matched concept.
 *
 * Formula:
 *   raw     = weight × log(1 + count)
 *   score   = round( raw / max_raw × 100 )
 */
class RelevanceScorer
{
    /**
     * @param  array<array{term:string,category:string,weight:int,count:int,sentences:string[]}> $matches
     * @return array<array{term:string,category:string,weight:int,count:int,score:int,sentences:string[]}>
     */
    public function score(array $matches): array
    {
        if (empty($matches)) {
            return [];
        }

        $raws = [];
        foreach ($matches as $m) {
            $raws[] = $m['weight'] * log(1 + $m['count']);
        }

        $maxRaw = max($raws);

        $scored = [];
        foreach ($matches as $i => $m) {
            $scored[] = array_merge($m, [
                'score' => $maxRaw > 0 ? (int) round($raws[$i] / $maxRaw * 100) : 0,
            ]);
        }

        // Re-sort by score descending
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return $scored;
    }
}
