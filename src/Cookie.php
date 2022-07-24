<?php
/**
 * Cookie.php
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
use InitPHP\ParameterBag\ParameterBag;
use InitPHP\ParameterBag\ParameterBagInterface;

class Cookie implements CookieInterface
{
    /** @var string */
    protected $name;

    /** @var string */
    private $salt;

    /** @var bool */
    private $isChange = false;

    /** @var ParameterBagInterface */
    protected $storage;

    protected $options = [
        'ttl'       => 2592000, // 30 days
        'path'      => '/',
        'domain'    => null,
        'secure'    => false,
        'httponly'  => true,
        'samesite'  => 'Strict'
    ];

    public function __construct(string $name, string $salt, array $options = [])
    {
        $name = \trim($name);
        $salt = \trim($salt);
        if(empty($salt) || empty($name)){
            throw new CookieInvalidArgumentException('Cookie name and salt value cannot be empty.');
        }
        $this->name = $name;
        $this->salt = $salt;
        if(!empty($options)){
            $this->options = \array_merge($this->options, $options);
        }
        $this->storage = new ParameterBag($this->decode(), ['isMulti' => false]);
    }

    public function __destruct()
    {
        $this->push();
    }

    /**
     * @inheritDoc
     */
    public function push(): bool
    {
        if($this->isChange === FALSE){
            return true;
        }
        $this->isChange = false;

        $options = [
            'expires'   => ($this->options['ttl'] + \time())
        ];
        if(!empty($this->options['path'])){
            $options['path'] = $this->options['path'];
        }
        if(!empty($this->options['domain'])){
            $options['domain']  = $this->options['domain'];
        }
        if(\is_bool($this->options['secure'])){
            $options['secure']  = $this->options['secure'];
        }
        if(\is_bool($this->options['httponly'])){
            $options['httponly'] = $this->options['httponly'];
        }
        if(\is_string($this->options['samesite']) && \in_array(\strtolower($this->options['samesite']), ['none', 'lax', 'strict'], true)){
            $options['samesite'] = $this->options['samesite'];
        }
        return @\setcookie($this->name, $this->encode(), $options);
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        if($this->storage->has($key) === FALSE){
            return false;
        }
        $get = $this->storage->get($key);
        return ($get['ttl'] === null || $get['ttl'] < \time());
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, $default = null)
    {
        if(($get = $this->storage->get($key, null)) === null){
            return $default;
        }
        if($get['ttl'] !== null && $get['ttl'] > \time()){
            $this->remove($key);
            return $default;
        }
        return $get['value'];
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $value, ?int $ttl = null): CookieInterface
    {
        if(!\is_string($value) && !\is_bool($value) && !\is_numeric($value)){
            throw new CookieInvalidArgumentException('Cookie value can only be string, boolean or numeric.');
        }
        $this->isChange = true;
        $ttl = ($ttl !== null) ? (int)\abs($ttl) + \time() : null;
        if($ttl === 0){
            $ttl = null;
        }
        $cookie = [
            'value'     => $value,
            'ttl'       => $ttl,
        ];
        $this->storage->set($key, $cookie);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setArray(array $array, ?int $ttl = null): CookieInterface
    {
        $ttl = ($ttl !== null) ? (int)\abs($ttl) + \time() : null;
        if($ttl === 0){
            $ttl = null;
        }

        $cookies = [];
        foreach ($array as $key => $value) {
            if(!\is_string($key)){
                throw new CookieInvalidArgumentException();
            }
            if(!\is_string($value) && !\is_bool($value) && !\is_numeric($value)){
                throw new CookieInvalidArgumentException('Cookie value can only be string, boolean or numeric.');
            }
            $cookies[$key] = [
                'value'     => $value,
                'ttl'       => $ttl,
            ];
        }
        $this->isChange = true;
        $this->storage->merge($cookies);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        $cookies = [];
        $now = \time();
        foreach ($this->storage->all() as $key => $value) {
            if($value['ttl'] !== null && $value['ttl'] > $now){
                continue;
            }
            $cookies[$key] = $value['value'];
        }
        return $cookies;
    }

    /**
     * @inheritDoc
     */
    public function remove(string ...$key): CookieInterface
    {
        $this->isChange = true;
        $this->storage->remove(...$key);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function destroy(): bool
    {
        $this->isChange = false;
        $this->storage->clear();
        return @\setcookie($this->name, '', [
            'expires'   => (\time() - 86400)
        ]);
    }

    /**
     * Kullanıcının browserına göndermeden önce çerez değerlerini imzalar ve encode eder.
     *
     * @return string
     */
    protected function encode(): string
    {
        $cookies = [];
        $now = \time();
        foreach ($this->storage->all() as $key => $value) {
            if($value['ttl'] !== null && $value['ttl'] > $now){
                continue;
            }
            $cookies[$key] = $value;
        }
        $cookies = \serialize($cookies);
        $data = [
            'cookies'   => $cookies,
            'signature' => \md5($this->salt . $cookies),
        ];
        $data = \serialize($data);
        return \base64_encode($data);
    }

    /**
     * Kullanıcının cihazından gelen çerezi alır ve decode ederek çözümler.
     * Eğer çerez üzerinde bir oynama varsa, çerezleri geçersiz sayar ve geçmiş çerezleri yok sayar.
     * @return array
     */
    protected function decode(): array
    {
        if(!isset($_COOKIE[$this->name]) || empty($_COOKIE[$this->name])){
            return [];
        }
        if(($cookies = \base64_decode($_COOKIE[$this->name])) === FALSE){
            return [];
        }
        if(($data = \unserialize($cookies)) === FALSE){
            return [];
        }
        if(!isset($data['cookies']) || !\is_string($data['cookies']) || !isset($data['signature']) || !\is_string($data['signature'])){
            return [];
        }
        $signature = \md5($this->salt . $data['cookies']);
        if($data['signature'] != $signature){
            $this->isChange = true;
            return [];
        }
        return \unserialize($data['cookies']);
    }

}
