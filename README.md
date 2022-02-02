# Composer WordPress Autoloader

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alleyinteractive/composer-wordpress-autoloader.svg?style=flat-square)](https://packagist.org/packages/alleyinteractive/composer-wordpress-autoloader)
[![Tests](https://github.com/alleyinteractive/composer-wordpress-autoloader/actions/workflows/tests.yml/badge.svg)](https://github.com/alleyinteractive/composer-wordpress-autoloader/actions/workflows/tests.yml)

Autoload WordPress files configured via Composer that support the [Wordpress
Coding
Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
using
[alleyinteractive/wordpress-autoloader](https://github.com/alleyinteractive/wordpress-autoloader).

## Installation

You can install the package via composer:

```bash
composer require alleyinteractive/composer-wordpress-autoloader
```

## Usage

```json
{
  "autoload": {
    "wordpress": {
      "My_Plugin_Namespace\\": "src/",
    }
  },
  "autoload-dev": {
    "wordpress": {
      "My_Plugin_Namespace\\Tests\\": "tests/",
    }
  }
}
```

Once added a `vendor/wordpress-autoload.php` file will be created. You can load
that in place of `vendor/autoload.php` (it will load that for you) to load you
WordPress and Composer dependencies.


### Automatically Injecting WordPress Autoloader

Composer WordPress Autoloader can also automatically inject the WordPress
autoloader into your `vendor/autoload.php` file you're already loading. This is
disabled by default.

```json
{
  "extra": {
    "wordpress-autoloader": {
      "inject": true
    }
  }
}
```

Once enabled, you can load `vendor/autoload.php` like you do normally and have
your WordPress files automatically loaded.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.
