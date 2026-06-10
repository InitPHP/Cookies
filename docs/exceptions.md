# Exceptions

Goal: know every condition that makes the package throw, and how to
catch it.

The package throws a single exception type:
`InitPHP\Cookies\Exception\CookieInvalidArgumentException`. It extends
`\InvalidArgumentException`, so `catch (\InvalidArgumentException $e)`
blocks continue to work.

## When it is raised

| Trigger | Example |
| --- | --- |
| Empty / whitespace-only name (after trim) | `new Cookie('', $salt);` |
| Empty / whitespace-only salt (after trim) | `new Cookie('app_session', '   ');` |
| Non-scalar value to `set` / `push` | `$cookie->set('k', ['a']);` |
| Non-scalar value to `setArray` | `$cookie->setArray(['k' => ['a']]);` |
| Non-associative array to `setArray` (integer key) | `$cookie->setArray(['just-a-value']);` |
| Zero TTL to `set` / `setArray` / `push` | `$cookie->set('k', 'v', 0);` |

Note what does **not** throw:

- A **negative** TTL — it is normalized with `abs()` (so `-100` behaves
  like `100`). See [TTL and expiry](usage/ttl-and-expiry.md).
- An **empty** array to `setArray()` — it is a no-op.
- An **unknown** option key in the constructor — it is ignored.
- A missing key to `get`/`has`/`remove`/`pull` — these return a
  default / `false` / are no-ops.
- A tampered or malformed **incoming** cookie — it is silently
  discarded and re-issued clean (no exception). See the
  [security model](security-model.md).

## Examples

### Empty name or salt

```php
use InitPHP\Cookies\Cookie;

new Cookie('', 'a-real-salt');     // throws — empty name
new Cookie('app_session', '   ');  // throws — whitespace-only salt
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
$cookie->set('k', 'v', 0);            // throws
$cookie->setArray(['k' => 'v'], 0);   // throws
$cookie->push('k', 'v', 0);           // throws

$cookie->set('k', 'v', null);         // fine — no per-key expiry
$cookie->set('k', 'v', 3600);         // fine — 1 hour
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

If you wrap the package in your own service layer and want callers to
catch a single, domain-specific type, re-throw the exception as a
subclass of your own.
