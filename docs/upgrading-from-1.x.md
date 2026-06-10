# Upgrading from 1.x

Version 2.0 is a security and correctness release with intentional
breaking changes. The headline is that the wire format changed, so
cookies issued by 1.x are not readable by 2.x — clients simply receive
a fresh cookie. Most applications need no code changes beyond
re-checking their constructor call; the behavioral fixes are what
matter.

## TL;DR

- Cookies issued by 1.x are silently dropped and re-issued by 2.x
  (expected — see below). No migration step is required, but plan for
  every existing cookie to be reset once.
- If your 1.x code set values with an explicit TTL and found them
  unreadable, **that bug is fixed** in 2.0.
- Requires **PHP 7.4+**.
- `Cookie` is now `final`.
- Two **optional** constructor arguments were added after `$options`;
  existing 3-argument calls are unaffected.

## Behavioral changes

### Cookie format changed — old cookies are not readable

The payload is now signed with **HMAC-SHA256** (1.x used MD5) and the
envelope layout is different (`base64(serialize(entries)) . "." . hmac`).
A 2.x reader recomputes the signature, finds it does not match a
1.x-issued value, discards the payload, and re-issues a clean cookie.

Practical effect: on the first request after deploying 2.0, every user's
existing cookie is replaced with an empty one — stored values are lost
once. This is the same mechanism that makes salt rotation an
"invalidate everything" switch. Plan for it (e.g. do not assume a value
set under 1.x will still be present).

### The 1.x inverted-TTL bug is fixed

The legacy implementation inverted the expiry comparison in `get()` and
`encode()`: a still-valid TTL'd value was deleted on read and dropped
before being persisted, so **any value set with an explicit TTL was
effectively unusable**. `has()`/`get()`/`all()`/`encode()` also
disagreed on the predicate, so `has()` could report a value present
while `get()` returned the default.

2.0 uses a single, correct predicate everywhere:

```php
$cookie->set('token', 'abc', 3600);

$cookie->has('token'); // 1.x: inconsistent;  2.0: true
$cookie->get('token'); // 1.x: default (gone); 2.0: 'abc'
```

If you previously avoided per-key TTLs because they "didn't work", you
can use them now. See [TTL and expiry](usage/ttl-and-expiry.md).

### TTL argument semantics are well-defined

In 2.0 the per-key `$ttl` is seconds from now, stored internally as an
absolute expiry. `null` means "no per-key expiry"; `0` throws; a
negative value is normalized via `abs()`. See
[TTL and expiry](usage/ttl-and-expiry.md).

### Tamper detection and object-injection hardening

2.0 verifies the signature with `hash_equals()` (constant time) before
any deserialization, and deserializes with `allowed_classes => false`,
closing the PHP object-injection vector. A tampered, truncated, or
wrong-salt cookie is rejected and re-issued clean. See the
[security model](security-model.md).

## API and class changes

### `Cookie` is `final`, properties are `private`

Extend by composition rather than inheritance. Depend on
`InitPHP\Cookies\CookieInterface` and wrap a `Cookie` instance in your
own class. If your 1.x code subclassed `Cookie` or reached into its
properties, refactor to composition — see the
[FAQ](faq.md) on why the class is `final`.

### New optional constructor arguments

Two arguments were added **after** `$options`, both optional, for
testability:

```php
public function __construct(
    string $name,
    string $salt,
    array $options = [],
    ?array $source = null,   // NEW — raw cookie source, defaults to $_COOKIE
    ?callable $writer = null // NEW — low-level writer, defaults to setcookie()
);
```

Existing three-argument calls (`new Cookie($name, $salt, $options)`) are
unaffected. The new seams let you test cookie-using code without real
headers or `$_COOKIE` — see the
[testing recipe](recipes/testing-cookies.md).

### Name and salt are validated

An empty or whitespace-only name or salt (after trimming) now throws
`CookieInvalidArgumentException`. Pass real, stable values.

## PHP version

2.0 requires **PHP 7.4** or later (including 8.0–8.4). 1.x advertised
7.2 but already relied on the PHP 7.3 `setcookie()` options-array
signature, so the floor is now stated honestly.

## Migration checklist

- [ ] Confirm `COOKIE_SALT` (or equivalent) is set to a stable, secret
      value and stays constant across requests.
- [ ] Expect existing cookies to reset once on deploy; do not depend on
      a value persisting across the 1.x→2.0 boundary.
- [ ] If you relied on per-key TTLs, re-enable them — they work now.
- [ ] Replace any subclassing of `Cookie` with composition against
      `CookieInterface`.
- [ ] Ensure `send()` is called before output (this was always true,
      but worth re-verifying).
- [ ] Run on PHP 7.4+.
