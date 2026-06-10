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

use InitPHP\Cookies\Exception\CookieInvalidArgumentException;

/**
 * Contract for a signed, tamper-evident cookie manager.
 *
 * The manager keeps an in-memory working copy of the cookie payload.
 * Mutating methods ({@see self::set()}, {@see self::setArray()},
 * {@see self::remove()}, {@see self::flush()}) only change that working
 * copy; nothing is written to the browser until {@see self::send()} is
 * called — either explicitly (recommended, before any output) or by the
 * destructor as a safety-net.
 *
 * Each entry carries its own absolute expiry. Reading an expired entry
 * ({@see self::has()}, {@see self::get()}, {@see self::all()}) removes
 * it and reports it as absent.
 *
 * Stored values are limited to scalars (`string`, `bool`, `int`,
 * `float` and numeric strings); anything else raises
 * {@see CookieInvalidArgumentException}.
 */
interface CookieInterface
{
    /**
     * Whether a non-expired value is stored for $key.
     *
     * An entry whose TTL has elapsed is removed as a side effect and
     * reported as absent.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Return the value stored for $key, or $default when the key is
     * absent or expired.
     *
     * An expired entry is removed as a side effect before $default is
     * returned.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return string|int|float|bool|mixed
     */
    public function get(string $key, $default = null);

    /**
     * Return the value for $key and then remove it (read-once).
     *
     * Behaves like {@see self::get()} but the entry is always removed
     * afterwards, whether or not it existed.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function pull(string $key, $default = null);

    /**
     * Stage a single cookie value.
     *
     * The value is written to the working copy only; it reaches the
     * browser through {@see self::send()} or the destructor. A null
     * $ttl means "expires with the transport cookie" (no per-key
     * expiry); a positive $ttl is the lifetime in seconds from now.
     *
     * @param string                $key
     * @param string|int|float|bool $value
     * @param int|null              $ttl   Seconds from now, or null.
     *
     * @return $this
     *
     * @throws CookieInvalidArgumentException If $value is not scalar or
     *                                        $ttl is zero.
     */
    public function set(string $key, $value, ?int $ttl = null): self;

    /**
     * Stage several cookies from an associative array sharing one TTL.
     *
     * @param array<string, string|int|float|bool> $assoc
     * @param int|null                             $ttl   Seconds from now, or null.
     *
     * @return $this
     *
     * @throws CookieInvalidArgumentException If a key is not a string,
     *                                        a value is not scalar, or
     *                                        $ttl is zero.
     */
    public function setArray(array $assoc, ?int $ttl = null): self;

    /**
     * Stage a single cookie value and return that value.
     *
     * Identical to {@see self::set()} except it returns $value instead
     * of the manager, which is convenient when assigning and storing in
     * one expression.
     *
     * @param string                $key
     * @param string|int|float|bool $value
     * @param int|null              $ttl
     *
     * @return string|int|float|bool|mixed The staged $value.
     *
     * @throws CookieInvalidArgumentException If $value is not scalar or
     *                                        $ttl is zero.
     */
    public function push(string $key, $value, ?int $ttl = null);

    /**
     * Return every non-expired staged cookie as a key => value map.
     *
     * Expired entries are removed as a side effect and excluded from
     * the result.
     *
     * @return array<string, string|int|float|bool>
     */
    public function all(): array;

    /**
     * Stage the removal of one or more cookies.
     *
     * The removal reaches the browser through {@see self::send()} or
     * the destructor. Removing a missing key is a no-op.
     *
     * @param string ...$key
     *
     * @return $this
     */
    public function remove(string ...$key): self;

    /**
     * Write the staged state to the browser via setcookie().
     *
     * Does nothing and returns true when no mutation has occurred since
     * the last send. Should be called before any output is produced.
     *
     * @see \setcookie()
     *
     * @return bool True on success (or no-op), false if the underlying
     *              writer reported a failure.
     */
    public function send(): bool;

    /**
     * Clear every staged value without expiring the transport cookie.
     *
     * The emptied state is written on the next {@see self::send()} (or
     * by the destructor), which replaces the browser cookie with an
     * empty, still-signed payload.
     *
     * @return bool
     */
    public function flush(): bool;

    /**
     * Immediately expire and clear the transport cookie.
     *
     * Unlike {@see self::flush()} this writes to the browser right away,
     * instructing it to delete the cookie, and empties the working copy.
     *
     * @see \setcookie()
     *
     * @return bool
     */
    public function destroy(): bool;
}
