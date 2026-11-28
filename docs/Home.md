# InitPHP Cookies — Wiki

Welcome to the official documentation for **`initphp/cookies`** — a signed,
tamper-evident cookie manager for PHP. Your values live in a single browser
cookie whose payload is authenticated with an **HMAC-SHA256** signature, so a
client can read it but cannot forge or alter it. Each value can carry its own
**time-to-live**, and writes are staged in memory and flushed with one call.

The package ships three types:

| Type | Purpose |
| --- | --- |
| [`Cookie`](API-Reference.md#cookie) | The default concrete implementation (`final`). |
| [`CookieInterface`](API-Reference.md#cookieinterface) | The contract — type-hint this in your services. |
| [`CookieInvalidArgumentException`](Exceptions.md) | Thrown on an invalid name/salt, value, or TTL. |

```bash
composer require initphp/cookies
```

```php
use InitPHP\Cookies\Cookie;

// The salt is the HMAC secret — keep it private and stable across requests.
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));

$cookie->set('user_id', 42);
$cookie->set('flash', 'Saved!', 60); // expires in 60 seconds

$cookie->send(); // write the staged changes before any output
```

On the next request:

```php
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));

$cookie->has('user_id');      // true
$cookie->get('user_id');      // 42 (int — scalar types are preserved)
$cookie->pull('flash');       // reads the value once, then removes it
$cookie->get('missing', '-'); // '-' (default)
```

## Start here

- **New to the package?** Read [Installation](Installation.md), then
  [Quick Start](Quick-Start.md).
- **Storing values?** See [Basic Usage](Basic-Usage.md) and
  [TTL & Expiry](TTL-and-Expiry.md).
- **Reading, clearing, deleting?** Read
  [Reading & Removing](Reading-and-Removing.md).
- **When does a cookie actually get written?** Read
  [Sending & Lifecycle](Sending-and-Lifecycle.md).
- **What does the signature protect (and not)?** Read the
  [Security Model](Security-Model.md).
- **Keeping a user signed in?** Read the
  [Remember Me recipe](Recipe-Remember-Me.md).
- **Upgrading from 1.x?** Read [Migration (v1 → v2)](Migration-from-1.x.md).
- **Looking for a specific method?** The full [API Reference](API-Reference.md)
  lists every public member.

## At a glance — capabilities

| Capability | Supported |
| --- | :---: |
| HMAC-SHA256 signed payload | ✅ |
| Constant-time verification (`hash_equals`) | ✅ |
| Object-injection-safe deserialization (`allowed_classes => false`) | ✅ |
| Tamper → discard & re-issue clean | ✅ |
| Per-key time-to-live | ✅ |
| Many values under one named cookie | ✅ |
| Deferred writes (stage in memory, one `send()`) | ✅ |
| Scalar type preservation (`int`/`float`/`bool`/`string`) | ✅ |
| Injectable cookie source & writer (for tests) | ✅ |
| Confidentiality / encryption of values | ❌ (integrity only — see [Security Model](Security-Model.md)) |

## Package metadata

- **License**: MIT
- **Minimum PHP**: 8.2 (tested on 8.2 – 8.6)
- **Runtime dependency**: [`initphp/parameterbag`](https://github.com/InitPHP/ParameterBag) `^3.0`
- **Packagist**: [`initphp/cookies`](https://packagist.org/packages/initphp/cookies)
- **Source**: [github.com/InitPHP/Cookies](https://github.com/InitPHP/Cookies)
- **Issues**: [github.com/InitPHP/Cookies/issues](https://github.com/InitPHP/Cookies/issues)
- **Discussions**: [github.com/orgs/InitPHP/discussions](https://github.com/orgs/InitPHP/discussions)
- **Security**: [`SECURITY.md`](https://github.com/InitPHP/.github/blob/main/SECURITY.md)

> **Integrity, not secrecy.** Signing proves a cookie was issued by you and has
> not been altered; it does **not** hide the values from the client. Never store
> secrets in a cookie — see the [Security Model](Security-Model.md).

If something in this wiki is unclear, ambiguous, or wrong, please
[open an issue](https://github.com/InitPHP/Cookies/issues/new) —
documentation fixes are reviewed eagerly.
