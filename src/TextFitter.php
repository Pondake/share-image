<?php

namespace Pondake\ShareImage;

/**
 * Picks the largest font size at which a title fits, and wraps it into lines.
 *
 * @internal
 */
final class TextFitter
{
    /** Large titles get fewer lines, so a short name reads like a headline. */
    private const SIZES = [120, 108, 100, 92, 84, 76, 68, 60];

    /**
     * One line above 100 px, two from 84 px, three below. If nothing fits,
     * the longest word sets the size and the third line is cut off.
     *
     * @return array{int, list<string>}
     */
    public function fit(string $font, string $title, int $maxWidth): array
    {
        foreach (self::SIZES as $size) {
            $lines = $this->wrap($font, $size, $title, $maxWidth);

            if ($lines && count($lines) <= match (true) {
                $size > 100 => 1, $size >= 84 => 2, default => 3
            }) {
                return [$size, $lines];
            }
        }

        // A very long word sets the size, but never below readable.
        $widest = max(array_map(fn ($word) => $this->width($font, 56, $word), $this->words($title)));
        $size = max(36, min(56, (int) floor(56 * $maxWidth / $widest)));
        $lines = $this->wrap($font, $size, $title, $maxWidth, force: true);

        if (count($lines) > 3) {
            $lines = [$lines[0], $lines[1], implode(' ', array_slice($lines, 2))];
        }

        return [$size, array_map(fn ($line) => $this->ellipsis($font, $size, $line, $maxWidth), $lines)];
    }

    /**
     * Words spread over lines; null when one word alone is too wide, unless forced.
     *
     * @return list<string>|null
     */
    public function wrap(string $font, int $size, string $text, int $maxWidth, bool $force = false): ?array
    {
        $lines = [];
        $line = '';

        foreach ($this->words($text) as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;

            if ($this->width($font, $size, $candidate) <= $maxWidth) {
                $line = $candidate;

                continue;
            }

            if (! $force && ($line === '' || $this->width($font, $size, $word) > $maxWidth)) {
                return null;
            }

            if ($line !== '') {
                $lines[] = $line;
            }
            $line = $word;
        }

        $lines[] = $line;

        return $lines;
    }

    public function ellipsis(string $font, int $size, string $text, int $maxWidth): string
    {
        if ($this->width($font, $size, $text) <= $maxWidth) {
            return $text;
        }

        while (mb_strlen($text) > 1 && $this->width($font, $size, rtrim($text).'…') > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text).'…';
    }

    /** Width in pixels; GD measures in points at 96 dpi. */
    public function width(string $font, int|float $size, string $text): float
    {
        $box = imagettfbbox($size * 0.75, 0, $font, $text);

        return $box[2] - $box[0];
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        return preg_split('/\s+/u', trim($text)) ?: [''];
    }
}
