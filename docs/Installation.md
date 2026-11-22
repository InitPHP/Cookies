# Installation

Install `initphp/cookies` with Composer, confirm the runtime requirements,
and run a five-line smoke test before moving on to the [Quick Start](Quick-Start.md).

## Requirements

| | |
| --- | --- |
| PHP | `^8.2` (tested on 8.2, 8.3, 8.4 and 8.5) |
| Extensions | None beyond the PHP core (`hash`, `setcookie` are built in) |
| Runtime dependency | [`initphp/parameterbag`](https://github.com/InitPHP/ParameterBag) `^3.0` |

The only runtime dependency is `initphp/parameterbag`, which Composer pulls
in automatically. Development tools (PHPUnit, PHPStan, PHP-CS-Fixer) live
under `require-dev` and are not installed when you depend on the package
from another project.

## Install via Composer

```bash
composer require initphp/cookies
```

The classes are autoloaded under the `InitPHP\Cookies` namespace via PSR-4:

```json
{
    "autoload": {
        "psr-4": {
            "InitPHP\\Cookies\\": "src/"
        }
    }
}
```

The package ships three public types:

| Type | Purpose |
| --- | --- |
| [`Cookie`](API-Reference.md#cookie) | The default concrete implementation (`final`). |
| [`CookieInterface`](API-Reference.md#cookieinterface) | The contract — type-hint this in your services. |
| [`CookieInvalidArgumentException`](Exceptions.md) | Thrown on an invalid name/salt, value, or TTL. |

## Smoke test

Drop this into a script and run it from the CLI. It exercises the full
in-memory round trip without emitting real headers by injecting a tiny
recording writer (the fifth constructor argument):

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Cookies\Cookie;

$written = [];
$writer = static function (string $name, string $value, array $options) use (&$written): bool {
    $written[$name] = $value; // capture instead of calling setcookie()
    return true;
};

// 2nd arg is the HMAC salt; 4th is the cookie source; 5th is the writer.
$cookie = new Cookie('app_session', 'a-real-salt', [], [], $writer);
$cookie->set('user_id', 42);
$cookie->send();

// Feed what was written back in as an incoming cookie:
$reader = new Cookie('app_session', 'a-real-salt', [], ['app_session' => $written['app_session']]);
echo $reader->get('user_id'); // 42
```

If you see `42`, signing, sending, and verification all work. In a real
request you would omit the `$source` and `$writer` arguments so the manager
reads `$_COOKIE` and calls native `setcookie()`.

## Supported PHP versions in CI

Every change runs the test matrix against PHP 8.2, 8.3, 8.4 and 8.5. If you find a regression on a supported version, please
[file an issue](https://github.com/InitPHP/Cookies/issues/new) and include
the version reported by `php -v`.

## Where to go next

- Take the five-minute tour in [Quick Start](Quick-Start.md).
- Learn the everyday read/write API in [Basic Usage](Basic-Usage.md).
- Read what the signature protects (and does not) in the [Security Model](Security-Model.md).
- Upgrading from 1.x? See [Migration (v1 → v2)](Migration-from-1.x.md).
