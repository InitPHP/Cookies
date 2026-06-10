<?php

declare(strict_types=1);

namespace InitPHP\Cookies\Tests;

/**
 * Robustness tests for entries that pass the signature check but do not
 * match the expected `['value' => ..., 'ttl' => ...]` shape (e.g. a bare
 * scalar produced by a future format or a corrupted-yet-signed payload).
 * Such entries must be ignored rather than crash the reader.
 */
final class MalformedEntryTest extends CookieTestCase
{
    public function testNonArrayEntryIsExcludedFromAll(): void
    {
        $source = [self::NAME => $this->encodeCookie(['weird' => 'bare-string'])];

        $cookie = $this->cookie([], $source);

        self::assertArrayNotHasKey('weird', $cookie->all());
    }

    public function testNonArrayEntryIsAbsentForHasAndGet(): void
    {
        $source = [self::NAME => $this->encodeCookie(['weird' => 'bare-string'])];

        $cookie = $this->cookie([], $source);

        self::assertFalse($cookie->has('weird'));
        self::assertNull($cookie->get('weird'));
    }

    public function testNonArrayEntryIsDroppedWhenReencoded(): void
    {
        $source = [self::NAME => $this->encodeCookie([
            'weird' => 'bare-string',
            'ok'    => ['value' => 'v', 'ttl' => null],
        ])];

        $cookie = $this->cookie([], $source);
        $cookie->set('x', '1');
        $cookie->send();

        $reader = $this->cookie([], [self::NAME => $this->lastWrittenValue()]);

        self::assertSame(['ok' => 'v', 'x' => '1'], $reader->all());
    }
}
