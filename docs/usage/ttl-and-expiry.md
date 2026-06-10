# TTL and expiry

Goal: understand the two independent lifetimes in play — the per-key
`$ttl` argument and the `ttl` *option* — and the exact rules for
`null`, zero, and negative TTLs.

## Two different lifetimes

There are two TTLs, and they control different things:

| | Per-key `$ttl` argument | `ttl` option |
| --- | --- | --- |
| Set via | `set()` / `setArray()` / `push()` | the constructor's options array |
| Controls | how long the manager treats an **individual value** as valid | how long the **browser** keeps the single transport cookie |
| Unit | seconds from now | seconds from now |
| Default | `null` (lives as long as the transport cookie) | `2592000` (30 days) |

The per-key TTL is enforced by the manager when you read; the option
TTL is the `Expires` attribute on the actual browser cookie.

```php
use InitPHP\Cookies\Cookie;

// The browser keeps the transport cookie for 7 days...
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'), [
    'ttl' => 7 * 86400,
]);

// ...but this individual value is only valid for 1 hour.
$cookie->set('otp', '123456', 3600);
```

If the per-key TTL outlives the transport cookie, the browser deletes
the whole cookie first and the value is gone regardless. Keep per-key
TTLs at or below the transport `ttl` for predictable behavior.

## Per-key TTL: seconds from now

The `$ttl` argument is a **relative** lifetime in seconds. Internally
it is converted to an absolute expiry timestamp at the moment you call
`set()`, so it counts from "now":

```php
$cookie->set('token', 'abc', 3600); // valid for 1 hour
$cookie->has('token');              // true (right away)
$cookie->get('token');              // 'abc'
```

`push()` and `setArray()` take the same `$ttl`:

```php
$cookie->push('token', 'abc', 3600);            // returns 'abc'
$cookie->setArray(['a' => '1', 'b' => '2'], 60); // both share a 60s TTL
```

## `null` TTL: no per-key expiry

A `null` per-key TTL (the default) means the value lives as long as the
transport cookie. It never expires on its own:

```php
$cookie->set('user_id', 42);       // null TTL → no per-key expiry
$cookie->set('user_id', 42, null); // identical
```

## Expiry on read

Expiry is evaluated lazily, when you read. An expired entry is removed
from the working copy as a side effect and reported as absent:

- `has()` on an expired key removes it and returns `false`.
- `get()` on an expired key removes it and returns the default.
- `all()` excludes (and removes) every expired entry.

```php
// Suppose 'token' was set with a 1-second TTL two seconds ago.
$cookie->has('token');            // false — expired, now removed
$cookie->get('token', 'gone');    // 'gone'
```

Because expired entries are stripped before re-encoding, they are never
re-sent: the next `send()` writes a cookie that no longer contains them.
You generally do not need to clean up expired values manually.

## Zero TTL throws

A `$ttl` of exactly `0` is rejected — a value that is valid for zero
seconds is meaningless. It throws
`InitPHP\Cookies\Exception\CookieInvalidArgumentException`:

```php
$cookie->set('k', 'v', 0); // throws CookieInvalidArgumentException
```

Use `null` for "no per-key expiry", or a positive integer for a real
lifetime.

## Negative TTL is normalized with abs()

A negative `$ttl` is **not** treated as "already expired". It is run
through `abs()`, so `-100` behaves exactly like `100`:

```php
$cookie->set('k', 'v', -100); // abs(-100) → 100 seconds from now
$cookie->get('k');            // 'v' — still valid
```

This is a deliberate normalization, so an accidental sign flip does not
silently drop a value. If you actually want to expire something, call
[`remove()`](reading-and-removing.md) instead of passing a negative
TTL.

## Common mistakes

- **Confusing the two TTLs.** Setting the `ttl` option to `60` does not
  expire individual values after a minute — it sets the *browser*
  cookie lifetime. Per-value expiry is the `$ttl` argument of `set()`.
- **Passing `0` to mean "no expiry".** Zero throws. Use `null`.
- **Expecting a negative TTL to delete a value.** It does the opposite
  (normalized via `abs()`). Use `remove()` to delete.
- **Per-key TTL longer than the transport `ttl`.** The browser drops
  the whole cookie when the transport TTL elapses, so longer per-key
  TTLs cannot outlive it.
