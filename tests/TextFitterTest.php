<?php

namespace Pondake\ShareImage\Tests;

use PHPUnit\Framework\TestCase;
use Pondake\ShareImage\Branding;
use Pondake\ShareImage\TextFitter;

final class TextFitterTest extends TestCase
{
    private string $font;

    protected function setUp(): void
    {
        $this->font = Branding::font('Figtree-Bold.ttf');
    }

    public function test_short_title_gets_the_largest_size_on_one_line(): void
    {
        $this->assertSame([120, ['Northgate']], (new TextFitter)->fit($this->font, 'Northgate', 1040));
    }

    public function test_long_title_shrinks_to_at_most_three_lines(): void
    {
        [$size, $lines] = (new TextFitter)->fit($this->font, 'The Northgate Community Arts and Crafts Centre and Public Library', 1040);

        $this->assertLessThan(100, $size);
        $this->assertLessThanOrEqual(3, count($lines));
    }

    public function test_every_line_fits(): void
    {
        $fitter = new TextFitter;
        [$size, $lines] = $fitter->fit($this->font, str_repeat('Harbour View Gardens ', 20), 1040);

        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(1040, $fitter->width($this->font, $size, $line));
        }
        $this->assertStringEndsWith('…', end($lines));
    }

    public function test_wrap_refuses_a_word_wider_than_the_line(): void
    {
        $this->assertNull((new TextFitter)->wrap($this->font, 120, 'Neighbourhood', 300));
    }
}
