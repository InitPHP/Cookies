# Exceptions

Know every condition that makes the package throw, and how to catch it.

The package throws a single exception type:
`InitPHP\Cookies\Exception\CookieInvalidArgumentException`. It extends
`\InvalidArgumentException`, so existing
`catch (\InvalidArgumentException $e)` blocks continue to work.

## When it is raised

| Trigger | Example |
| --- | --- |
| Empty / whitespace-only name (after trim) | `new Cookie('', $salt);` |
| Empty / whitespace-only salt (after trim) | `new Cookie('app_session', '   ');` |
| Non-scalar value to `set` / `push` | `$cookie->set('k', ['a']);` |
| Non-scalar value to `setArray` | `$cookie->setArray(['k' => ['a']]);` |
| Non-associative array to `setArray` (integer key) | `$cookie->setArray(['just-a-value']);` |
| Zero TTL to `set` / `setArray` / `push` | `$cookie->set('k', 'v', 0);` |

## What does NOT throw

| Situation | Behavior instead |
| --- | --- |
| A **negative** TTL | Normalized with `abs()` (so `-100` behaves like `100`). See [TTL & Expiry](TTL-and-Expiry.md#negative-ttl-is-normalized-with-abs). |
| An **empty** array to `setArray()` | A no-op (does not mark the state changed). |
| An **unknown** option key in the constructor | Silently ignored. |
| A missing key to `get` / `has` / `remove` / `pull` | Returns the default / `false` / is a no-op. |
| A tampered, malformed, or wrong-salt **incoming** cookie | Silently discarded and re-issued clean. See the [Security Model](Security-Model.md). |
| An entry of the wrong shape in a signed payload | Ignored on read; dropped on the next re-encode. |

## Examples

### Empty name or salt

```php
use InitPHP\Cookies\Cookie;

new Cookie('', 'a-real-salt');    // throws — empty name
new Cookie('app_session', '   '); // throws — whitespace-only salt
```

### Non-scalar value

Only `string`, `bool`, `int`, `float` and numeric strings are allowed;
arrays, `null`, and objects throw:

```php
$cookie->set('k', ['a', 'b']);   // throws — array
$cookie->set('k', null);         // throws — null is not a scalar value
$cookie->set('k', new stdClass); // throws — object
```

### Non-associative `setArray`

`setArray()` requires string keys. A list (integer keys) throws:

```php
$cookie->setArray(['theme' => 'dark']); // fine — associative
$cookie->setArray(['dark']);            // throws — list, integer key 0
```

### Zero TTL

```php
$cookie->set('k', 'v', 0);          // throws
$cookie->setArray(['k' => 'v'], 0); // throws
$cookie->push('k', 'v', 0);         // throws

$cookie->set('k', 'v', null);       // fine — no per-key expiry
$cookie->set('k', 'v', 3600);       // fine — 1 hour
```

## Catching it

```php
use InitPHP\Cookies\Cookie;
use InitPHP\Cookies\Exception\CookieInvalidArgumentException;

try {
    $cookie = new Cookie('app_session', getenv('COOKIE_SALT'));
    $cookie->set('profile', $someValue); // $someValue might be non-scalar
} catch (CookieInvalidArgumentException $e) {
    // Handle the bad input — e.g. serialize structured data to a string first.
}
```

## Hierarchy

```
InvalidArgumentException
└── InitPHP\Cookies\Exception\CookieInvalidArgumentException
```

If you wrap the package in your own service layer and want callers to catch a
single, domain-specific type, re-throw the exception as a subclass of your
own.

## See also

- [Basic Usage](Basic-Usage.md#allowed-value-types) — the allowed value types.
- [TTL & Expiry](TTL-and-Expiry.md) — why zero throws and negative does not.
- [Security Model](Security-Model.md) — why a hostile *incoming* cookie never throws.
- [API Reference](API-Reference.md#exception) — the throwing method per call.
