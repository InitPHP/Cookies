<?php

declare(strict_types=1);

namespace InitPHP\Cookies\Tests;

use InitPHP\Cookies\Exception\CookieInvalidArgumentException;

use function time;

/**
 * Regression tests for the per-key TTL semantics.
 *
 * The legacy 1.x implementation inverted the expiry comparison in
 * get() and encode(): a still-valid TTL'd cookie was deleted on read
 * and dropped before being persisted, so any cookie set with an
 * explicit TTL was effectively unusable. has()/get()/all()/encode()
 * also disagreed on the predicate. These tests pin the corrected,
 * single-predicate behaviour.
 */
final class TtlExpiryTest extends CookieTestCase
{
    public function testGetAfterSetWithTtlReturnsValue(): void
    {
        // Regression: 1.x returned the default (and deleted the entry).
        $cookie = $this->cookie();
        $cookie->set('token', 'abc', 3600);

        self::assertSame('abc', $cookie->get('token'));
    }

    public function testHasAfterSetWithTtlIsTrue(): void
    {
        $cookie = $this->cookie();
        $cookie->set('token', 'abc', 3600);

        self::assertTrue($cookie->has('token'));
    }

    public function testHasAndGetAgreeForAValidTtl(): void
    {
        // Regression: 1.x has() said present while get() returned default.
        $cookie = $this->cookie();
        $cookie->set('token', 'abc', 3600);

        self::assertTrue($cookie->has('token'));
        self::assertSame('abc', $cookie->get('token'));
    }

    public function testValidFutureTtlFromSourceIsReadable(): void
    {
        $source = [self::NAME => $this->encodeCookie([
            'token' => ['value' => 'abc', 'ttl' => time() + 3600],
        ])];

        $cookie = $this->cookie([], $source);

        self::assertTrue($cookie->has('token'));
        self::assertSame('abc', $cookie->get('token'));
    }

    public function testExpiredEntryFromSourceIsReportedAbsent(): void
    {
        $source = [self::NAME => $this->encodeCookie([
            'token' => ['value' => 'abc', 'ttl' => time() - 10],
        ])];

        $cookie = $this->cookie([], $source);

        self::assertFalse($cookie->has('token'));
        self::assertNull($cookie->get('token'));
        self::assertArrayNotHasKey('token', $cookie->all());
    }

    public function testExpiredEntryIsRemovedAndReissuedClean(): void
    {
        $source = [self::NAME => $this->encodeCookie([
            'stale' => ['value' => 'x', 'ttl' => time() - 10],
            'fresh' => ['value' => 'y', 'ttl' => time() + 3600],
        ])];

        $cookie = $this->cookie([], $source);
        // Touching the stale key removes it from the working copy.
        $cookie->get('stale');
        $cookie->send();

        // Re-read what was written: the stale entry is gone, fresh stays.
        $reader = $this->cookie([], [self::NAME => $this->lastWrittenValue()]);
        self::assertFalse($reader->has('stale'));
        self::assertSame('y', $reader->get('fresh'));
    }

    public function testNullTtlNeverExpires(): void
    {
        $source = [self::NAME => $this->encodeCookie([
            'permanent' => ['value' => 'v', 'ttl' => null],
        ])];

        $cookie = $this->cookie([], $source);

        self::assertTrue($cookie->has('permanent'));
        self::assertSame('v', $cookie->get('permanent'));
    }

    public function testAllExcludesExpiredEntries(): void
    {
        $source = [self::NAME => $this->encodeCookie([
            'a' => ['value' => '1', 'ttl' => time() + 3600],
            'b' => ['value' => '2', 'ttl' => time() - 10],
            'c' => ['value' => '3', 'ttl' => null],
        ])];

        $cookie = $this->cookie([], $source);

        self::assertSame(['a' => '1', 'c' => '3'], $cookie->all());
    }

    public function testZeroTtlThrows(): void
    {
        $cookie = $this->cookie();

        $this->expectException(CookieInvalidArgumentException::class);

        $cookie->set('k', 'v', 0);
    }

    public function testNegativeTtlIsNormalizedToAPositiveLifetime(): void
    {
        $cookie = $this->cookie();
        $cookie->set('k', 'v', -100);

        // abs(-100) => 100 seconds in the future, hence still valid.
        self::assertSame('v', $cookie->get('k'));
    }
}
