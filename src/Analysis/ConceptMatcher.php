<?php

declare(strict_types=1);

namespace ExamQuest\Analysis;

/**
 * Matches concepts from the concept database against a text corpus.
 */
class ConceptMatcher
{
    /** @var array<array{term:string,category:string,weight:int,synonyms:string[]}> */
    private array $concepts;

    private TextNormalizer $normalizer;

    public function __construct(string $conceptsJsonPath, TextNormalizer $normalizer)
    {
        $json = file_get_contents($conceptsJsonPath);
        if ($json === false) {
            throw new \RuntimeException("Kan ikke læse begrebsdatabasen: {$conceptsJsonPath}");
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Ugyldig begrebsdatabase (JSON parse-fejl).');
        }
        $this->concepts   = $data;
        $this->normalizer = $normalizer;
    }

    /**
     * Find all concepts present in the text.
     *
     * @return array<array{term:string,category:string,weight:int,count:int,sentences:string[]}>
     */
    public function match(string $text): array
    {
        $lowerText = $this->normalizer->toLower($text);
        $sentences = $this->normalizer->sentences($text);
        $results   = [];

        foreach ($this->concepts as $concept) {
            $terms = array_merge([$concept['term']], $concept['synonyms'] ?? []);
            $count = 0;
            $matchedSentences = [];

            foreach ($terms as $term) {
                $lowerTerm = $this->normalizer->toLower($term);
                $termCount = $this->countOccurrences($lowerTerm, $lowerText);
                $count    += $termCount;

                if ($termCount > 0) {
                    foreach ($sentences as $sentence) {
                        if (str_contains($this->normalizer->toLower($sentence), $lowerTerm)) {
                            $matchedSentences[] = $sentence;
                        }
                    }
                }
            }

            if ($count > 0) {
                $results[] = [
                    'term'      => $concept['term'],
                    'category'  => $concept['category'],
                    'weight'    => (int) $concept['weight'],
                    'count'     => $count,
                    'sentences' => array_values(array_unique($matchedSentences)),
                ];
            }
        }

        // Sort by relevance: weight × count descending
        usort($results, fn($a, $b) =>
            ($b['weight'] * $b['count']) <=> ($a['weight'] * $a['count'])
        );

        return $results;
    }

    /**
     * Count whole-word occurrences of a term (case-insensitive).
     */
    private function countOccurrences(string $term, string $lowerText): int
    {
        // Escape special regex characters in the term
        $escaped = preg_quote($term, '/');
        // Use word boundary; for multi-word terms boundary is at start/end only
        $pattern = '/(?<![a-zæøå0-9])' . $escaped . '(?![a-zæøå0-9])/ui';
        preg_match_all($pattern, $lowerText, $matches);
        return count($matches[0]);
    }
}
