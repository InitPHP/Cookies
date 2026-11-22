# Configuration

Configure the transport cookie's attributes through the constructor's third
argument, and understand the couplings between options (notably `samesite`
and `secure`).

## The constructor

```php
public function __construct(
    string $name,
    string $salt,
    array $options = [],
    ?array $source = null,
    ?callable $writer = null
);
```

`$options` overrides the cookie attributes below. **Only the keys you pass
are changed; every other key keeps its default** (the defaults are merged
under your overrides). Unknown keys are simply ignored — there is no
validation error for a typo, so double-check spelling.

```php
use InitPHP\Cookies\Cookie;

$cookie = new Cookie('app_session', $salt, [
    'ttl'      => 2592000, // transport-cookie lifetime in seconds (30 days)
    'path'     => '/',
    'domain'   => null,
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Strict', // 'Strict' | 'Lax' | 'None'
]);
```

The fourth and fifth arguments (`$source`, `$writer`) are for testing and are
covered in [Testing](Testing.md).

## Options reference

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `ttl` | `int` | `2592000` | Lifetime of the **browser** cookie in seconds (30 days). Mapped to `expires = time() + ttl`. Per-value TTLs are separate — see [TTL & Expiry](TTL-and-Expiry.md). |
| `path` | `string` | `'/'` | Cookie path. Omitted from the header when empty. |
| `domain` | `string\|null` | `null` | Cookie domain. **Omitted from the header when `null`** (or empty). |
| `secure` | `bool` | `false` | Send only over HTTPS. |
| `httponly` | `bool` | `true` | Hide the cookie from JavaScript (`document.cookie`). |
| `samesite` | `string` | `'Strict'` | `Strict`, `Lax` or `None` (case-insensitive). An unrecognized value is omitted. `None` forces `secure => true`. |

## Per-option notes

### `ttl` — transport lifetime, not per-value

This is the browser's retention window for the single transport cookie,
written as the `Expires`/`Max-Age` attribute. It is independent of the
per-key `$ttl` you pass to `set()`. Keep per-key TTLs at or below this value,
or the browser will drop the whole cookie first. See
[TTL & Expiry](TTL-and-Expiry.md).

### `path` and `domain`: also used by `destroy()`

The deletion cookie emitted by [`destroy()`](Reading-and-Removing.md#destroy-delete-the-cookie-immediately)
carries the configured `path` and `domain`. A browser only deletes a cookie
when the deletion request matches the original scope, so if you issued the
cookie with a custom `path`/`domain`, construct the manager with the **same**
values before calling `destroy()` — otherwise the browser keeps the old
cookie.

### `domain` is omitted when `null`

When `domain` is `null` (the default) or empty, no `Domain` attribute is
emitted, which scopes the cookie to the exact host that set it (a host-only
cookie). Set a domain only when you intend to share the cookie across
subdomains.

```php
new Cookie('app_session', $salt);                               // no Domain attribute
new Cookie('app_session', $salt, ['domain' => 'example.test']); // Domain=example.test
```

### `secure` and `httponly`

`secure` restricts the cookie to HTTPS connections; `httponly` (on by
default) hides it from JavaScript, which mitigates XSS-based theft. Both are
always emitted as explicit booleans in the header.

### `samesite`: normalized, validated, coupled to `secure`

`samesite` is case-insensitive and normalized to canonical case:

```php
new Cookie('app_session', $salt, ['samesite' => 'lax']);  // emitted as 'Lax'
new Cookie('app_session', $salt, ['samesite' => 'none']); // emitted as 'None'
```

Only `Strict`, `Lax`, and `None` are accepted. **An unrecognized value is
omitted** from the header entirely (the browser then applies its own
default):

```php
new Cookie('app_session', $salt, ['samesite' => 'bogus']);
// No SameSite attribute is sent.
```

`samesite => 'None'` **forces `secure => true`**, because browsers only honor
`SameSite=None` on a `Secure` cookie:

```php
$cookie = new Cookie('app_session', $salt, [
    'samesite' => 'None',
    'secure'   => false, // overridden to true
]);
// The emitted cookie is Secure regardless of the secure override.
```

## Common mistakes

- **Custom `path`/`domain` on set but defaults on destroy.** The browser will
  not delete a cookie whose scope does not match. Use the same options for
  both managers. See [Reading & Removing](Reading-and-Removing.md).
- **`SameSite=None` over plain HTTP.** It is forced `Secure`, so a `None`
  cookie will not be set on an `http://` origin.
- **Expecting an unknown `samesite` to error.** It does not — it is silently
  omitted. Double-check the spelling and case.
- **Expecting an unknown option key to error.** Unknown keys are ignored, not
  rejected. A typo'd option silently has no effect.
- **Confusing the `ttl` option with per-key `$ttl`.** See
  [TTL & Expiry](TTL-and-Expiry.md).

## See also

- [TTL & Expiry](TTL-and-Expiry.md) — the `ttl` option vs. per-key lifetimes.
- [Security Model](Security-Model.md) — the defense-in-depth role of `secure`/`httponly`/`samesite`.
- [Reading & Removing](Reading-and-Removing.md) — how `path`/`domain` flow into `destroy()`.
- [Testing](Testing.md) — the `$source` and `$writer` seams.
