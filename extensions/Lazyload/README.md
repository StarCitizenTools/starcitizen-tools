MediaWiki Lazyload Extension
============================

An extension to delay loading of images on MediaWiki pages.

## Requirements

Lazyload extension requires MediaWiki 1.25 or higher.

## Installation

To install the extension, place the entire `Lazyload` directory within your
MediaWiki `extensions` directory, then add the following line to your
`LocalSettings.php` file:

```php
wfLoadExtension( 'Lazyload' );
```

If you are using the [APNG extension](https://github.com/mudkipme/mediawiki-apng) on your wiki, please upgrade it to 0.2.0 or higher.

## Configuration

Lazyload extension supports dynamically replace image hosts or disable HiDPI support for certain cases in [JavaScript](https://www.mediawiki.org/wiki/Manual:Interface/JavaScript). This is particularly useful when your wiki wants to use multiple CDNs for various conditions.

```javascript
mw.config.set('Lazyload.imageHost', YOUR_IMAGE_HOST);
mw.config.set('Lazyload.disableHidpi', true);
```

A use case in [52Poké Wiki](https://wiki.52poke.com/) is setting the image host to CDN only for non-logged in users, and another CDN domain backed by [malasada](https://github.com/mudkipme/malasada) for browsers supporting WebP to minimize traffic cost.

```javascript
(function() {
    if (mw.config.get('wgUserName')) {
        return;
    }
    mw.config.set('Lazyload.imageHost', '//s0.52poke.wiki');
    mw.config.set('Lazyload.disableHidpi', true);

    function testWebP(callback) {
        var webP = new Image();
        webP.onload = webP.onerror = function () {
            callback(webP.height === 2);
        };
        webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
    }

    testWebP(function (supported) {
        if (supported) {
            mw.config.set('Lazyload.imageHost', '//s1.52poke.wiki');
        }
    });
})();
```

## License

[MIT](LICENSE)