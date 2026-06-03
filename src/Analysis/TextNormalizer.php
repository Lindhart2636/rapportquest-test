<?php

declare(strict_types=1);

namespace ExamQuest\Analysis;

/**
 * Normalises raw PDF text for consistent matching and analysis.
 */
class TextNormalizer
{
    public function normalize(string $text): string
    {
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove soft hyphens and ligatures common in PDFs
        $text = str_replace(["\u{00AD}", "\u{FB00}", "\u{FB01}", "\u{FB02}"], ['', 'ff', 'fi', 'fl'], $text);

        // Collapse multiple spaces on same line (preserve newlines)
        $text = preg_replace('/[^\S\n]+/', ' ', $text);

        // Remove lines that are just page numbers (one or two digits alone)
        $text = preg_replace('/^\s*\d{1,3}\s*$/m', '', $text);

        // Collapse 3+ consecutive blank lines into two
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    public function tokenize(string $text): array
    {
        // Split into words, keeping Danish characters
        preg_match_all('/[\pL\pN][\pL\pN\-]*/u', $text, $matches);
        return $matches[0];
    }

    public function sentences(string $text): array
    {
        // Split on sentence-ending punctuation followed by whitespace or newline
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-ZÆØÅ\d])/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(array_map('trim', $parts ?: []), fn($s) => mb_strlen($s) > 10));
    }

    public function toLower(string $text): string
    {
        return mb_strtolower($text, 'UTF-8');
    }
}
