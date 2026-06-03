<?php

declare(strict_types=1);

namespace ExamQuest\Analysis;

/**
 * Splits a normalised text into labelled sections (chapters / sub-sections).
 */
class ChapterDetector
{
    // Patterns that indicate a chapter or section heading
    private const HEADING_PATTERNS = [
        // Numbered headings: "1.", "1.1", "2.3.4" — up to three levels
        '/^(\d{1,2}(?:\.\d{1,2}){0,2}\.?)\s+(.{3,80})$/m',
        // ALL-CAPS lines (min 3 words, max 10 words)
        '/^([A-ZÆØÅ][A-ZÆØÅ\s]{10,70})$/m',
        // Lines ending with a colon that are short
        '/^(.{3,60}):$/m',
    ];

    // Well-known section type keywords (Danish + English)
    private const SECTION_KEYWORDS = [
        'abstract'        => 'abstract',
        'resume'          => 'abstract',
        'indledning'      => 'introduction',
        'introduktion'    => 'introduction',
        'introduction'    => 'introduction',
        'baggrund'        => 'background',
        'background'      => 'background',
        'problemformulering' => 'problem_statement',
        'problemstilling' => 'problem_statement',
        'formål'          => 'purpose',
        'afgrænsning'     => 'delimitation',
        'metode'          => 'method',
        'methodology'     => 'method',
        'teori'           => 'theory',
        'theory'          => 'theory',
        'analyse'         => 'analysis',
        'analysis'        => 'analysis',
        'diskussion'      => 'discussion',
        'discussion'      => 'discussion',
        'konklusion'      => 'conclusion',
        'conclusion'      => 'conclusion',
        'perspektivering' => 'perspectives',
        'litteratur'      => 'references',
        'references'      => 'references',
        'referenceliste'  => 'references',
        'bibliography'    => 'references',
        'bilag'           => 'appendix',
        'appendix'        => 'appendix',
    ];

    /**
     * @return array<array{type: string, title: string, content: string, position: int}>
     */
    public function detect(string $normalizedText): array
    {
        $lines    = explode("\n", $normalizedText);
        $sections = [];
        $current  = ['type' => 'preamble', 'title' => 'Forside / Indledning', 'lines' => [], 'position' => 0];
        $position = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($this->isHeading($trimmed)) {
                // Save previous section
                if (!empty($current['lines'])) {
                    $sections[] = $this->finalise($current);
                }
                $position++;
                $current = [
                    'type'     => $this->classifyHeading($trimmed),
                    'title'    => $trimmed,
                    'lines'    => [],
                    'position' => $position,
                ];
            } else {
                $current['lines'][] = $line;
            }
        }

        // Last section
        if (!empty($current['lines'])) {
            $sections[] = $this->finalise($current);
        }

        return $sections;
    }

    private function isHeading(string $line): bool
    {
        if (mb_strlen($line) < 3 || mb_strlen($line) > 100) {
            return false;
        }
        foreach (self::HEADING_PATTERNS as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }
        return false;
    }

    private function classifyHeading(string $heading): string
    {
        $lower = mb_strtolower($heading, 'UTF-8');
        // Strip leading numbering
        $lower = preg_replace('/^\d[\d.]*\s*/', '', $lower);
        $lower = trim($lower);

        foreach (self::SECTION_KEYWORDS as $keyword => $type) {
            if (str_contains($lower, $keyword)) {
                return $type;
            }
        }
        return 'section';
    }

    private function finalise(array $current): array
    {
        return [
            'type'     => $current['type'],
            'title'    => $current['title'],
            'content'  => trim(implode("\n", $current['lines'])),
            'position' => $current['position'],
        ];
    }
}
