# Getting started

Goal: install the package, construct a `Cookie` with a salt, stage a
value, write it to the browser before any output, and read it back on
the next request.

## Install

```bash
composer require initphp/cookies
```

Requires PHP 7.4 or later (tested up to 8.4) and
[`initphp/parameterbag`](https://github.com/InitPHP/ParameterBag)
`^2.0`, which Composer pulls in automatically.

## Construct: the salt is the secret

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use InitPHP\Cookies\Cookie;

// Constructor: new Cookie(string $name, string $salt, array $options = [])
// $name is the browser cookie name; $salt is the HMAC secret.
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));
```

The salt is the HMAC key used to sign and verify the payload. Treat it
like any other application secret:

- **Keep it private.** Anyone who knows it can forge valid cookies.
- **Keep it stable.** If the salt changes, every previously issued
  cookie fails verification and is silently replaced by an empty one,
  so users effectively lose their stored values. See the
  [security model](security-model.md).
- **Do not use an empty or whitespace-only salt** (or name). The
  constructor trims both and throws
  `InitPHP\Cookies\Exception\CookieInvalidArgumentException` if either
  is empty after trimming.

```php
new Cookie('app_session', '');     // throws CookieInvalidArgumentException
new Cookie('   ', 'a-real-salt');  // throws CookieInvalidArgumentException
```

## Set a value, then send before output

Mutations are staged in memory; nothing reaches the browser until you
call `send()`. Because `send()` writes HTTP headers, it must run
**before any output** (no `echo`, no HTML, no leading whitespace
outside `<?php ?>`).

```php
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));

$cookie->set('user_id', 42);
$cookie->set('flash', 'Saved!', 60); // expires in 60 seconds

// Write the staged changes to the browser.
$cookie->send();
```

`set()` returns the manager, so writes chain:

```php
$cookie
    ->set('user_id', 42)
    ->set('theme', 'dark')
    ->send();
```

## Read on the next request

On a later request the constructor decodes and verifies the incoming
cookie. Reading is then straightforward:

```php
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));

$cookie->has('user_id');       // true
$cookie->get('user_id');       // 42  (int — scalar types are preserved)
$cookie->get('missing', '-');  // '-' (the default, key absent)
$cookie->pull('flash');        // reads the value once, then removes it
```

Output:

```
true
42
-
Saved!
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

## Next steps

- Learn the everyday methods in [Basic usage](usage/basic-usage.md).
- Understand expiry in [TTL and expiry](usage/ttl-and-expiry.md).
- Read how the signature protects you in the
  [security model](security-model.md).

## Common mistakes

- **Calling `send()` after output.** Like native `setcookie()`,
  `send()` emits headers and silently fails once the body has started.
  Call it at the end of request handling, before rendering. See
  [Sending and lifecycle](usage/sending-and-lifecycle.md).
- **Relying on the destructor.** The destructor calls `send()` as a
  safety-net, but by then output may already have flushed. Always call
  `send()` explicitly.
- **Rotating the salt without warning.** Changing `COOKIE_SALT`
  invalidates every existing cookie. This is expected behavior, not a
  bug — see the [FAQ](faq.md).
