# Recipe: testing cookies

Goal: unit-test code that uses `Cookie` without emitting real HTTP
headers or mutating `$_COOKIE`, by injecting the two test seams the
constructor exposes — the raw `$source` (read side) and the `$writer`
(write side).

## The two seams

```php
public function __construct(
    string $name,
    string $salt,
    array $options = [],
    ?array $source = null,   // raw cookie source; defaults to $_COOKIE
    ?callable $writer = null // low-level writer; defaults to setcookie()
);
```

- **`$source`** is a raw `['cookieName' => 'rawValue']` array. Pass it
  to simulate an incoming cookie instead of reading `$_COOKIE`. The
  `'rawValue'` must be in the on-the-wire format
  (`base64(serialize(entries)) . '.' . hmac`), not a plain value.
- **`$writer`** is a `callable(string $name, string $value, array $options): bool`.
  Pass a recording closure to capture what *would* be written instead
  of calling `setcookie()`. This also makes the destructor safety-net
  harmless under test (it calls the recording writer, not real headers).

## A recording writer

Capture every emission so assertions can inspect it:

```php
$written = [];

$writer = static function (string $name, string $value, array $options) use (&$written): bool {
    $written[] = ['name' => $name, 'value' => $value, 'options' => $options];

    return true;
};

$cookie = new InitPHP\Cookies\Cookie('session', 's3cr3t-salt', [], [], $writer);
$cookie->set('user', 'ada');
$cookie->send();

// $written[0]['name']  === 'session'
// $written[0]['value'] is the signed wire string
```

## Reproducing the wire format for input

To feed an incoming cookie to `$source`, you must produce the same
signed envelope the manager writes. Each stored entry is
`['value' => scalar, 'ttl' => int|null]` (an absolute Unix timestamp, or
`null` for "no per-key expiry"):

```php
function encodeCookie(array $entries, string $salt): string
{
    $payload = base64_encode(serialize($entries));

    return $payload . '.' . hash_hmac('sha256', $payload, $salt);
}

$source = ['session' => encodeCookie([
    'user' => ['value' => 'ada', 'ttl' => null],
], 's3cr3t-salt')];

$cookie = new InitPHP\Cookies\Cookie('session', 's3cr3t-salt', [], $source);

$cookie->get('user'); // 'ada'
```

This is exactly the pattern the package's own test suite uses. See
[`tests/CookieTestCase.php`](../../tests/CookieTestCase.php), whose
`encodeCookie()` helper and recording `writer()` are the canonical
reference — including for crafting hostile inputs (expired entries,
tampering, wrong salt).

## A reusable test base

A small base class mirrors the package's scaffolding and keeps
individual tests terse:

```php
use InitPHP\Cookies\Cookie;
use PHPUnit\Framework\TestCase;

abstract class CookieTestCase extends TestCase
{
    protected const NAME = 'session';
    protected const SALT = 's3cr3t-salt';

    /** @var array<int, array{name: string, value: string, options: array}> */
    protected array $written = [];

    protected function setUp(): void
    {
        $this->written = [];
    }

    protected function writer(): callable
    {
        return function (string $name, string $value, array $options): bool {
            $this->written[] = compact('name', 'value', 'options');

            return true;
        };
    }

    protected function cookie(array $options = [], array $source = []): Cookie
    {
        return new Cookie(self::NAME, self::SALT, $options, $source, $this->writer());
    }

    protected function encodeCookie(array $entries, string $salt = self::SALT): string
    {
        $payload = base64_encode(serialize($entries));

        return $payload . '.' . hash_hmac('sha256', $payload, $salt);
    }

    protected function lastWrittenValue(string $name = self::NAME): ?string
    {
        for ($i = count($this->written) - 1; $i >= 0; $i--) {
            if ($this->written[$i]['name'] === $name) {
                return $this->written[$i]['value'];
            }
        }

        return null;
    }
}
```

## Example tests

### Round trip: issuer → reader

Capture what an issuer wrote, then feed it back into a reader as
`$source`:

```php
final class MyCookieTest extends CookieTestCase
{
    public function testValueSurvivesARoundTrip(): void
    {
        $issuer = $this->cookie();
        $issuer->set('user', 'ada');
        $issuer->send();

        $reader = $this->cookie([], [self::NAME => $this->lastWrittenValue()]);

        self::assertSame('ada', $reader->get('user'));
    }
}
```

### Asserting send-side behavior

```php
public function testSendIsANoOpWhenNothingChanged(): void
{
    $cookie = $this->cookie();

    self::assertTrue($cookie->send());
    self::assertCount(0, $this->written); // nothing emitted
}

public function testSendWritesAfterAMutation(): void
{
    $cookie = $this->cookie();
    $cookie->set('a', '1');
    $cookie->send();

    self::assertCount(1, $this->written);
    self::assertSame(self::NAME, $this->written[0]['name']);
}
```

### Crafting expired or hostile input

```php
public function testExpiredEntryIsAbsent(): void
{
    $source = [self::NAME => $this->encodeCookie([
        'token' => ['value' => 'abc', 'ttl' => time() - 10], // already past
    ])];

    $cookie = $this->cookie([], $source);

    self::assertFalse($cookie->has('token'));
}

public function testTamperedCookieIsRejected(): void
{
    $valid    = $this->encodeCookie(['user' => ['value' => 'ada', 'ttl' => null]]);
    $tampered = ($valid[0] === 'A' ? 'B' : 'A') . substr($valid, 1);

    $cookie = $this->cookie([], [self::NAME => $tampered]);

    self::assertNull($cookie->get('user')); // signature mismatch → discarded
}
```

## Testing code that depends on a cookie

Type-hint `CookieInterface` in your own classes so a test can pass a
manager wired to a recording writer:

```php
use InitPHP\Cookies\CookieInterface;

final class LoginService
{
    public function __construct(private CookieInterface $cookie) {}

    public function login(int $userId): void
    {
        $this->cookie->set('user_id', $userId);
        $this->cookie->send();
    }
}

// Test:
$service = new LoginService($this->cookie());
$service->login(42);
self::assertCount(1, $this->written);
```

## Common pitfalls

- **Passing a plain value to `$source`.** The source value must be the
  signed wire string. Use `encodeCookie()`; a raw `'ada'` has no
  delimiter and is discarded.
- **Mismatched salt between `encodeCookie()` and the manager.** If the
  salts differ, verification fails and the cookie reads as empty — which
  is correct, but surprising if you did not intend it.
- **Forgetting that `ttl` in an entry is absolute.** When building input
  by hand, the entry's `ttl` is an absolute Unix timestamp (or `null`),
  not "seconds from now". Use `time() + N` for valid, `time() - N` for
  expired.
- **Mutating `$_COOKIE` in tests.** You do not need to — inject
  `$source` instead, which keeps tests isolated and parallel-safe.
