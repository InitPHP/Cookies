# Recipe: Flash Messages

Show a one-time message after a redirect (the classic "Saved successfully!"
banner) using a short per-key TTL and `pull()` so the message is read exactly
once.

## Why `pull` and a short TTL

A flash message should appear on the **next** request and then vanish. Two
mechanisms combine here:

- [`pull()`](Reading-and-Removing.md#get-vs-pull) reads the value and removes it
  in the same call, so the message cannot be shown twice.
- A short per-key TTL is a safety-net: if the user never lands on the page that
  reads the flash (closed the tab, etc.), the message expires on its own
  instead of lingering. See [TTL & Expiry](TTL-and-Expiry.md).

## Writing a flash before a redirect

```php
use InitPHP\Cookies\Cookie;

function flash(string $type, string $message): void
{
    $cookie = new Cookie('app_session', getenv('COOKIE_SALT'));

    // 60s is plenty to survive a redirect; it self-cleans if unread.
    $cookie->set('flash_type', $type, 60);
    $cookie->set('flash_message', $message, 60);

    $cookie->send(); // before the redirect header / any output
}

// In a controller after a successful action:
flash('success', 'Your profile was saved.');
header('Location: /profile');
```

## Reading it once on the next request

```php
use InitPHP\Cookies\Cookie;

function consumeFlash(): ?array
{
    $cookie = new Cookie('app_session', getenv('COOKIE_SALT'));

    if (!$cookie->has('flash_message')) {
        return null;
    }

    $flash = [
        'type'    => $cookie->pull('flash_type', 'info'),
        'message' => $cookie->pull('flash_message'),
    ];

    // pull() removed both keys from the working copy; persist that.
    $cookie->send();

    return $flash;
}

// In your view layer:
$flash = consumeFlash();
if ($flash !== null) {
    // render the banner with $flash['type'] and $flash['message']
}
```

Output for a single round trip:

```
['type' => 'success', 'message' => 'Your profile was saved.']
```

A second `consumeFlash()` on a later request returns `null` — `pull()` already
removed the keys.

## A small flash helper

Wrapping the two calls keeps controllers tidy. Type-hint
[`CookieInterface`](API-Reference.md#cookieinterface) so the helper is easy to
test:

```php
use InitPHP\Cookies\CookieInterface;

final class FlashBag
{
    public function __construct(private CookieInterface $cookie) {}

    public function add(string $message, string $type = 'info', int $ttl = 60): void
    {
        $this->cookie->set('flash_message', $message, $ttl);
        $this->cookie->set('flash_type', $type, $ttl);
    }

    /** @return array{type: string, message: string}|null */
    public function consume(): ?array
    {
        if (!$this->cookie->has('flash_message')) {
            return null;
        }

        return [
            'type'    => (string) $this->cookie->pull('flash_type', 'info'),
            'message' => (string) $this->cookie->pull('flash_message'),
        ];
    }
}
```

Remember to `send()` the underlying `Cookie` once per request (after `add()`
on the writing request, after `consume()` on the reading request). See
[Testing](Testing.md) for wiring `FlashBag` to a recording writer in tests.

## Common pitfalls

- **Using `get()` instead of `pull()`.** `get()` leaves the message in place,
  so it shows again on the next page load until the TTL expires.
- **Forgetting `send()` after `consume()`.** `pull()` removes the keys from the
  working copy, but the browser still holds the old values until you `send()`
  the emptied state.
- **TTL too short.** If the redirect or round trip can take longer than the TTL
  (slow networks, intermediate auth), the flash may expire before it is read. A
  minute is a safe default.
- **Storing rich data.** Flash values are scalars only. To carry structured
  data, `json_encode()` it into a string and decode on read. See
  [Basic Usage](Basic-Usage.md#allowed-value-types).

## See also

- [Reading & Removing](Reading-and-Removing.md) — `pull` vs. `get`.
- [TTL & Expiry](TTL-and-Expiry.md) — the short per-key TTL safety-net.
- [Recipe: Remember Me](Recipe-Remember-Me.md) — a long-lived counterpart.
