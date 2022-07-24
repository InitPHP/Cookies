<?php
/**
 * CookieInterface.php
 *
 * This file is part of Cookies.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Cookies;

use InitPHP\Cookies\Exception\CookieInvalidArgumentException;

interface CookieInterface
{

    /**
     * Çerezin varlığını kontrol eder.
     *
     * Süresi dolmuş bir çerez sorgulanmak istenirse; false döner ve çerezi kaldırmaya çalışır.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Çerezin değerini verir.
     *
     * Süresi dolmuş ya da olmayan bir çerez istenirse $default döner.
     *
     * @param string $key
     * @param mixed $default
     * @return string|int|float|bool|mixed
     */
    public function get(string $key, $default = null);

    /**
     * Bir çerezi tanımlar.
     *
     * Bu yöntem çerezi doğrudan kullanının tarayıcısna göndermez. Çerezin geçerli değerini değiştirir/tanımlar.
     * Yapılan değişiklik CookieInterface::__destruct() yönteminde ya da kendisinden sonraki CookieInterface::push() yöntemi ile tarayıcıya aktarılır.
     *
     * @param string $key
     * @param string|int|float|bool $value
     * @param int|null $ttl
     * @return $this
     * @throws CookieInvalidArgumentException
     */
    public function set(string $key, $value, ?int $ttl = null): self;

    /**
     * İlişkisel bir dizi kullanarak bir cookie tanımlar.
     *
     *
     * @param string[] $array
     * @param int|null $ttl
     * @return $this
     * @throws CookieInvalidArgumentException
     */
    public function setArray(array $array, ?int $ttl = null): self;

    /**
     * Tüm cookie verisini (süresi dolmayanları) ilişkisel bir dizi olarak verir.
     *
     * @return array
     */
    public function all(): array;

    /**
     * Geçerli değişikleri (set,remove) kullanıcının browserına gönderir.
     *
     * Eğer bir değişiklik yoksa bir şey yapmaz.
     *
     * @see setcookie()
     * @return bool
     */
    public function push(): bool;

    /**
     * Çerezi kaldırır.
     *
     * Bu yöntem çerezi doğrudan kullanının tarayıcısna göndermez. Çerezin geçerli script içinde kaldırır.
     * Yapılan değişiklik CookieInterface::__destruct() yönteminde ya da kendisinden sonraki CookieInterface::push() yöntemi ile tarayıcıya aktarılır.
     *
     * @param string ...$key
     * @return $this
     */
    public function remove(string ...$key): self;

    /**
     * Tüm cookieleri yok eder.
     *
     * @see setcookie()
     * @return bool
     */
    public function destroy(): bool;

}
