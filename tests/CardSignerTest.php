<?php

namespace Pondake\ShareImage\Tests;

use PHPUnit\Framework\TestCase;
use Pondake\ShareImage\CardSigner;

final class CardSignerTest extends TestCase
{
    public function test_round_trip(): void
    {
        $signer = new CardSigner('secret', '2');
        ['key' => $key, 'data' => $data] = $signer->encode('Riverton Community Library', 'Library in Riverton');

        $this->assertSame(['Riverton Community Library', 'Library in Riverton'], $signer->decode($key, $data));
        $this->assertSame(['Home', null], $signer->decode(...array_values($signer->encode('Home'))));
    }

    public function test_output_is_url_path_safe(): void
    {
        ['key' => $key, 'data' => $data] = (new CardSigner('secret'))->encode('?/+ & = ü ~', '?');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $key);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $data);
    }

    public function test_forged_text_or_key_is_rejected(): void
    {
        $signer = new CardSigner('secret');
        ['key' => $key] = $signer->encode('Northgate');
        $forged = rtrim(strtr(base64_encode('["Something else",null]'), '+/', '-_'), '=');

        $this->assertNull($signer->decode($key, $forged));
        $this->assertNull($signer->decode(str_repeat('0', 16), 'eA'));
    }

    public function test_signed_garbage_is_rejected(): void
    {
        $signer = new CardSigner('secret');

        foreach (['eA', rtrim(base64_encode('["",null]'), '='), rtrim(base64_encode('[1,2]'), '=')] as $data) {
            $this->assertNull($signer->decode($signer->key($data), $data));
        }
    }

    public function test_new_version_or_secret_changes_the_key(): void
    {
        $data = (new CardSigner('secret', '1'))->encode('Northgate')['data'];

        $this->assertNotSame((new CardSigner('secret', '1'))->key($data), (new CardSigner('secret', '2'))->key($data));
        $this->assertNotSame((new CardSigner('secret', '1'))->key($data), (new CardSigner('other', '1'))->key($data));
    }
}
