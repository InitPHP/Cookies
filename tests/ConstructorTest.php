<?php

declare(strict_types=1);

namespace InitPHP\Cookies\Tests;

use InitPHP\Cookies\Exception\CookieInvalidArgumentException;

use function time;

/**
 * Tests for Cookie construction, validation and option handling.
 */
final class ConstructorTest extends CookieTestCase
{
    public function testEmptyNameThrows(): void
    {
        $this->expectException(CookieInvalidArgumentException::class);

        $this->cookie([], [], '');
    }

    public function testEmptySaltThrows(): void
    {
        $this->expectException(CookieInvalidArgumentException::class);

        $this->cookie([], [], self::NAME, '');
    }

    public function testWhitespaceOnlyNameThrows(): void
    {
        $this->expectException(CookieInvalidArgumentException::class);

        $this->cookie([], [], '   ');
    }

    public function testWhitespaceOnlySaltThrows(): void
    {
        $this->expectException(CookieInvalidArgumentException::class);

        $this->cookie([], [], self::NAME, "  \t ");
    }

    public function testNameAndSaltAreTrimmed(): void
    {
        // A cookie issued with padded name/salt must be readable by a
        // cookie configured with the trimmed equivalents.
        $issuer = $this->cookie([], [], '  ' . self::NAME . '  ', '  ' . self::SALT . '  ');
        $issuer->set('user', 'ada');
        $issuer->send();

        $reader = $this->cookie([], [self::NAME => $this->lastWrittenValue()]);

        self::assertSame('ada', $reader->get('user'));
    }

    public function testDefaultOptionsAreApplied(): void
    {
        $cookie = $this->cookie();
        $cookie->set('k', 'v');

        $before = time();
        $cookie->send();
        $after = time();

        $options = $this->lastWrittenOptions();

        self::assertNotNull($options);
        self::assertSame('/', $options['path']);
        self::assertFalse($options['secure']);
        self::assertTrue($options['httponly']);
        self::assertSame('Strict', $options['samesite']);
        self::assertGreaterThanOrEqual($before + 2592000, $options['expires']);
        self::assertLessThanOrEqual($after + 2592000, $options['expires']);
    }

    public function testOptionOverridesAreMergedOverDefaults(): void
    {
        $cookie = $this->cookie([
            'ttl'    => 100,
            'path'   => '/app',
            'secure' => true,
        ]);
        $cookie->set('k', 'v');

        $before = time();
        $cookie->send();
        $after = time();

        $options = $this->lastWrittenOptions();

        self::assertNotNull($options);
        self::assertSame('/app', $options['path']);
        self::assertTrue($options['secure']);
        // Untouched defaults survive the merge.
        self::assertTrue($options['httponly']);
        self::assertSame('Strict', $options['samesite']);
        self::assertGreaterThanOrEqual($before + 100, $options['expires']);
        self::assertLessThanOrEqual($after + 100, $options['expires']);
    }

    public function testDomainOptionIsOmittedWhenNull(): void
    {
        $cookie = $this->cookie();
        $cookie->set('k', 'v');
        $cookie->send();

        $options = $this->lastWrittenOptions();

        self::assertNotNull($options);
        self::assertArrayNotHasKey('domain', $options);
    }
}
