# share-image

Link preview images (`og:image`) that also show up in **WhatsApp**.

Discord and Facebook accept almost any image. WhatsApp does not: if the image is too heavy, too far away or behind a URL it doesn't like, it silently falls back to your favicon. This library makes images that pass:

- **1200×630**, cropped from the middle of any photo.
- **Under 300 kB**: JPEG quality steps down until it fits (WhatsApp skips anything above ~600 kB).
- **Your mark bottom centre** on a white circle. WhatsApp's ~90 px square thumbnail and Pinterest's portrait crop only show the middle, where a wordmark is unreadable but a mark still reads.
- **A brand card** for pages without a photo: your logo, the title as large as it fits, waves in your colours.
- **Signed card URLs** without a query string, so nobody can make your server render your logo with their words.

Pure PHP + GD. No framework, no headless browser, no external service.

| Photo | Brand card |
|---|---|
| ![Photo with stamped mark](docs/photo.jpg) | ![Brand card](docs/card.png) |

## Install

```bash
composer require pondake/share-image
```

Requires PHP 8.2+ with `gd` and `mbstring`.

## Usage

```php
use Pondake\ShareImage\Branding;
use Pondake\ShareImage\ShareImage;

$images = new ShareImage(new Branding(
    mark: __DIR__.'/brand/mark.png',      // stamped on photos
    logo: __DIR__.'/brand/lockup.png',    // top left on cards
    background: [255, 251, 240],
    waves: [[254, 215, 170], [194, 65, 12]],
));

$jpeg = $images->fromPhoto(file_get_contents($photoFile)); // null if unreadable or too large
$png = $images->card('Opening hours and prices', 'Riverton Library');
```

Every option has a default; `new ShareImage` alone gives a working card with the bundled Figtree font.

### Card URLs

A card is rendered from text in the URL. Sign it, so the route only renders what your app asked for:

```php
use Pondake\ShareImage\CardSigner;

$signer = new CardSigner($appSecret, version: '2'); // bump the version when the layout changes

['key' => $key, 'data' => $data] = $signer->encode('Opening hours and prices');
$url = "https://example.com/share/{$key}/{$data}.png";

// In the route:
[$title, $eyebrow] = $signer->decode($key, $data) ?? abort(404);
```

The URL ends in `.png` and has no `?`: WhatsApp ignored image URLs without an extension or with a query string.

## Getting WhatsApp to show it

The image is half the job. In your `<head>`:

```html
<meta property="og:image" content="https://example.com/share/page-12-3f9a1c.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:type" content="image/jpeg">
```

- Put a version in the **path** (`page-12-<hash>.jpg`), not in `?v=`. A new image then gets a new URL, and WhatsApp's per-URL cache can't serve the old one.
- Keep `property` and `content` first in the tag; extra attributes go after.
- Store the result and serve it from disk; render once per version.
- To retest, share the page with a fresh query string (`?w=2`): WhatsApp caches previews per page URL.

## Development

```bash
composer install
composer test
composer examples   # re-renders the images in docs/
```

## License

MIT. The bundled [Figtree](https://github.com/erikdkennedy/figtree) fonts are under the SIL Open Font License (`resources/fonts/OFL.txt`).
