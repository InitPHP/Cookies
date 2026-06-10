# FAQ

## My cookie isn't being set — why?

Almost always because `send()` ran **after output**. Like native
`setcookie()`, `send()` writes an HTTP header, which only works before
any body has been emitted (no `echo`, no HTML, no whitespace/BOM
outside the PHP tags). Call `send()` at the end of request handling,
before you render.

```php
$cookie->set('a', '1');
$cookie->send(); // BEFORE any output
echo $html;      // output starts here
```

Do not rely on the destructor: it often runs during shutdown, after the
body has flushed, and the default writer suppresses the
"headers already sent" warning — so a too-late write fails *silently*.
See [Sending and lifecycle](usage/sending-and-lifecycle.md).

## My values disappear between requests — why?

The usual cause is a per-key TTL that has elapsed. The `$ttl` argument
of `set()`/`setArray()`/`push()` is seconds from now; once it passes,
the entry is removed on the next read and never re-sent. Check:

- You did not pass a short `$ttl` you have since forgotten about.
- The per-key TTL is not longer than the transport `ttl` option — if it
  is, the browser drops the whole cookie when the transport TTL elapses.
- You actually called `send()` after the mutation.

See [TTL and expiry](usage/ttl-and-expiry.md).

## I changed the salt and all my cookies vanished — is that a bug?

No, that is by design. The salt is the HMAC key. When it changes, every
previously issued cookie fails the signature check and is silently
replaced with an empty (freshly signed) cookie. Users lose their stored
values. Rotate the salt only when you intend exactly that — it doubles
as a global "invalidate every cookie" switch. See the
[security model](security-model.md).

## Can the client read my cookie values?

Yes. The payload is signed, not encrypted — it is base64 of a plain PHP
serialization, which anyone can decode. The signature proves the data
was issued by you and detects tampering; it does **not** hide the
values. Do not store secrets, password hashes, or sensitive PII. Store
an opaque reference and keep the real data server-side. See the
[security model](security-model.md) and the
[remember-me recipe](recipes/remember-me.md).

## Why can't I store an array or an object?

Only `string`, `bool`, `int`, `float` and numeric strings are accepted;
anything else throws `CookieInvalidArgumentException`. To store
structured data, encode it into a string yourself and decode on read:

```php
$cookie->set('prefs', json_encode(['theme' => 'dark', 'lang' => 'en']));
// ...later...
$prefs = json_decode((string) $cookie->get('prefs', '{}'), true);
```

Keep the total payload small (see the next entry).

## Is there a size limit?

Yes. Browsers cap a single cookie at roughly **4 KB** (4096 bytes) for
the whole name=value pair, and the value here also includes the base64
overhead plus a 64-character HMAC signature. Because every value you
set shares one cookie, a large value or many values can blow past the
limit, at which point the browser silently drops the cookie. Keep the
combined payload well under 4 KB; store bulk data server-side.

## Why is `null` rejected when `get()` returns `null`?

`null` is not a storable value type, so `set('k', null)` throws. But
`get()` returns `null` as its *default* when a key is absent. To remove
a value, call `remove('k')` rather than setting it to `null`. To tell
"absent" from a stored `false`/`0`/`''`, use `has()`.

## What's the difference between `flush()` and `destroy()`?

- `flush()` clears the values but keeps the cookie: the next `send()`
  writes an **empty, still-signed** cookie that stays in the browser.
- `destroy()` writes a **deletion** cookie immediately, telling the
  browser to remove it, and makes the next `send()` a no-op.

Use `flush()` to empty the contents, `destroy()` to delete the cookie
(e.g. logout). See
[Reading and removing](usage/reading-and-removing.md).

## Why is `Cookie` `final`?

`Cookie` is `final` and its properties are `private` to keep the
security-sensitive surface (signing, verification, the deserialization
guard) closed to accidental override. A subclass that touched encoding,
the salt, or the verification flow could quietly defeat the tamper
protection. Extend by **composition**: depend on `CookieInterface` and
wrap a `Cookie` instance in your own class, as the
[flash-messages](recipes/flash-messages.md) and
[testing](recipes/testing-cookies.md) recipes do.

## Does it touch the superglobals?

It reads `$_COOKIE` by default (you can inject `$source` instead) and
never writes to it. Writes go through the injectable `$writer`, which
defaults to native `setcookie()`. This makes the class fully testable
without superglobal or header juggling — see the
[testing recipe](recipes/testing-cookies.md).

## Which PHP versions are supported?

PHP 7.4 through 8.4. The 2.0 line requires 7.4+ (1.x advertised 7.2 but
already relied on the PHP 7.3 `setcookie()` options-array signature).
See [Upgrading from 1.x](upgrading-from-1.x.md).
