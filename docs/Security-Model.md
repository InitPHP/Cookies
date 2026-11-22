# Security Model

This page is the package's threat model and wire-format reference: precisely
what the HMAC signature protects, what it does **not**, how the signed
envelope is laid out on the wire, how to manage the salt, and why a tampered
cookie can never trigger PHP object injection.

For *reporting* a vulnerability, see
[`SECURITY.md`](https://github.com/InitPHP/.github/blob/main/SECURITY.md).
This page is the design context behind it.

## Threat model

We assume an attacker who can:

- Read, store, modify or replay any cookie your application issues (the
  client holds the cookie and can inspect every byte).
- Submit arbitrary bytes back as the incoming cookie — random data, a forged
  envelope, someone else's cookie, a truncated value.
- Observe the timing of your verification.

We assume the attacker **cannot**:

- Read your `salt` from your secret store or process memory.
- Modify the package source at runtime.

Given those assumptions, the package guarantees:

1. **Integrity and authenticity.** A client cannot change a value, extend an
   expiry, or inject a new key without knowing the salt — any edit
   invalidates the signature and the whole payload is discarded.
2. **A bound on what verification does with attacker bytes.** Deserialization
   runs only *after* the signature check and with `allowed_classes => false`,
   so a hostile cookie cannot instantiate application classes — no PHP
   object-injection gadget chain can fire.
3. **No crash on hostile input.** A missing delimiter, bad base64, a
   non-array payload, or an entry of the wrong shape is ignored, not fatal —
   the manager simply starts from an empty state.

What the package does **not** guarantee:

- **Confidentiality.** The payload is signed, not encrypted. The client can
  read every value. This is the single most important property to internalize
  — see [Integrity is not secrecy](#integrity-is-not-secrecy).

## What gets written: the wire format

The browser cookie value is two parts joined by a dot:

```
+--------------------------------+---+----------------------------------+
| payload                        | . | signature                        |
+--------------------------------+---+----------------------------------+
| base64(serialize(entries))     | . | hash_hmac('sha256', payload,salt)|
+--------------------------------+---+----------------------------------+
```

1. The non-expired working copy (`entries`) is `serialize()`-d and
   base64-encoded into a `payload`.
2. The payload is signed: `signature = hash_hmac('sha256', payload, salt)`.
3. The cookie value is `"{payload}.{signature}"`.

The dot is a safe delimiter because neither standard base64 output nor a hex
HMAC digest can contain a dot.

Each entry inside `entries` has the shape:

```php
'key' => ['value' => $scalar, 'ttl' => $absoluteUnixTimestampOrNull]
```

The `ttl` is an **absolute** Unix timestamp (or `null` for "no per-key
expiry"), not "seconds from now" — the per-key `$ttl` argument you pass to
`set()` is converted to this absolute form at write time. See
[TTL & Expiry](TTL-and-Expiry.md).

You can reproduce this exact envelope by hand — that is how the test suite and
the [Testing](Testing.md) guide craft input.

## How verification works

On every construction, the incoming cookie is verified **before** anything is
deserialized:

1. The value is split into `payload` and `signature` on the first dot.
2. The expected signature is recomputed from the payload and the salt.
3. It is compared with the stored signature using `hash_equals()`
   (constant-time, so signature comparison does not leak timing information).
4. The base64 is decoded strictly (`base64_decode($payload, true)`), then
   `unserialize($s, ['allowed_classes' => false])` is applied.

If any step fails — no delimiter, signature mismatch, invalid base64, or a
deserialized result that is not an array — the payload is **discarded** and
the manager starts from an empty state. When the incoming value was malformed
or tampered (rather than simply absent), the state is flagged as changed so
the next `send()` re-issues a clean, correctly signed cookie.

```php
use InitPHP\Cookies\Cookie;

// An attacker who flips even one byte of the payload...
$tampered = $forgedPayload . '.' . $stolenSignature;

$cookie = new Cookie('app_session', $salt, [], ['app_session' => $tampered]);
$cookie->get('user'); // null — verification failed, payload discarded
$cookie->all();       // []
```

A signature forged under a guessed or wrong salt is likewise rejected — an
attacker without the real salt cannot produce a matching HMAC, so a hand-built
future-dated `['admin' => ['value' => true, 'ttl' => time() + 999999]]` entry
never verifies:

```php
$cookie->get('admin'); // null — forged signature rejected
```

## Integrity is not secrecy

**The signature is not encryption.** The payload is base64 of a plain PHP
serialization — anyone can decode and read it. Signing proves the data was
issued by you and has not been altered; it does **not** hide the values.

Consequences:

- **The client can read every value.** Do not store secrets, password hashes,
  API keys, internal IDs you would not expose, or PII you would not put in a
  URL.
- **Store references, not secrets.** Keep a session ID or an opaque token in
  the cookie and hold the sensitive data server-side, keyed by that token.
  See the [Remember Me recipe](Recipe-Remember-Me.md).

> Integrity ≠ secrecy. If you need confidentiality, encrypt the value yourself
> before storing it (and keep the encryption key server-side), or keep the
> data on the server entirely. The
> [`initphp/encryption`](https://github.com/InitPHP/Encryption) package is one
> way to encrypt a value before it goes into a cookie.

## Salt management

The salt is the HMAC key. Everything above depends on keeping it secret and
stable.

- **Secret.** Anyone who knows the salt can forge valid cookies for any
  payload. Store it like a database password — in an environment variable or a
  secrets manager, never in version control.
- **Stable.** If the salt changes, every previously issued cookie fails
  verification and is replaced by an empty one. Users lose their stored
  values. This is expected behavior, and it doubles as an effective
  "invalidate every cookie" switch — rotate the salt deliberately, with
  awareness of the consequence (see the [FAQ](FAQ.md)).
- **Per-secret isolation.** Different salts produce mutually unreadable
  cookies; a cookie signed with salt A is rejected by a manager using salt B.
- **Non-empty.** The constructor trims the salt and throws
  [`CookieInvalidArgumentException`](Exceptions.md) if it is empty.

The constructor also trims the name and salt, so a value padded with
surrounding whitespace signs and verifies the same as its trimmed form —
`'  my-salt  '` and `'my-salt'` are interchangeable.

### Generating a salt

```bash
php -r 'echo bin2hex(random_bytes(32)), "\n";'
# → 64-character hex string, 256 bits of entropy
```

Put that string into your secret store and reference it via
`getenv('COOKIE_SALT')`. Do not commit it.

## Object-injection hardening

Deserializing attacker-controlled data is the classic PHP object-injection
vector. This package closes it two ways:

1. **Deserialization runs only after the signature check.** An attacker who
   does not know the salt cannot get their payload past verification, so their
   serialized blob never reaches `unserialize()` at all.
2. **Even a correctly signed payload cannot instantiate objects.**
   Deserialization uses `unserialize($data, ['allowed_classes' => false])`, so
   any serialized object is decoded as `__PHP_Incomplete_Class` and no class
   is instantiated — no `__wakeup()`, no `__destruct()`, no gadget chain
   fires.

```php
// A correctly signed payload smuggling a serialized object still cannot wake
// it: allowed_classes => false prevents instantiation. The object is not a
// valid entry, so it reads as absent.
$cookie->get('x'); // null — no object was ever constructed
```

The package's test suite pins this with a probe object whose `__wakeup()`
would set a static flag; the flag stays false because instantiation never
happens.

## Defense-in-depth checklist

- Keep `httponly => true` (the default) so JavaScript cannot read the cookie —
  this limits XSS-based theft.
- Use `secure => true` in production over HTTPS (and `samesite => 'None'` only
  when you genuinely need cross-site sends, which forces `secure`). See
  [Configuration](Configuration.md#samesite-normalized-validated-coupled-to-secure).
- Keep the salt out of source control and rotate it if you suspect it leaked —
  rotation invalidates every issued cookie.
- Store opaque references in the cookie and keep sensitive data server-side.
- Keep the payload well under the ~4 KB cookie limit; store bulk data
  server-side (see the [FAQ](FAQ.md)).

## Common mistakes

- **Treating the cookie as private storage.** Values are readable by the
  client. Use the signature for integrity, not confidentiality.
- **Hard-coding the salt in the repository.** Anyone with repo access can then
  forge cookies. Use an environment secret.
- **Rotating the salt without expecting logouts.** Every existing cookie
  becomes unreadable. Plan rotations.
- **Assuming `final` or `private` makes data secret.** Those are
  design/encapsulation choices, not a confidentiality boundary. The wire
  format is plain (signed) serialization.

## See also

- [Configuration](Configuration.md) — `secure`, `httponly`, `samesite` attributes.
- [Recipe: Remember Me](Recipe-Remember-Me.md) — store a reference, not the identity.
- [Testing](Testing.md) — craft tampered/expired/wrong-salt input by hand.
- [Migration (v1 → v2)](Migration-from-1.x.md) — the HMAC format change and what it invalidates.
- [FAQ](FAQ.md) — "can the client read my values?", salt rotation, size limits.
