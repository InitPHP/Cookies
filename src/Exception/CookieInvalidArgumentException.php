<?php

/**
 * This file is part of the initphp/cookies package.
 *
 * (c) Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 *
 * For the full copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 *
 * @link https://github.com/InitPHP/Cookies
 */

declare(strict_types=1);

namespace InitPHP\Cookies\Exception;

use InvalidArgumentException;

/**
 * Thrown when a caller supplies an argument the cookie manager cannot
 * accept — an empty name/salt, a non-scalar cookie value, a
 * non-associative array, or a non-positive TTL.
 */
final class CookieInvalidArgumentException extends InvalidArgumentException
{
}
