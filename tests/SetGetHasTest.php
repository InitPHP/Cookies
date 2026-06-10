<?php

declare(strict_types=1);

namespace InitPHP\Cookies\Tests;

use InitPHP\Cookies\Cookie;
use InitPHP\Cookies\Exception\CookieInvalidArgumentException;

/**
 * Tests for the core set/get/has read-write surface.
 */
final class SetGetHasTest extends CookieTestCase
{
    public function testSetThenGetReturnsValue(): void
    {
        $cookie = $this->cookie();
        $cookie->set('user', 'ada');

        self::assertSame('ada', $cookie->get('user'));
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $cookie = $this->cookie();

        self::assertNull($cookie->get('nope'));
    }

    public function testGetReturnsProvidedDefaultForMissingKey(): void
    {
        $cookie = $this->cookie();

        self::assertSame('fallback', $cookie->get('nope', 'fallback'));
    }

    public function testHasIsFalseForMissingKey(): void
    {
        $cookie = $this->cookie();

        self::assertFalse($cookie->has('nope'));
    }

    public function testHasIsTrueAfterSet(): void
    {
        $cookie = $this->cookie();
        $cookie->set('user', 'ada');

        self::assertTrue($cookie->has('user'));
    }

    public function testStoresBooleanFalseDistinctlyFromMissing(): void
    {
        $cookie = $this->cookie();
        $cookie->set('flag', false);

        self::assertTrue($cookie->has('flag'));
        self::assertFalse($cookie->get('flag'));
        self::assertNotSame('default', $cookie->get('flag', 'default'));
    }

    public function testStoresIntAndFloatPreservingType(): void
    {
        $cookie = $this->cookie();
        $cookie->set('age', 42);
        $cookie->set('ratio', 1.5);

        self::assertSame(42, $cookie->get('age'));
        self::assertSame(1.5, $cookie->get('ratio'));
    }

    public function testStoresNumericString(): void
    {
        $cookie = $this->cookie();
        $cookie->set('zip', '01234');

        self::assertSame('01234', $cookie->get('zip'));
    }

    public function testSetIsChainable(): void
    {
        $cookie = $this->cookie();

        self::assertInstanceOf(Cookie::class, $cookie->set('a', '1'));
    }

    public function testSetRejectsArrayValue(): void
    {
        $cookie = $this->cookie();

        $this->expectException(CookieInvalidArgumentException::class);

        /** @phpstan-ignore-next-line intentional invalid value */
        $cookie->set('bad', ['nope']);
    }

    public function testSetRejectsNullValue(): void
    {
        $cookie = $this->cookie();

        $this->expectException(CookieInvalidArgumentException::class);

        /** @phpstan-ignore-next-line intentional invalid value */
        $cookie->set('bad', null);
    }

    public function testSetRejectsObjectValue(): void
    {
        $cookie = $this->cookie();

        $this->expectException(CookieInvalidArgumentException::class);

        /** @phpstan-ignore-next-line intentional invalid value */
        $cookie->set('bad', new \stdClass());
    }

    public function testOverwritingAKeyReplacesItsValue(): void
    {
        $cookie = $this->cookie();
        $cookie->set('user', 'ada');
        $cookie->set('user', 'grace');

        self::assertSame('grace', $cookie->get('user'));
    }
}
