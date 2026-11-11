<?php

namespace Pondake\ShareImage\Tests;

use PHPUnit\Framework\TestCase;
use Pondake\ShareImage\Branding;
use Pondake\ShareImage\ShareImage;

final class ShareImageTest extends TestCase
{
    private static string $mark;

    private static string $logo;

    public static function setUpBeforeClass(): void
    {
        self::$mark = self::png(200, 240, [71, 85, 105]);
        self::$logo = self::png(600, 120, [44, 36, 33], border: 20);
    }

    public function test_photo_becomes_1200x630_jpeg_within_budget(): void
    {
        $jpeg = (new ShareImage(new Branding(mark: self::$mark)))->fromPhoto($this->noise(2560, 1700));

        $info = getimagesizefromstring($jpeg);
        $this->assertSame([1200, 630, 'image/jpeg'], [$info[0], $info[1], $info['mime']]);
        $this->assertLessThanOrEqual(300 * 1024, strlen($jpeg));
    }

    public function test_smaller_budget_gives_a_smaller_file(): void
    {
        $photo = $this->noise(2560, 1700);

        $this->assertLessThan(strlen((new ShareImage)->fromPhoto($photo)), strlen((new ShareImage(maxBytes: 150 * 1024))->fromPhoto($photo)));
    }

    public function test_mark_sits_bottom_centre_on_a_white_circle(): void
    {
        $image = imagecreatefromstring((new ShareImage(new Branding(mark: self::$mark)))->fromPhoto($this->solid(1600, 900, [0, 0, 0])));

        // Inside the white circle, left of the mark itself.
        $this->assertGreaterThan(230, imagecolorat($image, 545, 530) & 0xFF);
        // Corners stay untouched.
        $this->assertLessThan(20, imagecolorat($image, 10, 10) & 0xFF);
    }

    public function test_without_mark_nothing_is_stamped(): void
    {
        $image = imagecreatefromstring((new ShareImage)->fromPhoto($this->solid(1600, 900, [0, 0, 0])));

        $this->assertLessThan(20, imagecolorat($image, 545, 530) & 0xFF);
    }

    public function test_portrait_photo_is_cropped_from_the_middle(): void
    {
        // Red top, green middle, blue bottom: only green should remain.
        $photo = imagecreatetruecolor(600, 1800);
        imagefilledrectangle($photo, 0, 0, 599, 599, 0xFF0000);
        imagefilledrectangle($photo, 0, 600, 599, 1199, 0x00FF00);
        imagefilledrectangle($photo, 0, 1200, 599, 1799, 0x0000FF);

        $image = imagecreatefromstring((new ShareImage)->fromPhoto($this->encode($photo)));

        foreach ([[5, 5], [1195, 625]] as [$x, $y]) {
            [$r, $g, $b] = $this->rgb($image, $x, $y);
            $this->assertGreaterThan(200, $g);
            $this->assertLessThan(60, max($r, $b));
        }
    }

    public function test_unreadable_or_huge_input_returns_null(): void
    {
        $this->assertNull((new ShareImage)->fromPhoto('not an image'));
        $this->assertNull((new ShareImage(maxPixels: 1000))->fromPhoto($this->solid(100, 100, [0, 0, 0])));
    }

    public function test_card_is_1200x630_png_within_budget(): void
    {
        $png = (new ShareImage(new Branding(logo: self::$logo)))
            ->card('The Northgate Community Arts and Crafts Centre and Public Library', 'Library in Riverton');

        $info = getimagesizefromstring($png);
        $this->assertSame([1200, 630, 'image/png'], [$info[0], $info[1], $info['mime']]);
        $this->assertLessThanOrEqual(300 * 1024, strlen($png));
    }

    public function test_card_uses_brand_colours(): void
    {
        $card = imagecreatefromstring((new ShareImage(new Branding(background: [10, 20, 30], waves: [[200, 0, 0]])))->card('Hi'));

        $this->assertSame([10, 20, 30], $this->rgb($card, 1190, 10));
        $this->assertSame([200, 0, 0], $this->rgb($card, 600, 625));
    }

    public function test_card_handles_a_single_unbreakable_word(): void
    {
        $png = (new ShareImage)->card(str_repeat('Lighthouse', 40));

        $this->assertSame(1200, getimagesizefromstring($png)[0]);
    }

    /** @param array{int, int, int} $rgb */
    private static function png(int $width, int $height, array $rgb, int $border = 0): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, $border, $border, $width - 1 - $border, $height - 1 - $border, imagecolorallocate($image, ...$rgb));

        $file = tempnam(sys_get_temp_dir(), 'share-image').'.png';
        imagepng($image, $file);

        return $file;
    }

    /** Random blocks compress like a busy photo. */
    private function noise(int $width, int $height): string
    {
        mt_srand(7);
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y += 8) {
            for ($x = 0; $x < $width; $x += 8) {
                imagefilledrectangle($image, $x, $y, $x + 7, $y + 7, mt_rand(0, 0xFFFFFF));
            }
        }

        return $this->encode($image);
    }

    /** @param array{int, int, int} $rgb */
    private function solid(int $width, int $height, array $rgb): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));

        return $this->encode($image);
    }

    private function encode(\GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 95);

        return (string) ob_get_clean();
    }

    /** @return array{int, int, int} */
    private function rgb(\GdImage $image, int $x, int $y): array
    {
        $c = imagecolorat($image, $x, $y);

        return [$c >> 16 & 0xFF, $c >> 8 & 0xFF, $c & 0xFF];
    }
}
