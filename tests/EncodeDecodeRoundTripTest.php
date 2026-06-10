<?php

declare(strict_types=1);

namespace InitPHP\Cookies\Tests;

use function time;

/**
 * Tests that values survive a full encode -> browser -> decode cycle.
 */
final class EncodeDecodeRoundTripTest extends CookieTestCase
{
    public function testValuesSurviveARoundTrip(): void
    {
        $issuer = $this->cookie();
        $issuer->set('user', 'ada');
        $issuer->set('role', 'admin');
        $issuer->send();

        $reader = $this->cookie([], [self::NAME => $this->lastWrittenValue()]);

        self::assertSame('ada', $reader->get('user'));
        self::assertSame('admin', $reader->get('role'));
    }

    public function testScalarTypesSurviveARoundTrip(): void
    {
        $issuer = $this->cookie();
        $issuer->set('int', 42);
        $issuer->set('float', 1.5);
        $issuer->set('boolTrue', true);
        $issuer->set('boolFalse', false);
        $issuer->set('string', 'hello');
        $issuer->send();

        $reader = $this->cookie([], [self::NAME => $this->lastWrittenValue()]);

        self::assertSame(42, $reader->get('int'));
        self::assertSame(1.5, $reader->get('float'));
        self::assertTrue($reader->get('boolTrue'));
        self::assertFalse($reader->get('boolFalse'));
        self::assertSame('hello', $reader->get('string'));
    }

    public function testEncodeDropsExpiredEntries(): void
    {
        $source = [self::NAME => $this->encodeCookie([
            'stale' => ['value' => 'x', 'ttl' => time() - 10],
            'fresh' => ['value' => 'y', 'ttl' => time() + 3600],
        ])];

        $issuer = $this->cookie([], $source);
        // Force a re-send without touching either key directly.
        $issuer->set('extra', 'z');
        $issuer->send();

        $reader = $this->cookie([], [self::NAME => $this->lastWrittenValue()]);

        self::assertFalse($reader->has('stale'));
        self::assertSame('y', $reader->get('fresh'));
        self::assertSame('z', $reader->get('extra'));
    }

    public function testLoadingAValidCookieDoesNotTriggerAResend(): void
    {
        $source = [self::NAME => $this->encodeCookie([
            'user' => ['value' => 'ada', 'ttl' => null],
        ])];

        $cookie = $this->cookie([], $source);
        $cookie->send();

        self::assertCount(0, $this->written);
    }

    public function testEmptySourceProducesAnEmptyState(): void
    {
        $cookie = $this->cookie();

        self::assertSame([], $cookie->all());
    }
}
