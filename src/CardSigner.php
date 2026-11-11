<?php

namespace Pondake\ShareImage;

/**
 * Puts a card's text in a URL path segment, signed so nobody can make
 * your server render an image with your logo and their words.
 *
 * Path segment, not query string: WhatsApp skips image URLs with a "?".
 */
final readonly class CardSigner
{
    /**
     * @param  string  $secret  An application secret.
     * @param  string  $version  Change it when the card layout changes, so every card gets a new URL.
     */
    public function __construct(
        private string $secret,
        private string $version = '',
    ) {}

    /** @return array{key: string, data: string} Both URL-safe. */
    public function encode(string $title, ?string $eyebrow = null): array
    {
        $data = rtrim(strtr(base64_encode((string) json_encode([$title, $eyebrow])), '+/', '-_'), '=');

        return ['key' => $this->key($data), 'data' => $data];
    }

    public function key(string $data): string
    {
        return substr(hash_hmac('sha256', $data.'|'.$this->version, $this->secret), 0, 16);
    }

    /**
     * Title and eyebrow; null for a wrong key or malformed data.
     *
     * @return array{string, string|null}|null
     */
    public function decode(string $key, string $data): ?array
    {
        if (! hash_equals($this->key($data), $key)) {
            return null;
        }

        $text = json_decode((string) base64_decode(strtr($data, '-_', '+/')), true);

        return is_array($text) && is_string($text[0] ?? null) && $text[0] !== '' && (is_string($text[1] ?? null) || ($text[1] ?? null) === null)
            ? [$text[0], $text[1] ?: null]
            : null;
    }
}
