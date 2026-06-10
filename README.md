# InitPHP Cookies

A signed, tamper-evident cookie manager for PHP. Values are stored in a
single browser cookie whose payload is authenticated with an
HMAC-SHA256 signature, so a client cannot read-tamper its way into
forging cookie data. Each value can carry its own time-to-live.

[![CI](https://github.com/InitPHP/Cookies/actions/workflows/ci.yml/badge.svg)](https://github.com/InitPHP/Cookies/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/initphp/cookies/v)](https://packagist.org/packages/initphp/cookies)
[![License](https://poser.pugx.org/initphp/cookies/license)](./LICENSE)

## Features

- **Signed payload** — the whole cookie is signed with HMAC-SHA256 and
  verified in constant time (`hash_equals`). A tampered cookie is
  rejected and transparently re-issued clean.
- **Hardened against object injection** — deserialization runs only
  *after* the signature check and forbids object instantiation
  (`allowed_classes => false`).
- **Per-key TTL** — every value may expire independently; expired
  values are dropped on read and never re-sent.
- **One browser cookie, many values** — group related values under a
  single named, signed cookie.
- **Deferred writes** — mutations are staged in memory and flushed with
  a single `send()` (or by the destructor as a safety-net).
- **Testable by design** — the raw cookie source and the low-level
  writer are injectable, so no superglobal or header juggling is needed
  in tests.

## Requirements

- PHP 7.4, 8.0, 8.1, 8.2, 8.3 or 8.4
- [initphp/parameterbag](https://github.com/InitPHP/ParameterBag) `^2.0`

## Installation

```bash
composer require initphp/cookies
```

## Quick start

```php
require_once __DIR__ . '/vendor/autoload.php';

use InitPHP\Cookies\Cookie;

// The salt is the HMAC secret. Keep it private and stable across requests.
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));

$cookie->set('user_id', 42);
$cookie->set('flash', 'Saved!', 60); // expires in 60 seconds

// Flush the staged changes to the browser before any output is sent.
$cookie->send();
```

On the next request:

```php
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));

$cookie->has('user_id');     // true
$cookie->get('user_id');     // 42 (int — scalar types are preserved)
$cookie->get('missing', '-'); // '-' (default)
$cookie->pull('flash');      // reads the value once, then removes it
```

> **Important:** like PHP's native `setcookie()`, `send()` writes HTTP
> headers, so it must run before any output. Call it explicitly at the
> end of your request handling. The destructor calls `send()` as a
> safety-net, but relying on it is discouraged.

## How it works

The manager keeps an in-memory working copy of your values. Mutating
methods change only that copy; nothing reaches the browser until
`send()` is called. On `send()` the working copy is:

1. stripped of expired entries,
2. `serialize()`-d and base64-encoded into a `payload`,
3. signed: `signature = hash_hmac('sha256', payload, salt)`,
4. written as the cookie value `"{payload}.{signature}"`.

On construction the incoming cookie is verified before anything is
deserialized: the signature is recomputed and compared with
`hash_equals()`. If it does not match (tampering, a different salt, a
truncated value), the payload is discarded and a clean cookie is
re-issued. Because deserialization happens only after that check and
with `allowed_classes => false`, a malicious cookie cannot trigger PHP
object injection.

## Configuration

The third constructor argument overrides the default cookie attributes.
Only the keys you pass are changed; the rest keep their defaults.

```php
$cookie = new Cookie('app_session', $salt, [
    'ttl'      => 2592000, // transport-cookie lifetime in seconds (30 days)
    'path'     => '/',
    'domain'   => null,
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Strict', // 'Strict' | 'Lax' | 'None'
]);
```

| Option     | Type            | Default     | Description                                                                 |
|------------|-----------------|-------------|-----------------------------------------------------------------------------|
| `ttl`      | `int`           | `2592000`   | Lifetime of the **browser** cookie in seconds. Per-value TTLs are separate. |
| `path`     | `string`        | `'/'`       | Cookie path.                                                                |
| `domain`   | `string\|null`  | `null`      | Cookie domain. Omitted from the header when `null`.                         |
| `secure`   | `bool`          | `false`     | Send only over HTTPS.                                                        |
| `httponly` | `bool`          | `true`      | Hide the cookie from JavaScript.                                            |
| `samesite` | `string`        | `'Strict'`  | `Strict`, `Lax` or `None`. `None` automatically forces `secure => true`.    |

### `ttl` vs. per-key TTL

- The `ttl` **option** controls how long the *browser* keeps the single
  transport cookie.
- The `$ttl` **argument** of `set()` / `setArray()` / `push()` controls
  how long an *individual value* is considered valid by the manager. A
  `null` per-key TTL means "live as long as the transport cookie".

## API

```php
public function has(string $key): bool;
public function get(string $key, mixed $default = null): mixed;
public function pull(string $key, mixed $default = null): mixed;
public function set(string $key, string|int|float|bool $value, ?int $ttl = null): self;
public function setArray(array $assoc, ?int $ttl = null): self;
public function push(string $key, string|int|float|bool $value, ?int $ttl = null): mixed;
public function all(): array;
public function remove(string ...$key): self;
public function send(): bool;
public function flush(): bool;
public function destroy(): bool;
```

| Method      | Description                                                                                  |
|-------------|----------------------------------------------------------------------------------------------|
| `has`       | Whether a non-expired value exists. An expired value is removed and reported as absent.       |
| `get`       | The value for `$key`, or `$default` when absent/expired. Scalar types are preserved.          |
| `pull`      | Like `get`, but removes the value afterwards (read-once).                                     |
| `set`       | Stage a single value. `$ttl` is seconds from now, or `null` for no per-key expiry.            |
| `setArray`  | Stage several values from an associative array sharing one TTL.                               |
| `push`      | Like `set`, but returns the staged value.                                                     |
| `all`       | All non-expired values as a `key => value` map.                                              |
| `remove`    | Stage removal of one or more keys.                                                            |
| `send`      | Write the staged state to the browser. No-op when nothing changed.                            |
| `flush`     | Empty all values; the next `send()` writes an empty (still signed) cookie.                    |
| `destroy`   | Immediately expire and clear the transport cookie in the browser.                             |

Allowed value types are `string`, `bool`, `int`, `float` and numeric
strings. Anything else throws
`InitPHP\Cookies\Exception\CookieInvalidArgumentException`.

## Documentation

Full developer documentation lives in [`docs/`](./docs/README.md):
getting started, usage guides, the configuration reference, the security
model, the API reference and practical recipes.

## Upgrading from 1.x

Version 2.0 is a security and correctness release with intentional
breaking changes:

- **Cookie format changed.** The payload is now signed with
  HMAC-SHA256 (was MD5) and the envelope layout is different, so cookies
  issued by 1.x are not readable by 2.x — clients simply receive a fresh
  cookie. The previous 1.x behaviour also made any value set with an
  explicit TTL unreadable; 2.0 fixes that.
- **PHP 7.4+** is now required (1.x advertised 7.2, but already relied on
  the PHP 7.3 `setcookie()` options array).
- **`Cookie` is `final`** and its properties are `private`. Extend by
  composition rather than inheritance.
- **`new` optional constructor arguments** — `$source` (raw cookie
  array) and `$writer` (low-level writer) were added after `$options`
  for testability; existing 3-argument calls are unaffected.

## Testing

```bash
composer install
composer test        # PHPUnit
composer analyse     # PHPStan (level 8)
composer cs:check    # PHP-CS-Fixer (dry-run)
```

## Credits

- [Muhammet ŞAFAK](https://github.com/muhammetsafak) <<info@muhammetsafak.com.tr>>

## License

Released under the [MIT License](./LICENSE). Copyright &copy; 2022 InitPHP.
