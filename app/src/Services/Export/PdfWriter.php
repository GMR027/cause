<?php

declare(strict_types=1);

namespace Cauce\Services\Export;

/**
 * Generador PDF mínimo (A4, texto, Helvetica/WinAnsi).
 *
 * No pretende ser un motor tipográfico: produce un resumen legible sin
 * dependencias externas. Los textos se convierten a Windows-1252 para que los
 * acentos españoles se vean correctamente.
 */
final class PdfWriter
{
    private const WIDTH = 595.28;
    private const HEIGHT = 841.89;

    /** @var list<string> */
    private array $pages = [];

    private string $current = '';

    private float $y;

    private float $margin = 48.0;

    public function __construct()
    {
        $this->y = self::HEIGHT - $this->margin;
    }

    public function title(string $text): void
    {
        $this->line($text, 18, true, 24);
        $this->spacer(4);
    }

    public function heading(string $text): void
    {
        $this->ensureSpace(24);
        $this->line($text, 13, true, 18);
        $this->spacer(2);
    }

    public function text(string $text, float $size = 10, bool $bold = false): void
    {
        foreach ($this->wrap($text, $size) as $line) {
            $this->line($line, $size, $bold, $size + 4);
        }
    }

    public function keyValue(string $key, string $value): void
    {
        $this->text($key . ': ' . $value, 10, false);
    }

    public function spacer(float $height = 6): void
    {
        $this->y -= $height;

        if ($this->y < $this->margin) {
            $this->newPage();
        }
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     * @param list<float> $widths
     */
    public function table(array $headers, array $rows, array $widths): void
    {
        $size = 8.5;
        $x = $this->margin;
        $positions = [];

        foreach ($widths as $width) {
            $positions[] = $x;
            $x += $width;
        }

        $this->ensureSpace(22);
        $this->drawRow($headers, $positions, $size, true);
        $this->current .= sprintf("0.75 w %.2F %.2F m %.2F %.2F l S\n", $this->margin, $this->y + 2, self::WIDTH - $this->margin, $this->y + 2);
        $this->y -= 2;

        foreach ($rows as $row) {
            if ($this->y < $this->margin + 24) {
                $this->newPage();
            }

            $this->drawRow($row, $positions, $size, false);
        }

        $this->spacer(6);
    }

    public function output(): string
    {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
            $this->current = '';
        }

        if ($this->pages === []) {
            $this->pages[] = '';
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $pageId = 5;

        foreach ($this->pages as $content) {
            $contentId = $pageId + 1;
            $kids[] = $pageId . ' 0 R';
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::WIDTH,
                self::HEIGHT,
                $contentId
            );
            $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
            $pageId += 2;
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($this->pages) . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($max + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xref . "\n%%EOF\n";

        return $pdf;
    }

    private function line(string $text, float $size, bool $bold, float $leading): void
    {
        $this->ensureSpace($leading);
        $font = $bold ? '/F2' : '/F1';
        $this->current .= sprintf(
            "BT %s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $font,
            $size,
            $this->margin,
            $this->y,
            $this->escape($text)
        );
        $this->y -= $leading;
    }

    private function ensureSpace(float $height): void
    {
        if ($this->y - $height < $this->margin) {
            $this->newPage();
        }
    }

    private function newPage(): void
    {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
        }

        $this->current = '';
        $this->y = self::HEIGHT - $this->margin;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, float $size): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $segments = explode("\n", $text);
        $lines = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                $lines[] = '';
                continue;
            }

            $maxChars = max(20, (int) floor((self::WIDTH - 2 * $this->margin) / (0.48 * $size)));
            $words = preg_split('/\s+/', $segment) ?: [];
            $line = '';

            foreach ($words as $word) {
                if ($line === '') {
                    $line = $word;
                    continue;
                }

                if (strlen($line) + 1 + strlen($word) <= $maxChars) {
                    $line .= ' ' . $word;
                    continue;
                }

                $lines[] = $line;
                $line = $word;

                while (strlen($line) > $maxChars) {
                    $lines[] = substr($line, 0, $maxChars);
                    $line = substr($line, $maxChars);
                }
            }

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param list<string> $cells
     * @param list<float> $positions
     */
    private function drawRow(array $cells, array $positions, float $size, bool $bold): void
    {
        $this->ensureSpace($size + 6);
        $font = $bold ? '/F2' : '/F1';

        foreach ($cells as $index => $cell) {
            $x = $positions[$index] ?? $this->margin;
            $max = ($positions[$index + 1] ?? (self::WIDTH - $this->margin)) - $x - 4;
            $maxChars = max(4, (int) floor($max / (0.5 * $size)));
            $text = $this->truncate((string) $cell, $maxChars);

            $this->current .= sprintf(
                "BT %s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
                $font,
                $size,
                $x,
                $this->y,
                $this->escape($text)
            );
        }

        $this->y -= $size + 4;
    }

    private function truncate(string $text, int $maxChars): string
    {
        if (strlen($text) <= $maxChars) {
            return $text;
        }

        return rtrim(substr($text, 0, max(1, $maxChars - 1))) . '…';
    }

    private function escape(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);

        if (!is_string($converted)) {
            $converted = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
    }
}
