<?php

/**
 * This file is part of the initphp/cookies package.
 *
 * (c) Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 *
 * For the full copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 *
 * @link https://github.com/InitPHP/Cookies
 */

declare(strict_types=1);

namespace InitPHP\Cookies;

use Closure;
use InitPHP\Cookies\Exception\CookieInvalidArgumentException;
use InitPHP\ParameterBag\ParameterBag;
use InitPHP\ParameterBag\ParameterBagInterface;

use function abs;
use function array_merge;
use function base64_decode;
use function base64_encode;
use function count;
use function explode;
use function hash_equals;
use function hash_hmac;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function serialize;
use function setcookie;
use function strtolower;
use function time;
use function trim;
use function ucfirst;
use function unserialize;

/**
 * Default {@see CookieInterface} implementation.
 *
 * Values are held in an {@see ParameterBagInterface} working copy and
 * serialized into a single browser cookie. The payload is authenticated
 * with an HMAC-SHA256 signature derived from a caller-supplied salt, so
 * a client that tampers with the cookie sees its modified payload
 * rejected (and replaced by a clean one). Deserialization is hardened
 * with `allowed_classes => false` and only runs after the signature has
 * been verified, which closes the PHP object-injection vector.
 *
 * The class is intentionally testable: both the raw cookie source (read
 * side) and the low-level writer (write side) can be injected through
 * the constructor, so no superglobal mutation or real header emission is
 * required under test.
 *
 * @see CookieInterface
 */
final class Cookie implements CookieInterface
{
    /**
     * Separator between the base64 payload and its hex signature in the
     * transport cookie. Safe because neither standard base64 output nor
     * a hex HMAC digest can contain a dot.
     */
    private const DELIMITER = '.';

    /**
     * Default cookie attributes, overridable per instance.
     *
     * @var array<string, mixed>
     */
    private const DEFAULT_OPTIONS = [
        'ttl'      => 2592000, // 30 days, in seconds.
        'path'     => '/',
        'domain'   => null,
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ];

    /**
     * Name of the browser cookie that carries the signed payload.
     */
    private string $name;

    /**
     * Secret used as the HMAC key when signing/verifying the payload.
     */
    private string $salt;

    /**
     * True when the working copy has diverged from what the browser
     * holds and therefore needs to be re-sent.
     */
    private bool $isChanged = false;

    /**
     * In-memory working copy. Each entry is
     * `['value' => scalar, 'ttl' => int|null]`, keyed by cookie name.
     */
    private ParameterBagInterface $storage;

    /**
     * Effective cookie attributes (defaults merged with constructor
     * overrides).
     *
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * Raw cookie source the payload is decoded from (defaults to
     * $_COOKIE). Injected so {@see self::decode()} stays testable.
     *
     * @var array<array-key, mixed>
     */
    private array $source;

    /**
     * Low-level writer used to emit the cookie. Receives the cookie
     * name, the (already encoded) value and the setcookie() options
     * array, and returns whether the write succeeded.
     *
     * @var callable(string, string, array<string, mixed>): bool
     */
    private $writer;

    /**
     * @param string                                                   $name    Browser cookie name. Must be non-empty.
     * @param string                                                   $salt    HMAC secret. Must be non-empty.
     * @param array<string, mixed>                                     $options Attribute overrides; see {@see self::DEFAULT_OPTIONS}.
     * @param array<array-key, mixed>|null                             $source  Raw cookie source. Defaults to $_COOKIE.
     * @param callable(string, string, array<string, mixed>):bool|null $writer  Low-level writer. Defaults to setcookie().
     *
     * @throws CookieInvalidArgumentException If $name or $salt is empty.
     */
    public function __construct(
        string $name,
        string $salt,
        array $options = [],
        ?array $source = null,
        ?callable $writer = null
    ) {
        $name = trim($name);
        $salt = trim($salt);
        if ($name === '' || $salt === '') {
            throw new CookieInvalidArgumentException('Cookie name and salt value cannot be empty.');
        }
        $this->name = $name;
        $this->salt = $salt;
        $this->options = $options === [] ? self::DEFAULT_OPTIONS : array_merge(self::DEFAULT_OPTIONS, $options);
        $this->source = $source ?? $_COOKIE;
        $this->writer = $writer ?? Closure::fromCallable([self::class, 'nativeSetcookie']);
        $this->storage = new ParameterBag($this->decode(), ['isMulti' => false]);
    }

