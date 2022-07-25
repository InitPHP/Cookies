<?php
/**
 * Cookie.php
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
use \InitPHP\ParameterBag\{ParameterBag, ParameterBagInterface};

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
        $this->send();
    }

    /**
     * @inheritDoc
     */
    public function send(): bool
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
        if(!\is_array($get)){
            return false;
        }
        if(($has = ($get['ttl'] === null || $get['ttl'] > \time())) === FALSE){
            $this->remove($key);
        }
        return $has;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, $default = null)
    {
        if(($get = $this->storage->get($key, null)) === null){
            return $default;
        }
        if(!\is_array($get)){
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
    public function pull(string $key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $value, ?int $ttl = null): CookieInterface
    {
        if(!\is_string($value) && !\is_bool($value) && !\is_numeric($value)){
            throw new CookieInvalidArgumentException('Cookie value can only be string, boolean or numeric.');
        }
        $ttl = $this->ttlCheck($ttl);
        $this->isChange = true;
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
    public function setArray(array $assoc, ?int $ttl = null): CookieInterface
    {
        $ttl = $this->ttlCheck($ttl);
        $cookies = [];
        foreach ($assoc as $key => $value) {
            if(!\is_string($key)){
                throw new CookieInvalidArgumentException("\$assoc must be an associative array.");
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
    public function push(string $key, $value, ?int $ttl = null)
    {
        $this->set($key, $value, $ttl);
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        $cookies = [];
        $now = \time();
        $removes = []; $all = $this->storage->all();
        foreach ($all as $key => $value) {
            if($value['ttl'] !== null && $value['ttl'] < $now){
                $removes[] = $key;
                continue;
            }
            $cookies[$key] = $value['value'];
        }
        unset($all);
        if(!empty($removes)){
            $this->remove(...$removes);
            unset($removes);
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
    public function flush(): bool
    {
        $this->isChange = true;
        $this->storage->clear();
        return true;
    }

    /**
     * @inheritDoc
     */
    public function destroy(): bool
    {
        $this->isChange = false;
        $delete = @\setcookie($this->name, '', [
            'expires'   => (\time() - 86400)
        ]);
        if($delete !== FALSE){
            $this->storage->clear();
        }
        return $delete;
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

    protected function ttlCheck(?int $ttl): ?int
    {
        if($ttl === null){
            return null;
        }
        $ttl = (int)\abs($ttl);
        if($ttl === 0){
            throw new CookieInvalidArgumentException("\$ttl can be null or a positive integer.");
        }
        return $ttl + \time();
    }

}
