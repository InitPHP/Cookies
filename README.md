# InitPHP Cookies Manager

This library aims to make cookies easier to manage and a little bit more secure. It signs cookies with a (secret) salt and prevents data from being modified by the user.

## Requirements

- PHP 7.2 or later
- [InitPHP ParameterBag Library](https://github.com/InitPHP/ParameterBag)

## Installation

```
composer require initphp/cookies
```

## Configuration

```php
$options = [
        'ttl'       => 2592000, // 30 days
        'path'      => '/',
        'domain'    => null,
        'secure'    => false,
        'httponly'  => true,
        'samesite'  => 'Strict'
];
```

## Usage

```php
require_once __DIR__ . "/vendor/autoload.php";
use InitPHP\Cookies\Cookie;

$cookie = new Cookie('cookie_name', 's£cr£t_s@lt', []);
$cookie->set('username', 'sdd');
```

## Methods

```php
public function has(string $key): bool;
```

```php
public function get(string $key, $default = null): mixed;
```

```php
public function set(string $key, string|bool|int|float $value, ?int $ttl = null): self;
```

```php
public function setArray(string[] $array, ?int $ttl = null): self;
```

```php
public function remove(string ...$key): bool;
```

```php
public function push(): bool;
```

```php
public function all(): array;
```

```php
public function destroy(): bool;
```


## Credits

- [Muhammet ŞAFAK](https://github.com/muhammetsafak) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE)
