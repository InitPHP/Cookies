<?php

declare(strict_types=1);

namespace InitPHP\Cookies\Tests;

use InitPHP\Cookies\Cookie;
use InitPHP\Cookies\Exception\CookieInvalidArgumentException;

/**
 * Tests for Cookie::setArray().
 */
final class SetArrayTest extends CookieTestCase
{
    public function testStoresEveryEntry(): void
    {
        $cookie = $this->cookie();
        $cookie->setArray([
            'first'  => 'ada',
            'second' => 'grace',
        ]);

        self::assertSame('ada', $cookie->get('first'));
        self::assertSame('grace', $cookie->get('second'));
    }

    public function testMergesWithExistingEntries(): void
    {
        $cookie = $this->cookie();
        $cookie->set('existing', 'kept');
        $cookie->setArray(['added' => 'new']);

        self::assertSame('kept', $cookie->get('existing'));
        self::assertSame('new', $cookie->get('added'));
    }

    public function testIsChainable(): void
    {
        $cookie = $this->cookie();

        self::assertInstanceOf(Cookie::class, $cookie->setArray(['a' => '1']));
    }

    public function testRejectsNonStringKey(): void
    {
        $cookie = $this->cookie();

        $this->expectException(CookieInvalidArgumentException::class);

        // A list has integer keys, which is not an associative array.
        /** @phpstan-ignore-next-line intentional non-associative array */
        $cookie->setArray(['value-without-key']);
    }

    public function testRejectsNonScalarValue(): void
    {
        $cookie = $this->cookie();

        $this->expectException(CookieInvalidArgumentException::class);

        /** @phpstan-ignore-next-line intentional invalid value */
        $cookie->setArray(['bad' => ['nested']]);
    }

    public function testEmptyArrayIsANoOp(): void
    {
        $cookie = $this->cookie();
        $cookie->setArray([]);
        $cookie->send();

        self::assertCount(0, $this->written);
    }
}
