<?php
/**
 * CookieInterface.php
 *
 * This file is part of Cookies.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Cookies;

use InitPHP\Cookies\Exception\CookieInvalidArgumentException;

interface CookieInterface
{

    /**
     * Cookie varlığını kontrol eder.
     *
     * Süresi dolmuş bir cookie sorgulanmak istenirse; false döner ve cookie kaldırılır.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Cookie değerini verir.
     *
     * Süresi dolmuş ya da olmayan bir cookie istenirse $default döner.
     *
     * @param string $key
     * @param mixed $default
     * @return string|int|float|bool|mixed
     */
    public function get(string $key, $default = null);

    /**
     * Cookie değerini verir ve siler.
     *
     * CookieInterface::get() yönteminden farklı olarak cookie değeri getirildikten sonra cookie silinir.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull(string $key, $default = null);

    /**
     * Bir cookie tanımlar.
     *
     * Bu yöntem cookie doğrudan kullanının tarayıcısna göndermez. Cookie geçerli değerini değiştirir/tanımlar.
     * Yapılan değişiklik CookieInterface::__destruct() yönteminde ya da kendisinden sonraki CookieInterface::send() yöntemi ile tarayıcıya aktarılır.
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
     * @param string[] $assoc
     * @param int|null $ttl
     * @return $this
     * @throws CookieInvalidArgumentException
     */
    public function setArray(array $assoc, ?int $ttl = null): self;

    /**
     * Bir Cookie verisi set eder. CookieInterface::set() yönteminden farklı olarak bu yöntem geriye $value döndürür.
     *
     * @param string $key
     * @param string|int|float|bool $value
     * @param null|int $ttl
     * @return mixed
     */
    public function push(string $key, $value, ?int $ttl = null);

    /**
     * Tüm cookie verisini (süresi dolmayanları) ilişkisel bir dizi olarak verir.
     *
     * @return array
     */
    public function all(): array;

    /**
     * Cookie kaldırır.
     *
     * Bu yöntemde cookie doğrudan kullanının tarayıcısna gönderilmez. Cookie geçerli script içinde kaldırılır.
     * Yapılan değişiklik CookieInterface::__destruct() yönteminde ya da kendisinden sonraki CookieInterface::send() yöntemi ile tarayıcıya aktarılır.
     *
     * @param string ...$key
     * @return $this
     */
    public function remove(string ...$key): self;

    /**
     * Geçerli değişikleri (set,remove) kullanıcının browserına gönderir.
     *
     * Eğer bir değişiklik yoksa bir şey yapmaz.
     *
     * @see setcookie()
     * @return bool
     */
    public function send(): bool;

    /**
     * Cookie kaldırılmadan sadece içeriğini boşaltır.
     *
     * Yapılan değişiklik CookieInterface::__destruct() yönteminde ya da kendisinden sonraki CookieInterface::send() yöntemi ile tarayıcıya aktarılır.
     *
     * @return bool
     */
    public function flush(): bool;

    /**
     * Tüm cookieleri yok eder.
     *
     * @see setcookie()
     * @return bool
     */
    public function destroy(): bool;

}
