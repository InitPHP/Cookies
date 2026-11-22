# Recipe: Consent Cookie

Store a long-lived cookie-consent / preference flag, read it to gate analytics
or other optional features, and let the user withdraw consent. The flag is not
secret — only whether the user agreed — so a signed (not encrypted) cookie is
a good fit: the signature stops a client from silently flipping the flag.

## Why a signed flag is appropriate here

A consent flag is a low-stakes, **readable** value. The HMAC signature still
earns its keep: it makes the stored decision tamper-evident, so the value your
server reads back is the one your server wrote, not one a client edited. The
client can *see* `consent=true` — that is fine; what matters is it cannot forge
a different decision that your code then trusts. See the
[Security Model](Security-Model.md).

## Recording consent

Use a dedicated, long-lived cookie. A consent decision typically lasts a year,
so set both the transport `ttl` and the per-key `$ttl` to a year:

```php
use InitPHP\Cookies\Cookie;

function recordConsent(bool $analytics): void
{
    $oneYear = 365 * 86400;

    $cookie = new Cookie('consent', getenv('COOKIE_SALT'), [
        'ttl'      => $oneYear,
        'secure'   => true,
        'httponly' => false, // a banner script may need to read it; see the note below
        'samesite' => 'Lax',
    ]);

    // Store the decision. A bool round-trips as a real bool.
    $cookie->set('analytics', $analytics, $oneYear);
    $cookie->set('decided_at', time(), $oneYear);

    $cookie->send(); // before any output
}

// From the banner's "Accept" / "Reject" handler:
recordConsent(true);  // user accepted analytics
recordConsent(false); // user declined
```

> **`httponly` and consent banners.** The default is `httponly => true`, which
> hides the cookie from JavaScript. If a client-side banner needs to read the
> decision to decide whether to render, set `httponly => false` as above.
> Because the value is non-sensitive (a yes/no flag), exposing it to JavaScript
> is an acceptable trade-off. Keep `httponly => true` if only your server reads
> the flag. See [Configuration](Configuration.md#secure-and-httponly).

## Gating analytics on the stored decision

Use [`has()`](API-Reference.md#has) to tell "no decision yet" from a stored
decision, and [`get()`](API-Reference.md#get) to read the boolean. Remember that
`get('analytics')` returns `false` both when the user declined and when the key
is absent — so check `has()` first if "undecided" matters:

```php
use InitPHP\Cookies\Cookie;

function analyticsAllowed(): bool
{
    $cookie = new Cookie('consent', getenv('COOKIE_SALT'), [
        'secure'   => true,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);

    // No decision recorded yet → treat as "not allowed" and show the banner.
    if (!$cookie->has('analytics')) {
        return false;
    }

    return (bool) $cookie->get('analytics');
}

if (analyticsAllowed()) {
    // load analytics tags / write tracking cookies
} else {
    // render the consent banner, or simply do nothing
}
```

| State | `has('analytics')` | `get('analytics')` | Meaning |
| --- | --- | --- | --- |
| Cookie absent / expired | `false` | `null` (or your default) | No decision — show the banner |
| User accepted | `true` | `true` | Analytics allowed |
| User declined | `true` | `false` | Analytics not allowed |

## Withdrawing consent

Two options, depending on whether you want the cookie to linger as an explicit
"declined" record or to disappear entirely.

### Option A — flip the flag (keep an explicit record)

Overwrite the decision so a later read sees `analytics => false`:

```php
recordConsent(false); // writes analytics => false, keeps the cookie
```

This is usually the better choice: the cookie now positively records "the user
declined", which prevents the banner from reappearing on every page.

### Option B — remove the flag (forget the decision)

If withdrawal should mean "forget my choice and ask again", remove the keys (or
delete the whole cookie) and send:

```php
use InitPHP\Cookies\Cookie;

$cookie = new Cookie('consent', getenv('COOKIE_SALT'), [
    'secure'   => true,
    'httponly' => false,
    'samesite' => 'Lax',
]);

// Remove the specific keys...
$cookie->remove('analytics', 'decided_at');
$cookie->send();

// ...or delete the cookie outright (matching the issuing path/domain):
$cookie->destroy();
$cookie->send(); // no-op after destroy(), but harmless
```

After Option B, `has('analytics')` is `false` again, so your gate treats the
user as undecided and the banner returns. See
[Reading & Removing](Reading-and-Removing.md#flush-vs-destroy) for `remove` vs.
`destroy`.

## Common pitfalls

- **Reading `get('analytics')` without `has()`.** A declined user and an
  undecided user both yield `false`. Use `has()` when "undecided" must trigger
  the banner.
- **A per-key TTL longer than the transport `ttl`.** If the cookie's transport
  `ttl` is shorter than your per-key year, the browser drops the whole cookie
  first and the decision is lost early. Set both to the same lifetime. See
  [TTL & Expiry](TTL-and-Expiry.md#footgun-per-key-ttl-longer-than-the-transport-ttl).
- **Storing personal data in the consent cookie.** Keep it to the decision
  flags and a timestamp. The cookie is readable; do not put anything sensitive
  in it. See the [Security Model](Security-Model.md#integrity-is-not-secrecy).
- **Forgetting `send()`.** Recording and withdrawing both require a `send()`
  before output. See [Sending & Lifecycle](Sending-and-Lifecycle.md).

## See also

- [Basic Usage](Basic-Usage.md#existence-checks) — `has()` vs. `get()` for booleans.
- [TTL & Expiry](TTL-and-Expiry.md) — matching the per-key and transport lifetimes.
- [Configuration](Configuration.md) — `secure`, `httponly`, `samesite` for this cookie.
- [Reading & Removing](Reading-and-Removing.md) — `remove` vs. `destroy` for withdrawal.
