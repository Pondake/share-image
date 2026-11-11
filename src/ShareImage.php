<?php

namespace Pondake\ShareImage;

/**
 * Link preview images (og:image) that also show up in WhatsApp.
 *
 * WhatsApp silently falls back to the favicon when an image is too heavy
 * (~600 kB) or points elsewhere, so every image is 1200x630 and kept under
 * a byte budget. Photos get the brand mark bottom centre: WhatsApp's square
 * thumbnail and Pinterest only show the middle.
 */
final class ShareImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** JPEG qualities to try, best first, until the image fits the budget. */
    private const QUALITIES = [84, 76, 68, 60, 52];

    /** GD draws no anti-aliased edges, so shapes are drawn at this scale and then shrunk. */
    private const SUPERSAMPLE = 2;

    private TextFitter $text;

    public function __construct(
        private Branding $branding = new Branding,
        private int $maxBytes = 300 * 1024,
        private int $maxPixels = 40_000_000,
    ) {
        $this->text = new TextFitter;
    }

    /**
     * A photo cropped to 1200x630 with the mark stamped on it, as JPEG.
     * Null when the bytes are no image or too large to decode safely.
     */
    public function fromPhoto(string $bytes): ?string
    {
        $info = @getimagesizefromstring($bytes);

        if (! $info || ! $this->fitsInMemory($info[0], $info[1])) {
            return null;
        }

        $photo = @imagecreatefromstring($bytes);

        if (! $photo) {
            return null;
        }

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $this->cover($canvas, $photo);
        unset($photo);

        if ($this->branding->mark) {
            $this->stamp($canvas, $this->branding->mark);
        }

        // A busy photo compresses badly; lower the quality until it fits.
        foreach (self::QUALITIES as $quality) {
            $jpeg = $this->encode(fn () => imagejpeg($canvas, null, $quality));

            if (strlen($jpeg) <= $this->maxBytes) {
                break;
            }
        }

        return $jpeg;
    }

    /**
     * A brand card for pages without a photo, as PNG: logo top left, the
     * title as large as it fits, an optional eyebrow line above it and
     * waves along the bottom.
     */
    public function card(string $title, ?string $eyebrow = null): string
    {
        $s = self::SUPERSAMPLE;
        $canvas = imagecreatetruecolor(self::WIDTH * $s, self::HEIGHT * $s);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, ...$this->branding->background));
        $this->waves($canvas, $s);

        if ($this->branding->logo) {
            $this->logo($canvas, $this->branding->logo, $s);
        }

        $titleFont = $this->branding->titleFont;
        $eyebrowFont = $this->branding->eyebrowFont;
        $maxWidth = 1040;
        [$size, $lines] = $this->text->fit($titleFont, $title, $maxWidth);
        $eyebrow = $eyebrow ? $this->text->ellipsis($eyebrowFont, 34, $eyebrow, $maxWidth) : null;

        // Block height from cap height to last baseline, centred between logo and waves.
        $lineHeight = $size * 1.12;
        $capHeight = $size * 0.7;
        $eyebrowHeight = $eyebrow ? 34 * 0.7 + 30 : 0;
        $block = $eyebrowHeight + $capHeight + (count($lines) - 1) * $lineHeight;
        $top = 150 + (520 - 150 - $block) / 2;

        if ($eyebrow) {
            $this->write($canvas, $eyebrowFont, 34, 80, $top + 34 * 0.7, $eyebrow, $this->branding->eyebrow, $s);
        }

        foreach ($lines as $i => $line) {
            $this->write($canvas, $titleFont, $size, 80, $top + $eyebrowHeight + $capHeight + $i * $lineHeight, $line, $this->branding->title, $s);
        }

        $card = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled($card, $canvas, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, self::WIDTH * $s, self::HEIGHT * $s);

        return $this->encode(fn () => imagepng($card, null, 9));
    }

    /** Decoding takes ~5 bytes per pixel; refuse what would exhaust memory_limit. */
    private function fitsInMemory(int $width, int $height): bool
    {
        if ($width * $height > $this->maxPixels) {
            return false;
        }

        $limit = ini_parse_quantity((string) ini_get('memory_limit'));

        return $limit <= 0 || memory_get_usage() + $width * $height * 6 < $limit * 0.9;
    }

    /** Fills the whole frame and crops the overflow equally on both sides. */
    private function cover(\GdImage $canvas, \GdImage $photo): void
    {
        $width = imagesx($photo);
        $height = imagesy($photo);
        $scale = max(self::WIDTH / $width, self::HEIGHT / $height);
        $cropWidth = (int) round(self::WIDTH / $scale);
        $cropHeight = (int) round(self::HEIGHT / $scale);

        imagecopyresampled(
            $canvas, $photo, 0, 0,
            intdiv($width - $cropWidth, 2), intdiv($height - $cropHeight, 2),
            self::WIDTH, self::HEIGHT, $cropWidth, $cropHeight,
        );
    }

    /** The mark on a white circle with a soft shadow, so it stands out on light photos too. */
    private function stamp(\GdImage $canvas, string $markFile): void
    {
        $mark = imagecreatefrompng($markFile);

        $scale = self::SUPERSAMPLE;
        $circle = 130 * $scale;
        $markHeight = 96 * $scale;
        $markWidth = (int) round(imagesx($mark) * $markHeight / imagesy($mark));

        $shadow = 12 * $scale;
        $size = $circle + 2 * $shadow;
        $center = intdiv($size, 2);
        $layer = imagecreatetruecolor($size, $size);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);
        imagefill($layer, 0, 0, imagecolorallocatealpha($layer, 0, 0, 0, 127));

        // Without blending each ring replaces the previous one instead of stacking.
        foreach ([[10, 120], [6, 116], [3, 112]] as [$grow, $alpha]) {
            $diameter = $circle + 2 * $grow * $scale;
            imagefilledellipse($layer, $center, $center + 3 * $scale, $diameter, $diameter, imagecolorallocatealpha($layer, 0, 30, 40, $alpha));
        }

        imagefilledellipse($layer, $center, $center, $circle, $circle, imagecolorallocatealpha($layer, 255, 255, 255, 0));
        imagealphablending($layer, true);
        imagecopyresampled($layer, $mark, $center - intdiv($markWidth, 2), $center - intdiv($markHeight, 2), 0, 0, $markWidth, $markHeight, imagesx($mark), imagesy($mark));

        $width = intdiv($size, $scale);
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $layer, intdiv(self::WIDTH - $width, 2), self::HEIGHT - 24 - $width, 0, 0, $width, $width, $size, $size);
    }

    private function logo(\GdImage $canvas, string $logoFile, int $s): void
    {
        $logo = imagecreatefrompng($logoFile);
        [$x, $y, $width, $height] = $this->bounds($logo);
        $logoHeight = 56 * $s;
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $logo, 80 * $s, 72 * $s, $x, $y, (int) round($width * $logoHeight / $height), $logoHeight, $width, $height);
    }

    /** @param array{int, int, int} $rgb */
    private function write(\GdImage $canvas, string $font, int $size, float $x, float $baseline, string $text, array $rgb, int $s): void
    {
        imagettftext($canvas, $size * 0.75 * $s, 0, (int) round($x * $s), (int) round($baseline * $s), imagecolorallocate($canvas, ...$rgb), $font, $text);
    }

    /** Sine waves along the bottom, each half a period shifted from the one behind it. */
    private function waves(\GdImage $canvas, int $s): void
    {
        $layers = $this->branding->waves;
        $count = count($layers);

        foreach ($layers as $i => $rgb) {
            $base = 574 - 26 * ($count - 1 - $i);
            $shift = 0.5 * (($count - 1 - $i) % 2 === 0 ? 1 : 0);
            $points = [];

            for ($x = 0; $x <= self::WIDTH; $x += 6) {
                $points[] = $x * $s;
                $points[] = (int) round(($base + 10 * sin(($x / 180 + $shift) * 2 * M_PI)) * $s);
            }

            array_push($points, self::WIDTH * $s, self::HEIGHT * $s, 0, self::HEIGHT * $s);
            imagefilledpolygon($canvas, $points, imagecolorallocate($canvas, ...$rgb));
        }
    }

    /**
     * The non-transparent part of an image, without its empty border.
     *
     * @return array{int, int, int, int}
     */
    private function bounds(\GdImage $image): array
    {
        $left = imagesx($image);
        $top = imagesy($image);
        $right = $bottom = 0;

        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                if ((imagecolorat($image, $x, $y) >> 24 & 0x7F) < 120) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }

        return $right < $left ? [0, 0, imagesx($image), imagesy($image)] : [$left, $top, $right - $left + 1, $bottom - $top + 1];
    }

    /** @param callable(): bool $draw */
    private function encode(callable $draw): string
    {
        ob_start();
        $draw();

        return (string) ob_get_clean();
    }
}
