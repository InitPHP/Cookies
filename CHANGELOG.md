# Changelog

All notable changes to `initphp/cookies` are documented here. The format is based
on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- CI now also runs the test suite on PHP 8.6 (8.2, 8.3, 8.4, 8.5 and 8.6).

## [3.0.1] - 2026-11-25

### Documentation

- The README and Configuration examples show `'secure' => true` (the default stays
  `false`; keep it on in production).
- The API reference, Quick Start and TTL and Expiry examples no longer store a
  one-time password in a cookie.

## [3.0.0] - 2026-11-22

### Changed (breaking)

- Requires `initphp/parameterbag` `^3.0` (was `^2.0`). Projects that depend on
  `initphp/parameterbag` directly must raise their constraint too.
- Requires PHP `^8.2` (was `^7.4 || ^8.0`); PHP 7.4, 8.0 and 8.1 are no longer
  supported. CI now covers PHP 8.2, 8.3, 8.4 and 8.5.
- Code style moved to PER-CS (php-cs-fixer, shared `.php-cs-fixer.dist.php`);
  the codebase has been reformatted accordingly.
