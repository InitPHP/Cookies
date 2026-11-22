# Basic Usage

Store and read scalar values through `set`, `get`, `has` and `remove`, and
understand exactly which value types are allowed.

Every example assumes a constructed manager:

```php
use InitPHP\Cookies\Cookie;

$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));
```

## Writing values

[`set()`](API-Reference.md#set) stages a single value in the in-memory working
copy and returns the manager, so writes chain. Nothing reaches the browser
until [`send()`](Sending-and-Lifecycle.md).

```php
$cookie
    ->set('user_id', 42)
    ->set('theme', 'dark')
    ->set('logged_in', true);
```

Setting an existing key overwrites its value:

```php
$cookie->set('user', 'ada');
$cookie->set('user', 'grace');
$cookie->get('user'); // 'grace'
```

## Reading values

[`get()`](API-Reference.md#get) returns the stored value, or `$default` when the
key is absent or expired:

```php
$cookie->set('user', 'ada');

$cookie->get('user');                // 'ada'
$cookie->get('missing');             // null
$cookie->get('missing', 'fallback'); // 'fallback'
```

## Existence checks

[`has()`](API-Reference.md#has) is the authoritative existence check. It returns
`true` only when a non-expired value is stored. Note that `false`, `0`, `0.0`
and `''` are all real, present values:

```php
$cookie->set('flag', false);

$cookie->has('flag');            // true  — the key exists
$cookie->get('flag');            // false — the stored value
$cookie->get('flag', 'default'); // false — NOT 'default', the key is present
```

Use `has()` (not `get() === null`) to distinguish "absent" from a stored
`false`/`0`/`''`, because those values are indistinguishable from "absent" if
you only look at `get()` without a default.

## Removing values

[`remove()`](API-Reference.md#remove) stages the removal of one or more keys,
returns the manager, and ignores missing keys:

```php
$cookie->setArray(['a' => '1', 'b' => '2', 'c' => '3']);

$cookie->remove('a');              // removes 'a'
$cookie->remove('b', 'c');         // removes both
$cookie->remove('does-not-exist'); // silent no-op
$cookie->remove();                 // no arguments → no-op, state unchanged
```

See [Reading & Removing](Reading-and-Removing.md) for `pull`, `all`, `flush` and
`destroy`.

## Scalar type preservation

Values survive a full browser round trip with their PHP type intact — an
`int` comes back an `int`, a `bool` comes back a `bool`, a `float` comes back
a `float`:

```php
$cookie->set('int', 42);
$cookie->set('float', 1.5);
$cookie->set('boolTrue', true);
$cookie->set('boolFalse', false);
$cookie->set('string', 'hello');
$cookie->set('zip', '01234'); // numeric string, kept as a string
$cookie->send();

// On the next request:
$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));

$cookie->get('int');       // 42      (int)
$cookie->get('float');     // 1.5     (float)
$cookie->get('boolTrue');  // true    (bool)
$cookie->get('boolFalse'); // false   (bool)
$cookie->get('string');    // 'hello' (string)
$cookie->get('zip');       // '01234' (string — leading zero preserved)
```

Type fidelity comes from `serialize()`/`unserialize()` under the hood, so
there is no string coercion the way a raw browser cookie would force.

## Allowed value types

Only `string`, `bool`, `int`, `float` and numeric strings are accepted.
Anything else throws
[`CookieInvalidArgumentException`](Exceptions.md):

```php
$cookie->set('ok', 'text'); // fine
$cookie->set('ok', 3.14);   // fine
$cookie->set('ok', true);   // fine

$cookie->set('bad', ['a', 'b']);   // throws — array
$cookie->set('bad', null);         // throws — null is not a scalar value
$cookie->set('bad', new stdClass); // throws — object
```

To store structured data, serialize it into a string yourself (for example
`json_encode()`), store that string, and decode it on read:

```php
$cookie->set('prefs', json_encode(['theme' => 'dark', 'lang' => 'en']));
// ...later...
$prefs = json_decode((string) $cookie->get('prefs', '{}'), true);
```

Keep the total payload small — a single cookie is capped at roughly 4 KB by
browsers. See the [FAQ](FAQ.md).

## Common mistakes

- **Storing `null`.** `null` is not an accepted value type; it throws. Remove
  a key instead of setting it to `null`.
- **Expecting `get()` to tell present from `false`.** A stored `false` and an
  absent key both return `false` from `get()` (unless you pass a default).
  Use `has()` to distinguish them.
- **Forgetting to `send()`.** `set()`/`remove()` only mutate memory. Without
  `send()` (or the destructor), nothing is written. See
  [Sending & Lifecycle](Sending-and-Lifecycle.md).
- **Treating the cookie as private.** Values are readable by the client. The
  signature prevents forgery, not reading — see the [Security Model](Security-Model.md).

## See also

- [TTL & Expiry](TTL-and-Expiry.md) — give a value its own lifetime.
- [Reading & Removing](Reading-and-Removing.md) — `pull`, `all`, `flush`, `destroy`.
- [Exceptions](Exceptions.md) — every condition that throws.
- [API Reference](API-Reference.md) — the full method list.
