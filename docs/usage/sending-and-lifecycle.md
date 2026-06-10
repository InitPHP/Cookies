# Sending and lifecycle

Goal: understand when data actually reaches the browser, why `send()`
must run before output, the no-op semantics that make `send()` cheap to
call, and why the destructor safety-net is not a substitute for an
explicit `send()`.

## Staged writes

Every mutating method — `set`, `setArray`, `push`, `remove`, `flush` —
changes only an in-memory working copy and sets an internal
"changed" flag. None of them touch the browser:

```php
use InitPHP\Cookies\Cookie;

$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));

$cookie->set('user_id', 42);   // staged, nothing written yet
$cookie->set('theme', 'dark'); // staged
// ... still nothing on the wire ...
```

A single `send()` then serializes the non-expired working copy, signs
it, and hands it to the writer (native `setcookie()` by default):

```php
$cookie->send(); // one Set-Cookie header for 'app_session'
```

Because every value lives in one signed cookie, you pay for exactly one
`Set-Cookie` header no matter how many values you staged.

## `send()` is a no-op when nothing changed

`send()` short-circuits and returns `true` without writing when nothing
has changed since the last send. This makes it safe to call defensively
and idempotent until the next mutation:

```php
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));
$cookie->send(); // no mutation occurred → no-op, returns true, writes nothing

$cookie->set('a', '1');
$cookie->send(); // writes once
$cookie->send(); // no-op — nothing changed since the previous send
```

Loading a valid, unchanged incoming cookie does not flag a change, so a
read-only request issues no `Set-Cookie` at all. (A *tampered* or
malformed incoming cookie *does* flag a change, so a clean replacement
is re-issued — see the [security model](../security-model.md).)

`send()` returns the writer's success boolean. With the default native
writer that reflects whether `setcookie()` succeeded; a no-op returns
`true`.

## Headers before output

`send()` writes an HTTP response header. Like PHP's native
`setcookie()`, it only works **before any output** has been sent — no
`echo`, no rendered HTML, no whitespace or BOM outside the PHP tags.
Once the body has started, the header is dropped (and `setcookie()`
would normally emit a "headers already sent" warning).

The recommended pattern is to finish all cookie mutations and call
`send()` at the end of request handling, *before* you render:

```php
// 1. Handle the request, mutate cookies as needed.
$cookie->set('last_seen', time());

// 2. Flush cookies to headers.
$cookie->send();

// 3. Only now produce output.
echo $renderedHtml;
```

In a PSR-7 / middleware stack, run `send()` (or your own writer) while
you still control the headers, before the response body is emitted.

## The destructor safety-net

When the `Cookie` instance is destroyed (end of request, or when it
goes out of scope), its destructor calls `send()` once. This is a
safety-net so pending changes are not silently lost if you forget to
flush:

```php
function handle(): void
{
    $cookie = new Cookie('app_session', getenv('COOKIE_SALT'));
    $cookie->set('a', '1');
    // No explicit send(). When $cookie is destroyed at the end of the
    // function, the destructor calls send() for you.
}
```

If you already called `send()` and nothing changed afterward, the
destructor's `send()` is a no-op, so there is no double write.

### Why not rely on the destructor?

- **Output may already be flushed by then.** The destructor often runs
  during shutdown, after your response body has been sent. At that
  point the header is too late and the write is lost.
- **The native writer suppresses the warning.** The default writer
  prefixes `setcookie()` with `@`, specifically so the destructor
  cannot emit a "headers already sent" warning during shutdown. That
  means a too-late write fails *silently* — you would not see an error,
  only a missing cookie.

Treat the destructor as a seatbelt, not a strategy. Call `send()`
explicitly while you still control the headers.

## Lifecycle at a glance

```
construct ── decode + verify incoming cookie ──► working copy
   │
   ├─ set / setArray / push / remove / flush ──► mutate working copy, mark changed
   │
   ├─ send() ──► if changed: serialize → sign → write; else no-op
   │
   ├─ destroy() ──► write deletion cookie now, clear working copy, send() becomes a no-op
   │
   └─ __destruct() ──► send() safety-net (no-op if already sent)
```

## Common mistakes

- **`send()` after output.** The single most common cause of "my cookie
  isn't set". Move `send()` before any `echo`/render. See the
  [FAQ](../faq.md).
- **Leaning on the destructor.** It can fire after output, where the
  write silently fails. Call `send()` explicitly.
- **Expecting a write on a read-only request.** If you only read a
  valid cookie, nothing changed, so `send()` is a no-op by design.
- **Calling `send()` after `destroy()`** and expecting a second write —
  `destroy()` resets the changed flag, so `send()` does nothing.
