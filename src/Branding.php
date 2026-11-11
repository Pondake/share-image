<?php

namespace Pondake\ShareImage;

/**
 * The look of every image: which logo files, fonts and colours to use.
 * Colours are [r, g, b] arrays.
 */
final readonly class Branding
{
    public string $titleFont;

    public string $eyebrowFont;

    /**
     * @param  string|null  $mark  Square-ish PNG stamped bottom centre on photos; null for no stamp.
     * @param  string|null  $logo  PNG drawn top left on brand cards; transparent edges are trimmed.
     * @param  array{int, int, int}  $background
     * @param  array{int, int, int}  $title
     * @param  array{int, int, int}  $eyebrow
     * @param  list<array{int, int, int}>  $waves  Back to front; an empty list draws no waves.
     */
    public function __construct(
        public ?string $mark = null,
        public ?string $logo = null,
        ?string $titleFont = null,
        ?string $eyebrowFont = null,
        public array $background = [248, 248, 246],
        public array $title = [24, 24, 27],
        public array $eyebrow = [79, 70, 229],
        public array $waves = [[226, 232, 240], [71, 85, 105]],
    ) {
        $this->titleFont = $titleFont ?? self::font('Figtree-Bold.ttf');
        $this->eyebrowFont = $eyebrowFont ?? self::font('Figtree-Medium.ttf');
    }

    /** The bundled Figtree fonts (SIL Open Font License). */
    public static function font(string $file): string
    {
        return dirname(__DIR__).'/resources/fonts/'.$file;
    }
}
