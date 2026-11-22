# Reading & Removing

Choose the right read method (`get` vs. `pull`, plus `all`) and the right
deletion method (`remove`, `flush`, `destroy`).

Every example assumes a constructed manager:

```php
use InitPHP\Cookies\Cookie;

$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));
```

## `get` vs. `pull`

[`get()`](API-Reference.md#get) reads the value and leaves it in place:

```php
$cookie->set('user', 'ada');

$cookie->get('user'); // 'ada'
$cookie->get('user'); // 'ada' — still there
```

[`pull()`](API-Reference.md#pull) reads the value **once** and then removes it.
It behaves like `get()` followed by `remove()`, and the removal happens
whether or not the key existed:

```php
$cookie->set('flash', 'Saved!');

$cookie->pull('flash');        // 'Saved!'
$cookie->has('flash');         // false — pull removed it
$cookie->get('flash', 'gone'); // 'gone'

$cookie->pull('never_set', 'fb'); // 'fb' — default returned, removal is a safe no-op
```

Use `pull()` for read-once data such as flash messages and one-time tokens.
See the [Flash Messages recipe](Recipe-Flash-Messages.md).

## `all`

[`all()`](API-Reference.md#all) returns every non-expired value as a
`key => value` map. Expired entries are excluded (and removed as a side
effect):

```php
$cookie->setArray(['a' => '1', 'b' => '2']);
$cookie->set('temp', 'x', 3600);

$cookie->all(); // ['a' => '1', 'b' => '2', 'temp' => 'x']
```

`all()` returns values, not the internal `['value' => ..., 'ttl' => ...]`
entries — you get the same scalars `get()` would return.

## `remove`: stage a deletion

[`remove()`](API-Reference.md#remove) stages the removal of one or more keys and
returns the manager. Missing keys are harmless; calling it with no arguments
is a no-op that does **not** mark the state changed:

```php
$cookie->setArray(['a' => '1', 'b' => '2', 'c' => '3']);

$cookie->remove('a');              // single key
$cookie->remove('b', 'c');         // multiple keys
$cookie->remove('does-not-exist'); // no-op, harmless
$cookie->remove();                 // no-op, state untouched

$cookie->all(); // []
```

Like every mutation, `remove()` only changes the working copy; the deletion
reaches the browser on the next [`send()`](Sending-and-Lifecycle.md).

## `flush` vs. `destroy`

Both clear all values, but they differ in *what they tell the browser*.

### `flush()`: empty, but keep the cookie

[`flush()`](API-Reference.md#flush) clears every value and marks the state
changed. The next `send()` writes an **empty, still-signed** cookie — the
cookie remains in the browser (with its normal lifetime), it just carries no
entries:

```php
$cookie->setArray(['a' => '1', 'b' => '2']);
$cookie->flush(); // working copy is now empty
$cookie->all();   // []
$cookie->send();  // writes an empty signed cookie to the browser
```

`flush()` returns `true`. It does not write on its own — it needs a following
`send()` (or the destructor).

### `destroy()`: delete the cookie immediately

[`destroy()`](API-Reference.md#destroy) writes a deletion cookie to the browser
**right away** (a past expiry, carrying the configured `path` and `domain`)
and clears the working copy. After `destroy()`, a subsequent `send()` is a
no-op:

```php
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'), ['path' => '/app']);
$cookie->set('a', '1');

$cookie->destroy(); // browser is told to delete 'app_session' on path '/app'
$cookie->all();     // []
$cookie->send();    // no-op — nothing further is written
```

`destroy()` returns the underlying writer's success boolean.

> **Match the scope.** A browser only deletes a cookie when the deletion
> request carries the same `path`/`domain` the cookie was issued with. If you
> set the cookie with a custom `path` or `domain`, construct the manager with
> the **same** options before calling `destroy()`, or the browser keeps the
> old cookie. See [Configuration](Configuration.md#path-and-domain-also-used-by-destroy).

### Which to use

| Need | Method |
| --- | --- |
| Wipe values but keep using the cookie this request | `flush()` then `send()` |
| Log the user out / delete the cookie from the browser | `destroy()` |

## Common mistakes

- **Calling `pull()` twice.** The second call returns the default — `pull()`
  removes the value on the first read.
- **Expecting `flush()` to delete the browser cookie.** It writes an *empty*
  cookie, not a deletion. Use `destroy()` to remove it.
- **Calling `send()` after `destroy()` and expecting another write.**
  `destroy()` already wrote and reset the changed flag, so the later `send()`
  does nothing.
- **Forgetting `flush()` needs a `send()`.** `flush()` only stages the
  emptied state. Without `send()` (or the destructor), the browser still
  holds the old values.
- **Calling `destroy()` with different `path`/`domain` than you issued.** The
  browser will not match and delete it. Use the same options.

## See also

- [Sending & Lifecycle](Sending-and-Lifecycle.md) — when staged changes actually reach the browser.
- [Configuration](Configuration.md) — how `path`/`domain` flow into `destroy()`.
- [Recipe: Flash Messages](Recipe-Flash-Messages.md) — `pull` in practice.
- [API Reference](API-Reference.md) — full signatures.
