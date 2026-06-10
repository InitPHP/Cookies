<?php

declare(strict_types=1);

namespace InitPHP\Cookies\Tests;

use InitPHP\Cookies\Cookie;

use function count;
use function time;

/**
 * Tests for remove(), flush() and destroy().
 */
final class RemoveFlushDestroyTest extends CookieTestCase
{
    public function testRemoveDeletesASingleKey(): void
    {
        $cookie = $this->cookie();
        $cookie->set('a', '1');
        $cookie->set('b', '2');
        $cookie->remove('a');

        self::assertFalse($cookie->has('a'));
        self::assertTrue($cookie->has('b'));
    }

    public function testRemoveDeletesMultipleKeys(): void
    {
        $cookie = $this->cookie();
        $cookie->setArray(['a' => '1', 'b' => '2', 'c' => '3']);
        $cookie->remove('a', 'c');

        self::assertSame(['b' => '2'], $cookie->all());
    }

    public function testRemoveMissingKeyIsHarmless(): void
    {
        $cookie = $this->cookie();
        $cookie->set('a', '1');
        $cookie->remove('does-not-exist');

        self::assertSame(['a' => '1'], $cookie->all());
    }

    public function testRemoveIsChainable(): void
    {
        $cookie = $this->cookie();

        self::assertInstanceOf(Cookie::class, $cookie->remove('x'));
    }

    public function testRemoveWithoutArgumentsIsANoOp(): void
    {
        $cookie = $this->cookie();
        $cookie->set('a', '1');
        $cookie->send();
        $writesBefore = count($this->written);

        self::assertInstanceOf(Cookie::class, $cookie->remove());

        // remove() with no keys must not flag the state as changed.
        $cookie->send();
        self::assertCount($writesBefore, $this->written);
    }

    public function testFlushClearsEverything(): void
    {
        $cookie = $this->cookie();
        $cookie->setArray(['a' => '1', 'b' => '2']);
        $cookie->flush();

        self::assertSame([], $cookie->all());
        self::assertFalse($cookie->has('a'));
    }

    public function testFlushSendsAnEmptySignedCookie(): void
    {
        $cookie = $this->cookie();
        $cookie->setArray(['a' => '1']);
        $cookie->flush();
        $cookie->send();

        // A reader fed the flushed payload sees no entries.
        $reader = $this->cookie([], [self::NAME => $this->lastWrittenValue()]);
        self::assertSame([], $reader->all());
    }

    public function testDestroyEmitsADeletionCookie(): void
    {
        $cookie = $this->cookie(['path' => '/app']);
        $cookie->set('a', '1');

        $before = time();
        self::assertTrue($cookie->destroy());

        $options = $this->lastWrittenOptions();
        self::assertNotNull($options);
        self::assertSame('', $this->lastWrittenValue());
        self::assertLessThan($before, $options['expires']);
        self::assertSame('/app', $options['path']);
    }

    public function testDestroyPropagatesTheConfiguredDomain(): void
    {
        $cookie = $this->cookie(['domain' => 'example.test']);
        $cookie->set('a', '1');
        $cookie->destroy();

        $options = $this->lastWrittenOptions();
        self::assertNotNull($options);
        self::assertSame('example.test', $options['domain']);
    }

    public function testDestroyClearsTheWorkingCopy(): void
    {
        $cookie = $this->cookie();
        $cookie->set('a', '1');
        $cookie->destroy();

        self::assertSame([], $cookie->all());
    }

    public function testDestroyMakesSubsequentSendANoOp(): void
    {
        $cookie = $this->cookie();
        $cookie->set('a', '1');
        $cookie->destroy();

        $writesAfterDestroy = $this->written;
        $cookie->send();

        // send() added nothing on top of the destroy() write.
        self::assertCount(count($writesAfterDestroy), $this->written);
    }
}
