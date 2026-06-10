<?php

declare(strict_types=1);

namespace InitPHP\Cookies\Tests;

use InitPHP\Cookies\Cookie;

/**
 * Tests for send() dispatch behaviour, option mapping, and the
 * destructor safety-net.
 */
final class SendBehaviorTest extends CookieTestCase
{
    public function testSendIsANoOpWhenNothingChanged(): void
    {
        $cookie = $this->cookie();

        self::assertTrue($cookie->send());
        self::assertCount(0, $this->written);
    }

    public function testSendWritesAfterAMutation(): void
    {
        $cookie = $this->cookie();
        $cookie->set('a', '1');

        self::assertTrue($cookie->send());
        self::assertCount(1, $this->written);
        self::assertSame(self::NAME, $this->written[0]['name']);
    }

    public function testSendIsIdempotentUntilTheNextChange(): void
    {
        $cookie = $this->cookie();
        $cookie->set('a', '1');
        $cookie->send();
        $cookie->send();

        self::assertCount(1, $this->written);
    }

    public function testSameSiteNoneForcesSecure(): void
    {
        $cookie = $this->cookie(['samesite' => 'none', 'secure' => false]);
        $cookie->set('a', '1');
        $cookie->send();

        $options = $this->lastWrittenOptions();
        self::assertNotNull($options);
        self::assertSame('None', $options['samesite']);
        self::assertTrue($options['secure']);
    }

    public function testSameSiteIsNormalizedToCanonicalCase(): void
    {
        $cookie = $this->cookie(['samesite' => 'lax']);
        $cookie->set('a', '1');
        $cookie->send();

        $options = $this->lastWrittenOptions();
        self::assertNotNull($options);
        self::assertSame('Lax', $options['samesite']);
    }

    public function testInvalidSameSiteIsOmitted(): void
    {
        $cookie = $this->cookie(['samesite' => 'bogus']);
        $cookie->set('a', '1');
        $cookie->send();

        $options = $this->lastWrittenOptions();
        self::assertNotNull($options);
        self::assertArrayNotHasKey('samesite', $options);
    }

    public function testDomainOptionIsPropagatedWhenSet(): void
    {
        $cookie = $this->cookie(['domain' => 'example.test']);
        $cookie->set('a', '1');
        $cookie->send();

        $options = $this->lastWrittenOptions();
        self::assertNotNull($options);
        self::assertSame('example.test', $options['domain']);
    }

    public function testSecureAndHttponlyAreEmittedAsBooleans(): void
    {
        $cookie = $this->cookie(['secure' => true, 'httponly' => false]);
        $cookie->set('a', '1');
        $cookie->send();

        $options = $this->lastWrittenOptions();
        self::assertNotNull($options);
        self::assertTrue($options['secure']);
        self::assertFalse($options['httponly']);
    }

    public function testDestructorTriggersSendForPendingChanges(): void
    {
        $written = [];
        $writer = static function (string $name, string $value, array $options) use (&$written): bool {
            $written[] = $name;

            return true;
        };

        // Scope the cookie tightly so refcounting destroys it here.
        (static function () use ($writer): void {
            $cookie = new Cookie(self::NAME, self::SALT, [], [], $writer);
            $cookie->set('a', '1');
        })();

        self::assertSame([self::NAME], $written);
    }

    public function testDestructorDoesNotResendWhenAlreadySent(): void
    {
        $written = [];
        $writer = static function (string $name, string $value, array $options) use (&$written): bool {
            $written[] = $name;

            return true;
        };

        (static function () use ($writer): void {
            $cookie = new Cookie(self::NAME, self::SALT, [], [], $writer);
            $cookie->set('a', '1');
            $cookie->send();
        })();

        self::assertCount(1, $written);
    }
}
