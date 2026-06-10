<?php

declare(strict_types=1);

namespace InitPHP\Cookies\Tests\Fixture;

/**
 * Object-injection probe.
 *
 * If a payload carrying a serialized instance of this class were ever
 * deserialized with class instantiation allowed, {@see self::__wakeup()}
 * would flip {@see self::$awoken} to true. The cookie manager forbids
 * class instantiation (`allowed_classes => false`), so the flag must
 * stay false.
 */
final class MaliciousProbe
{
    public static bool $awoken = false;

    public function __wakeup(): void
    {
        self::$awoken = true;
    }
}