    /**
     * Safety-net: flush any pending changes when the instance is
     * destroyed. Prefer calling {@see self::send()} explicitly before
     * output is produced.
     */
    public function __destruct()
    {
        $this->send();
    }

    /**
     * @inheritDoc
     */
    public function send(): bool
    {
        if ($this->isChanged === false) {
            return true;
        }
        $this->isChanged = false;

        return ($this->writer)($this->name, $this->encode(), $this->cookieOptions());
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        $entry = $this->storage->get($key);
        if (!is_array($entry)) {
            return false;
        }
        if ($this->isExpired($entry, time())) {
            $this->remove($key);

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, $default = null)
    {
        $entry = $this->storage->get($key);
        if (!is_array($entry)) {
            return $default;
        }
        if ($this->isExpired($entry, time())) {
            $this->remove($key);

            return $default;
        }

        return $entry['value'] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function pull(string $key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->remove($key);

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $value, ?int $ttl = null): CookieInterface
    {
        $this->assertValidValue($value);
        $this->storage->set($key, [
            'value' => $value,
            'ttl'   => $this->ttlCheck($ttl),
        ]);
        $this->isChanged = true;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setArray(array $assoc, ?int $ttl = null): CookieInterface
    {
        $ttl = $this->ttlCheck($ttl);
        $cookies = [];
        foreach ($assoc as $key => $value) {
            if (!is_string($key)) {
                throw new CookieInvalidArgumentException('$assoc must be an associative array.');
            }
            $this->assertValidValue($value);
            $cookies[$key] = [
                'value' => $value,
                'ttl'   => $ttl,
            ];
        }
        if ($cookies !== []) {
            $this->storage->merge($cookies);
            $this->isChanged = true;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function push(string $key, $value, ?int $ttl = null)
    {
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        $cookies = [];
        $removes = [];
        $now = time();
        foreach ($this->storage->all() as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if ($this->isExpired($entry, $now)) {
                $removes[] = (string) $key;
                continue;
            }
            $cookies[(string) $key] = $entry['value'] ?? null;
        }
        if ($removes !== []) {
            $this->remove(...$removes);
        }

        return $cookies;
    }

    /**
     * @inheritDoc
     */
    public function remove(string ...$key): CookieInterface
    {
        if ($key === []) {
            return $this;
        }
        $this->storage->remove(...$key);
        $this->isChanged = true;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function flush(): bool
    {
        $this->storage->clear();
        $this->isChanged = true;

        return true;
    }

    /**
     * @inheritDoc
     */
    public function destroy(): bool
    {
        $this->isChanged = false;
        $options = ['expires' => time() - 86400];
        if (!empty($this->options['path'])) {
            $options['path'] = $this->options['path'];
        }
        if (!empty($this->options['domain'])) {
            $options['domain'] = $this->options['domain'];
        }
        $deleted = ($this->writer)($this->name, '', $options);
        if ($deleted) {
            $this->storage->clear();
        }

        return $deleted;
    }

    /**
     * Default low-level writer: a thin wrapper over setcookie().
     *
     * The leading @ suppresses the "headers already sent" warning so the
     * {@see self::__destruct()} safety-net cannot emit noise during
     * shutdown; the boolean return value still reports the real success
     * or failure. Excluded from coverage as it is a pure I/O boundary
     * over a native function.
     *
     * @param array<string, mixed> $options
     *
     * @codeCoverageIgnore
     */
    private static function nativeSetcookie(string $name, string $value, array $options): bool
    {
        return @setcookie($name, $value, $options);
    }

    /**
     * Build the setcookie() options array for the transport cookie.
     *
     * @return array<string, mixed>
     */
    private function cookieOptions(): array
    {
        $options = [
            'expires' => time() + (int) $this->options['ttl'],
        ];
        if (!empty($this->options['path'])) {
            $options['path'] = $this->options['path'];
        }
        if (!empty($this->options['domain'])) {
            $options['domain'] = $this->options['domain'];
        }
        $options['secure'] = !empty($this->options['secure']);
        $options['httponly'] = !empty($this->options['httponly']);
        if (is_string($this->options['samesite'])) {
            $samesite = ucfirst(strtolower($this->options['samesite']));
            if (in_array($samesite, ['None', 'Lax', 'Strict'], true)) {
                $options['samesite'] = $samesite;
                if ($samesite === 'None') {
                    // SameSite=None is only honoured on a Secure cookie.
                    $options['secure'] = true;
                }
            }
        }

        return $options;
    }

    /**
     * Sign and encode the non-expired working copy for transport.
     *
     * @return string `base64(serialize(entries)) . "." . hmac`
     */
    private function encode(): string
    {
        $cookies = [];
        $now = time();
        foreach ($this->storage->all() as $key => $entry) {
            if (!is_array($entry) || $this->isExpired($entry, $now)) {
                continue;
            }
            $cookies[$key] = $entry;
        }
        $payload = base64_encode(serialize($cookies));

        return $payload . self::DELIMITER . $this->sign($payload);
    }

    /**
     * Verify, decode and deserialize the incoming cookie.
     *
     * Returns an empty array (and marks the state changed, so a clean
     * cookie is re-issued) whenever the payload is missing, malformed or
     * its signature does not match. Deserialization runs only after the
     * signature check and forbids object instantiation.
     *
     * @return array<array-key, mixed>
     */
    private function decode(): array
    {
        $raw = $this->source[$this->name] ?? null;
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $parts = explode(self::DELIMITER, $raw, 2);
        if (count($parts) !== 2) {
            $this->isChanged = true;

            return [];
        }
        [$payload, $signature] = $parts;
        if (!hash_equals($this->sign($payload), $signature)) {
            $this->isChanged = true;

            return [];
        }
        $serialized = base64_decode($payload, true);
        if ($serialized === false) {
            return [];
        }
        $data = @unserialize($serialized, ['allowed_classes' => false]);

        return is_array($data) ? $data : [];
    }

    /**
     * Compute the HMAC-SHA256 signature of $payload under the salt.
     */
    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->salt);
    }

    /**
     * Whether a stored entry has passed its absolute expiry.
     *
     * A null TTL never expires; otherwise the stored TTL is an absolute
     * Unix timestamp and the entry is expired once $now reaches it.
     *
     * @param array<array-key, mixed> $entry
     */
    private function isExpired(array $entry, int $now): bool
    {
        $ttl = $entry['ttl'] ?? null;

        return $ttl !== null && $ttl <= $now;
    }

    /**
     * Guard that a cookie value is one of the supported scalar types.
     *
     * @param mixed $value
     *
     * @throws CookieInvalidArgumentException
     */
    private function assertValidValue($value): void
    {
        if (!is_string($value) && !is_bool($value) && !is_numeric($value)) {
            throw new CookieInvalidArgumentException('Cookie value can only be string, boolean or numeric.');
        }
    }

    /**
     * Normalize a relative TTL into an absolute expiry timestamp.
     *
     * @param int|null $ttl Seconds from now, or null for "no expiry".
     *
     * @return int|null Absolute Unix timestamp, or null.
     *
     * @throws CookieInvalidArgumentException If $ttl is zero.
     */
    private function ttlCheck(?int $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
        $ttl = abs($ttl);
        if ($ttl === 0) {
            throw new CookieInvalidArgumentException('$ttl can be null or a positive integer.');
        }

        return $ttl + time();
    }
}
