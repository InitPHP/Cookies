<?php

declare(strict_types=1);

namespace InitPHP\Cookies\Tests;

/**
 * Tests for Cookie::push() and Cookie::pull().
 */
final class PushPullTest extends CookieTestCase
{
    public function testPushReturnsTheStoredValue(): void
    {
        $cookie = $this->cookie();

        self::assertSame('ada', $cookie->push('user', 'ada'));
    }

    public function testPushAlsoStoresTheValue(): void
    {
        $cookie = $this->cookie();
        $cookie->push('user', 'ada');

        self::assertSame('ada', $cookie->get('user'));
    }

    public function testPushHonoursTtl(): void
    {
        $cookie = $this->cookie();
        $cookie->push('token', 'abc', 3600);

        self::assertTrue($cookie->has('token'));
    }

    public function testPullReturnsTheValueThenRemovesIt(): void
    {
        $cookie = $this->cookie();
        $cookie->set('user', 'ada');

        self::assertSame('ada', $cookie->pull('user'));
        self::assertFalse($cookie->has('user'));
        self::assertNull($cookie->get('user'));
    }

    public function testPullReturnsDefaultWhenMissing(): void
    {
        $cookie = $this->cookie();

        self::assertSame('fallback', $cookie->pull('nope', 'fallback'));
    }
}
