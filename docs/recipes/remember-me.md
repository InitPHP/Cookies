# Recipe: remember me

Goal: issue a long-lived "remember me" login cookie that survives
across sessions, using the signature for tamper-evidence and a
server-side token store for the actual authentication.

## The pattern: store a reference, not the identity

The cookie is signed but **not encrypted** — the client can read its
contents (see the [security model](../security-model.md)). So the
cookie must not carry anything that is sufficient to impersonate a user
on its own (no raw user ID you trust blindly, no password hash). Store
an **opaque, random selector + validator** and keep the authority
server-side:

- **selector** — a random lookup key, stored in the cookie and in your
  database.
- **validator** — a random secret; the cookie holds the raw value, the
  database holds only its hash. On login you re-hash the cookie's
  validator and compare against the stored hash.

The HMAC signature stops a client from forging or editing the cookie;
the server-side store is what actually grants the session.

## Issuing the cookie at login

```php
use InitPHP\Cookies\Cookie;

function issueRememberMe(int $userId, TokenStore $store): void
{
    $selector  = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));

    // Persist selector + HASHED validator server-side, tied to the user.
    $store->save(
        selector: $selector,
        userId: $userId,
        validatorHash: hash('sha256', $validator),
        expiresAt: time() + (30 * 86400)
    );

    // The transport cookie lives 30 days; mark it Secure + HttpOnly.
    $cookie = new Cookie('remember_me', getenv('COOKIE_SALT'), [
        'ttl'      => 30 * 86400,
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax', // Lax so it is sent on top-level navigation
    ]);

    // Per-key TTL matches the transport lifetime; null would also work here.
    $cookie->set('selector', $selector, 30 * 86400);
    $cookie->set('validator', $validator, 30 * 86400);

    $cookie->send(); // before any output
}
```

## Validating on a later request

```php
use InitPHP\Cookies\Cookie;

function loginFromRememberMe(TokenStore $store): ?int
{
    $cookie = new Cookie('remember_me', getenv('COOKIE_SALT'), [
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // A tampered or salt-mismatched cookie reads as absent here — the
    // signature check already discarded it during construction.
    if (!$cookie->has('selector') || !$cookie->has('validator')) {
        return null;
    }

    $selector  = (string) $cookie->get('selector');
    $validator = (string) $cookie->get('validator');

    $record = $store->find($selector);
    if ($record === null || $record->expiresAt < time()) {
        $cookie->destroy(); // stale/unknown — delete the browser cookie
        $cookie->send();
        return null;
    }

    // Constant-time comparison of the re-hashed validator.
    if (!hash_equals($record->validatorHash, hash('sha256', $validator))) {
        // Validator mismatch with a known selector can indicate theft —
        // invalidate the whole series server-side.
        $store->revoke($selector);
        $cookie->destroy();
        $cookie->send();
        return null;
    }

    return $record->userId;
}
```

## Logging out

Delete the cookie from the browser and revoke the server-side record:

```php
$store->revoke($selector);

$cookie = new Cookie('remember_me', getenv('COOKIE_SALT'), [
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
$cookie->destroy(); // immediate deletion cookie
$cookie->send();
```

Use the **same** `path`/`domain` options you issued the cookie with, or
the browser will not match and delete it. See
[Configuration](../configuration.md).

## Common pitfalls

- **Putting the user ID alone in the cookie.** It is readable and, on
  its own, trivially swappable in intent — only the signature stops the
  edit, and you still would not want to leak the ID. Use an opaque
  selector + a server-side store.
- **Storing the raw validator in the database.** Store its hash; the
  cookie holds the raw value. A database leak then does not hand out
  usable tokens.
- **`SameSite=Strict` for remember-me.** `Strict` is not sent on
  cross-site top-level navigation, so a user arriving from an external
  link would appear logged out. `Lax` is the usual choice.
- **Calling `destroy()` with different options than you issued.** The
  browser keeps the cookie. Match `path`/`domain`.
- **Forgetting `send()`.** Issuing, refreshing, and destroying all
  require a `send()` before output. See
  [Sending and lifecycle](../usage/sending-and-lifecycle.md).
