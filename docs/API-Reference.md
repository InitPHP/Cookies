# API Reference

The full surface of `InitPHP\Cookies\Cookie` and
`InitPHP\Cookies\CookieInterface`. Behavioral deep-dives are linked into the
[Core Usage](Basic-Usage.md) section.

> Signatures below show the intended types for readability (`mixed`, and the
> union `string|int|float|bool` for values). In the source, `get()`, `pull()`,
> `set()`, `push()` and their interface declarations carry those types in
> PHPDoc rather than in the native signature.

`Cookie` is `final`. Depend on `CookieInterface` and compose, rather than
subclass — see [why is `Cookie` final?](FAQ.md#why-is-cookie-final).

---

## `CookieInterface`

The contract for a signed, tamper-evident cookie manager. Type-hint this in
your services so a test (or an alternative implementation) can be substituted
without touching consumer code.

```php
namespace InitPHP\Cookies;

interface CookieInterface
{
    public function has(string $key): bool;
    public function get(string $key, mixed $default = null): mixed;
    public function pull(string $key, mixed $default = null): mixed;
    public function set(string $key, string|int|float|bool $value, ?int $ttl = null): self;
    public function setArray(array $assoc, ?int $ttl = null): self;
    public function push(string $key, string|int|float|bool $value, ?int $ttl = null): mixed;
    public function all(): array;
    public function remove(string ...$key): self;
    public function send(): bool;
    public function flush(): bool;
    public function destroy(): bool;
}
```

---

## `Cookie`

The default concrete implementation (`final`).

### Constructor

```php
public function __construct(
    string $name,
    string $salt,
    array $options = [],
    ?array $source = null,
    ?callable $writer = null
);
```

- **`$name`** — the browser cookie name. Trimmed; must be non-empty.
- **`$salt`** — the HMAC secret used to sign/verify the payload. Trimmed; must
  be non-empty. Keep it secret and stable. See the [Security Model](Security-Model.md#salt-management).
- **`$options`** — cookie attribute overrides; only the keys you pass change.
  See [Configuration](Configuration.md).
- **`$source`** — raw cookie source the payload is decoded from. Defaults to
  `$_COOKIE`. Injectable for testing.
- **`$writer`** — low-level writer
  `callable(string $name, string $value, array $options): bool`. Defaults to a
  wrapper over native `setcookie()`. Injectable for testing.
- **Throws** [`CookieInvalidArgumentException`](Exceptions.md) if `$name` or
  `$salt` is empty after trimming.

```php
use InitPHP\Cookies\Cookie;

$cookie = new Cookie('app_session', getenv('COOKIE_SALT'));
```

### `has`

```php
public function has(string $key): bool;
```

`true` only when a non-expired value is stored for `$key`. Reading an expired
key removes it as a side effect and returns `false`. See
[Basic Usage](Basic-Usage.md#existence-checks).

```php
$cookie->set('user', 'ada');
$cookie->has('user');    // true
$cookie->has('missing'); // false
```

### `get`

```php
public function get(string $key, mixed $default = null): mixed;
```

Returns the stored value, or `$default` when the key is absent or expired (an
expired entry is removed first). Scalar types are preserved across a round
trip. See [Basic Usage](Basic-Usage.md#scalar-type-preservation).

```php
$cookie->set('age', 42);
$cookie->get('age');            // 42 (int)
$cookie->get('missing', 'n/a'); // 'n/a'
```

### `pull`

```php
public function pull(string $key, mixed $default = null): mixed;
```

Like `get()`, but always removes the entry afterward (read-once), whether or
not it existed. See [Reading & Removing](Reading-and-Removing.md#get-vs-pull).

```php
$cookie->set('flash', 'Saved!');
$cookie->pull('flash'); // 'Saved!'
$cookie->has('flash');  // false
```

### `set`

```php
public function set(string $key, string|int|float|bool $value, ?int $ttl = null): self;
```

Stages a single value in the working copy and returns the manager. `$value`
must be `string`, `bool`, `int`, `float` or a numeric string. `$ttl` is
seconds from now (`null` = no per-key expiry; `0` throws; a negative value is
normalized via `abs()`). See [TTL & Expiry](TTL-and-Expiry.md).

- **Throws** [`CookieInvalidArgumentException`](Exceptions.md) if `$value` is not
  a supported scalar or `$ttl` is zero.

```php
$cookie->set('user_id', 42)
       ->set('promo_seen', true, 3600);
```

### `setArray`

```php
public function setArray(array $assoc, ?int $ttl = null): self;
```

Stages several values from an **associative** array that share one `$ttl`.
Every key must be a string; every value must be a supported scalar. An empty
array is a no-op (it does not mark the state changed). Returns the manager.

- **Throws** [`CookieInvalidArgumentException`](Exceptions.md) if a key is not a
  string (e.g. a list with integer keys), a value is not scalar, or `$ttl` is
  zero.

```php
$cookie->setArray(['theme' => 'dark', 'lang' => 'en']);
$cookie->setArray(['a' => '1', 'b' => '2'], 60); // shared 60s TTL
```

### `push`

```php
public function push(string $key, string|int|float|bool $value, ?int $ttl = null): mixed;
```

Identical to `set()` except it returns the staged `$value` instead of the
manager — convenient when assigning and storing in one expression.

- **Throws** [`CookieInvalidArgumentException`](Exceptions.md) under the same
  conditions as `set()`.

```php
$token = $cookie->push('token', bin2hex(random_bytes(16)), 3600);
// $token holds the value just stored.
```

### `all`

```php
public function all(): array;
```

Returns every non-expired value as a `key => value` map. Expired entries are
excluded and removed as a side effect. Returns the scalar values, not the
internal `['value' => ..., 'ttl' => ...]` entries.

```php
$cookie->setArray(['a' => '1', 'b' => '2']);
$cookie->all(); // ['a' => '1', 'b' => '2']
```

### `remove`

```php
public function remove(string ...$key): self;
```

Stages the removal of one or more keys and returns the manager. Missing keys
are harmless. Calling it with **no arguments is a no-op** that does not mark
the state changed. See [Reading & Removing](Reading-and-Removing.md#remove-stage-a-deletion).

```php
$cookie->remove('a');
$cookie->remove('b', 'c');
```

### `send`

```php
public function send(): bool;
```

Writes the staged state to the browser via the writer (native `setcookie()`
by default). **No-op** (returns `true`, writes nothing) when nothing has
changed since the last send. Must be called **before any output**. Returns the
writer's success boolean — note this cannot distinguish a no-op from a
successful write or a too-late silent failure. See
[Sending & Lifecycle](Sending-and-Lifecycle.md).

```php
$cookie->set('a', '1');
$cookie->send(); // writes
$cookie->send(); // no-op, returns true
```

### `flush`

```php
public function flush(): bool;
```

Clears every value and marks the state changed; the next `send()` writes an
**empty, still-signed** cookie (the cookie stays in the browser, carrying no
entries). Returns `true`. See
[Reading & Removing](Reading-and-Removing.md#flush-empty-but-keep-the-cookie).

```php
$cookie->setArray(['a' => '1']);
$cookie->flush();
$cookie->send(); // writes an empty signed cookie
```

### `destroy`

```php
public function destroy(): bool;
```

Immediately writes a deletion cookie (past expiry, carrying the configured
`path`/`domain`) and clears the working copy. After `destroy()`, a subsequent
`send()` is a no-op. Returns the writer's success boolean. See
[Reading & Removing](Reading-and-Removing.md#destroy-delete-the-cookie-immediately).

```php
$cookie->destroy(); // tells the browser to delete the cookie now
```

### `__destruct`

The destructor calls `send()` as a safety-net for pending changes. Prefer an
explicit `send()` before output; the destructor may run after the body has
flushed, where the write silently fails. See
[Sending & Lifecycle](Sending-and-Lifecycle.md#the-destructor-safety-net).

---

## Method summary

| Method | Returns | Writes to browser? |
| --- | --- | --- |
| `has` | `bool` | no (may remove an expired entry in memory) |
| `get` | value or default | no (may remove an expired entry in memory) |
| `pull` | value or default | no |
| `set` | `self` | no (staged) |
| `setArray` | `self` | no (staged) |
| `push` | the staged value | no (staged) |
| `all` | `array` | no (may remove expired entries in memory) |
| `remove` | `self` | no (staged) |
| `send` | `bool` | yes, unless no-op |
| `flush` | `bool` | no (staged; written on next `send`) |
| `destroy` | `bool` | yes, immediately |

---

## Exception

| Class | Extends | Thrown from |
| --- | --- | --- |
| `InitPHP\Cookies\Exception\CookieInvalidArgumentException` | `\InvalidArgumentException` | Constructor (empty name/salt), `set`/`push`/`setArray` (non-scalar value), `setArray` (non-associative array), `set`/`push`/`setArray` (zero TTL). |

See [Exceptions](Exceptions.md).

## See also

- [Basic Usage](Basic-Usage.md), [TTL & Expiry](TTL-and-Expiry.md),
  [Reading & Removing](Reading-and-Removing.md) — behavioral detail per method.
- [Configuration](Configuration.md) — the constructor's `$options`.
- [Testing](Testing.md) — the `$source` and `$writer` constructor seams.
