# Security model

Goal: understand precisely what the signature protects, what it does
**not** protect, how to manage the salt, and why a tampered cookie can
never trigger PHP object injection.

## What gets written

The browser cookie value is two parts joined by a dot:

```
{payload} . "." . {signature}

payload   = base64(serialize(entries))
signature = hash_hmac('sha256', payload, salt)
```

1. The non-expired working copy (`entries`) is `serialize()`-d and
   base64-encoded into a `payload`.
2. The payload is signed: `signature = hash_hmac('sha256', payload, salt)`.
3. The cookie value is `"{payload}.{signature}"`.

The dot is a safe delimiter because neither standard base64 output nor a
hex HMAC digest can contain a dot.

## What the signature protects

On every construction, the incoming cookie is verified **before**
anything is deserialized:

1. The value is split into `payload` and `signature` on the first dot.
2. The expected signature is recomputed from the payload and the salt.
3. It is compared with the stored signature using `hash_equals()`
   (constant-time, so signature comparison does not leak timing
   information).

If the value has no delimiter, the signature does not match, the base64
is invalid, or the deserialized result is not an array, the payload is
**discarded** and the manager starts from an empty state. When the
incoming value was malformed or tampered (rather than simply absent),
the state is flagged as changed so the next `send()` re-issues a clean,
correctly signed cookie.

This gives you **integrity** and **authenticity**: a client cannot
change a value, extend an expiry, or inject a new key without knowing
the salt, because any edit invalidates the signature.

```php
use InitPHP\Cookies\Cookie;

// An attacker who flips even one byte of the payload...
$tampered = $forgedPayload . '.' . $stolenSignature;

$cookie = new Cookie('app_session', $salt, [], [$name => $tampered]);
$cookie->get('user'); // null — verification failed, payload discarded
$cookie->all();       // []
```

A signature forged under a guessed/wrong salt is likewise rejected:

```php
// Built by an attacker without the real salt — does not verify.
$cookie->get('admin'); // null
```

## What the signature does NOT protect

**The signature is not encryption.** The payload is base64 of a plain
PHP serialization — anyone can decode and read it. Signing proves the
data was issued by you and has not been altered; it does **not** hide
the values.

Consequences:

- **The client can read every value.** Do not store secrets, password
  hashes, API keys, internal IDs you would not expose, or PII you would
  not put in a URL.
- **Store references, not secrets.** Keep a session ID or an opaque
  token in the cookie and hold the sensitive data server-side, keyed by
  that token. See the [remember-me recipe](recipes/remember-me.md).

> Integrity ≠ secrecy. If you need confidentiality, encrypt the value
> yourself before storing it (and keep the encryption key server-side),
> or keep the data on the server.

## Salt management

The salt is the HMAC key. Everything above depends on keeping it secret
and stable.

- **Secret.** Anyone who knows the salt can forge valid cookies for any
  payload. Store it like a database password — in an environment
  variable or a secrets manager, never in version control.
- **Stable.** If the salt changes, every previously issued cookie fails
  verification and is replaced by an empty one. Users lose their stored
  values. This is expected behavior, and it doubles as an effective
  "invalidate every cookie" switch — rotate the salt deliberately, with
  awareness of the consequence (see the [FAQ](faq.md)).
- **Per-name, per-secret isolation.** Different salts produce mutually
  unreadable cookies; a cookie signed with salt A is rejected by a
  manager using salt B.
- **Non-empty.** The constructor trims the salt and throws
  `CookieInvalidArgumentException` if it is empty.

The constructor also trims the name and salt, so a value padded with
surrounding whitespace signs and verifies the same as its trimmed form
— `'  my-salt  '` and `'my-salt'` are interchangeable.

## Object-injection hardening

Deserializing attacker-controlled data is the classic PHP object
injection vector. This package closes it two ways:

1. **Deserialization runs only after the signature check.** An attacker
   who does not know the salt cannot get their payload past
   verification, so their serialized blob never reaches `unserialize()`
   at all.
2. **Even a correctly signed payload cannot instantiate objects.**
   Deserialization uses `unserialize($data, ['allowed_classes' => false])`,
   so any serialized object is decoded as `__PHP_Incomplete_Class` and
   no class is instantiated — no `__wakeup()`, no `__destruct()`, no
   gadget chain fires.

```php
// A correctly signed payload smuggling a serialized object still
// cannot wake it: allowed_classes => false prevents instantiation.
// The object is not a valid entry, so it reads as absent.
$cookie->get('x'); // null — no object was ever constructed
```

base64 decoding is also strict (`base64_decode($payload, true)`), so a
payload with invalid base64 characters is rejected rather than silently
truncated.

## Defense-in-depth checklist

- Keep `httponly => true` (the default) so JavaScript cannot read the
  cookie — this limits XSS-based theft.
- Use `secure => true` (and `samesite => 'None'` only when you genuinely
  need cross-site sends, which forces `secure`) in production over HTTPS.
- Keep the salt out of source control and rotate it if you suspect it
  leaked — rotation invalidates every issued cookie.
- Store opaque references in the cookie and keep sensitive data
  server-side.

## Common mistakes

- **Treating the cookie as private storage.** Values are readable by
  the client. Use the signature for integrity, not confidentiality.
- **Hard-coding the salt in the repository.** Anyone with repo access
  can then forge cookies. Use an environment secret.
- **Rotating the salt without expecting logouts.** Every existing
  cookie becomes unreadable. Plan rotations.
- **Assuming `final` or `private` makes data secret.** Those are
  design/encapsulation choices, not a confidentiality boundary. The
  wire format is plain (signed) serialization.
