<?php

// Renders the README images into docs/: a brand card and a stamped photo.
// The demo mark and photo are drawn here, so the repo carries no third-party images.

require __DIR__.'/../vendor/autoload.php';

use Pondake\ShareImage\Branding;
use Pondake\ShareImage\ShareImage;

$docs = dirname(__DIR__).'/docs';

/** A slate circle with a white star: stands in for your own mark. */
function demoMark(): string
{
    $s = 400;
    $image = imagecreatetruecolor($s, $s);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagealphablending($image, true);
    imagefilledellipse($image, 200, 200, 380, 380, imagecolorallocate($image, 71, 85, 105));

    $star = [];
    for ($i = 0; $i < 10; $i++) {
        $radius = $i % 2 ? 70 : 160;
        $angle = -M_PI / 2 + $i * M_PI / 5;
        $star[] = (int) (200 + $radius * cos($angle));
        $star[] = (int) (212 + $radius * sin($angle));
    }
    imagefilledpolygon($image, $star, imagecolorallocate($image, 255, 255, 255));

    $file = sys_get_temp_dir().'/share-image-demo-mark.png';
    imagepng($image, $file);

    return $file;
}

/** Evening sky over rolling hills. */
function demoPhoto(): string
{
    mt_srand(3);
    $image = imagecreatetruecolor(2400, 1600);

    for ($y = 0; $y < 1600; $y++) {
        $t = $y / 1600;
        imageline($image, 0, $y, 2399, $y, imagecolorallocate($image, (int) (250 - 90 * $t), (int) (170 - 60 * $t), (int) (120 + 60 * $t)));
    }

    imagefilledellipse($image, 1700, 820, 260, 260, imagecolorallocate($image, 255, 236, 200));

    foreach ([[900, [92, 110, 88]], [1100, [64, 84, 66]], [1300, [42, 58, 48]]] as $i => [$base, $rgb]) {
        $points = [];
        for ($x = 0; $x <= 2400; $x += 20) {
            $points[] = $x;
            $points[] = (int) ($base + 90 * sin($x / (300 + 90 * $i) + $i) + mt_rand(-4, 4));
        }
        array_push($points, 2400, 1600, 0, 1600);
        imagefilledpolygon($image, $points, imagecolorallocate($image, ...$rgb));
    }

    ob_start();
    imagejpeg($image, null, 92);

    return (string) ob_get_clean();
}

$mark = demoMark();
$images = new ShareImage(new Branding(mark: $mark, logo: $mark));

file_put_contents("$docs/photo.jpg", $images->fromPhoto(demoPhoto()));
file_put_contents("$docs/card.png", $images->card('The Northgate Community Arts and Crafts Centre', 'Library in Riverton'));
file_put_contents("$docs/card-short.png", $images->card('Hello, world'));

foreach (['photo.jpg', 'card.png', 'card-short.png'] as $file) {
    printf("%-15s %4d kB\n", $file, filesize("$docs/$file") / 1024);
}
