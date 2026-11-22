# Quick Start

A five-minute tour of the public API. Every snippet runs as-is against the
released package.

## Construct: the salt is the secret

```php
use InitPHP\Cookies\Cookie;

// new Cookie(string $name, string $salt, array $options = [])
// $name is the browser cookie name; $salt is the HMAC secret.
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));
```

The salt is the HMAC key used to sign and verify the payload. Keep it
**private** (anyone who knows it can forge cookies) and **stable** (changing
it invalidates every existing cookie). An empty or whitespace-only name or
salt throws — see [Exceptions](Exceptions.md).

```php
new Cookie('app_session', '');     // throws CookieInvalidArgumentException
new Cookie('   ', 'a-real-salt');  // throws CookieInvalidArgumentException
```

## Set, then send before output

Mutations are staged in memory; nothing reaches the browser until you call
`send()`. Because `send()` writes HTTP headers, it must run **before any
output** (no `echo`, no HTML, no leading whitespace outside `<?php ?>`).

```php
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));

$cookie->set('user_id', 42);
$cookie->set('flash', 'Saved!', 60); // expires in 60 seconds

$cookie->send(); // write the staged changes to the browser
```

`set()` returns the manager, so writes chain:

```php
$cookie
    ->set('user_id', 42)
    ->set('theme', 'dark')
    ->send();
```

## Read on the next request

On a later request the constructor decodes and verifies the incoming cookie.
Reading is then straightforward, and scalar types are preserved:

```php
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));

$cookie->has('user_id');       // true
$cookie->get('user_id');       // 42  (int — not "42")
$cookie->get('missing', '-');  // '-' (the default, key absent)
```

## Per-key TTL

The third argument of `set()` is a per-key lifetime in **seconds from now**.
A `null` TTL (the default) means the value lives as long as the transport
cookie:

```php
$cookie->set('otp', '123456', 3600); // valid for 1 hour
$cookie->set('user_id', 42);         // null TTL — no per-key expiry
```

Expiry is evaluated lazily on read: an expired entry is removed and reported
as absent. See [TTL & Expiry](TTL-and-Expiry.md).

## `pull` — read once

`pull()` reads a value and removes it in the same call — ideal for flash
messages and one-time tokens:

```php
$cookie->pull('flash');  // 'Saved!' the first time
$cookie->pull('flash');  // null the second time — it was removed
```

## `remove` — stage a deletion

```php
$cookie->remove('user_id');         // single key
$cookie->remove('theme', 'flash');  // several keys
$cookie->send();                    // the deletion reaches the browser here
```

## A complete round trip

```php
use InitPHP\Cookies\Cookie;

$salt = getenv('COOKIE_SALT');

// Request 1 — issue the cookie.
$cookie = new Cookie('app_session', $salt);
$cookie->set('user_id', 42);
$cookie->send();

// Request 2 — browser sends the cookie back; read it.
$cookie = new Cookie('app_session', $salt);
echo $cookie->get('user_id'); // 42
```

## Common mistakes

- **Calling `send()` after output.** Like native `setcookie()`, `send()`
  emits headers and silently fails once the body has started. Call it before
  you render — see [Sending & Lifecycle](Sending-and-Lifecycle.md).
- **Relying on the destructor.** The destructor calls `send()` as a
  safety-net, but by then output may already have flushed. Always call
  `send()` explicitly.
- **Treating the cookie as private.** The client can read every value. The
  signature prevents forgery, not reading — see the [Security Model](Security-Model.md).
- **Rotating the salt without warning.** Changing the salt invalidates every
  existing cookie. This is expected — see the [FAQ](FAQ.md).

## Where to go next

- [Basic Usage](Basic-Usage.md) — `set`/`get`/`has`/`remove` and type preservation.
- [TTL & Expiry](TTL-and-Expiry.md) — per-key TTL vs. the transport `ttl` option.
- [Reading & Removing](Reading-and-Removing.md) — `pull`, `all`, `flush` vs. `destroy`.
- [Security Model](Security-Model.md) — what the signature protects and what it does not.
- [API Reference](API-Reference.md) — every public member, listed.
