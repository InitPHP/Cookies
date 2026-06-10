<?php

declare(strict_types=1);

namespace InitPHP\Cookies\Tests;

use InitPHP\Cookies\Cookie;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function count;
use function hash_hmac;
use function serialize;

/**
 * Shared scaffolding for the cookie test suite.
 *
 * Every Cookie built through {@see self::cookie()} is wired to a
 * recording writer, so tests never emit real headers and the destructor
 * safety-net is harmless. {@see self::encodeCookie()} reproduces the
 * on-the-wire format so inputs (expired entries, tampering, wrong salt)
 * can be crafted deterministically.
 */
abstract class CookieTestCase extends TestCase
{
    protected const NAME = 'session';
    protected const SALT = 's3cr3t-salt';

    /**
     * Every cookie the recording writer captured during the test.
     *
     * @var array<int, array{name: string, value: string, options: array<string, mixed>}>
     */
    protected array $written = [];

    protected function setUp(): void
    {
        $this->written = [];
    }

    /**
     * A writer that records each emission instead of calling setcookie().
     *
     * @return callable(string, string, array<string, mixed>): bool
     */
    protected function writer(): callable
    {
        return function (string $name, string $value, array $options): bool {
            $this->written[] = [
                'name'    => $name,
                'value'   => $value,
                'options' => $options,
            ];

            return true;
        };
    }

    /**
     * Build a Cookie bound to the recording writer.
     *
     * @param array<string, mixed>    $options
     * @param array<array-key, mixed> $source
     */
    protected function cookie(
        array $options = [],
        array $source = [],
        string $name = self::NAME,
        string $salt = self::SALT
    ): Cookie {
        return new Cookie($name, $salt, $options, $source, $this->writer());
    }

    /**
     * Reproduce the cookie wire format for a set of stored entries.
     *
     * Accepts arbitrary payloads (including deliberately malformed ones)
     * so security and robustness tests can craft hostile inputs.
     *
     * @param array<array-key, mixed> $entries
     */
    protected function encodeCookie(array $entries, string $salt = self::SALT): string
    {
        $payload = base64_encode(serialize($entries));

        return $payload . '.' . hash_hmac('sha256', $payload, $salt);
    }

    /**
     * The value of the most recently written cookie for $name.
     */
    protected function lastWrittenValue(string $name = self::NAME): ?string
    {
        for ($i = count($this->written) - 1; $i >= 0; $i--) {
            if ($this->written[$i]['name'] === $name) {
                return $this->written[$i]['value'];
            }
        }

        return null;
    }

    /**
     * The options of the most recently written cookie for $name.
     *
     * @return array<string, mixed>|null
     */
    protected function lastWrittenOptions(string $name = self::NAME): ?array
    {
        for ($i = count($this->written) - 1; $i >= 0; $i--) {
            if ($this->written[$i]['name'] === $name) {
                return $this->written[$i]['options'];
            }
        }

        return null;
    }
}
