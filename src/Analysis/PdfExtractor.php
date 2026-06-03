<?php

declare(strict_types=1);

namespace ExamQuest\Analysis;

use Smalot\PdfParser\Parser;

/**
 * Extracts raw text from a PDF file.
 * Falls back to OCR via Tesseract if the PDF is image-based (no selectable text).
 */
class PdfExtractor
{
    private Parser $parser;
    private bool   $ocrAvailable;

    public function __construct()
    {
        $this->parser       = new Parser();
        $this->ocrAvailable = $this->detectTesseract();
    }

    public function extract(string $filePath): string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException("PDF-filen kan ikke læses: {$filePath}");
        }

        $text = $this->extractWithPdfParser($filePath);

        // If we got very little text the PDF is likely image-based — try OCR
        if ($this->isTextInsufficient($text) && $this->ocrAvailable) {
            $text = $this->extractWithOcr($filePath);
        }

        return $text;
    }

    private function extractWithPdfParser(string $filePath): string
    {
        try {
            $pdf  = $this->parser->parseFile($filePath);
            $text = $pdf->getText();
            return $text ?: '';
        } catch (\Exception $e) {
            return '';
        }
    }

    private function extractWithOcr(string $filePath): string
    {
        // Convert PDF pages to images then run Tesseract
        $outputBase = sys_get_temp_dir() . '/rq_ocr_' . bin2hex(random_bytes(8));

        // Use ImageMagick to convert PDF → PNG pages
        $convertCmd = sprintf(
            'convert -density 150 %s -quality 90 %s.png 2>/dev/null',
            escapeshellarg($filePath),
            escapeshellarg($outputBase)
        );
        exec($convertCmd);

        $pages = glob($outputBase . '*.png');
        if (empty($pages)) {
            return '';
        }

        $fullText = '';
        sort($pages);

        foreach ($pages as $page) {
            $ocrOut = $outputBase . '_out';
            $ocrCmd = sprintf(
                'tesseract %s %s -l dan+eng 2>/dev/null',
                escapeshellarg($page),
                escapeshellarg($ocrOut)
            );
            exec($ocrCmd);

            $txtFile = $ocrOut . '.txt';
            if (file_exists($txtFile)) {
                $fullText .= file_get_contents($txtFile) . "\n";
                unlink($txtFile);
            }
            unlink($page);
        }

        return $fullText;
    }

    private function isTextInsufficient(string $text): bool
    {
        return mb_strlen(trim($text)) < 100;
    }

    private function detectTesseract(): bool
    {
        exec('which tesseract 2>/dev/null', $out, $code);
        return $code === 0;
    }
}
