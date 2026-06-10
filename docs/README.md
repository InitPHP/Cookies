# Documentation

Developer documentation for the `initphp/cookies` package — a signed,
tamper-evident cookie manager for PHP. The project
[README](../README.md) gives a one-page overview; this directory goes
deeper.

## Index

- [Getting started](getting-started.md) — install, construct with a
  salt, set a value, send before output, read on the next request.
- **Usage**
  - [Basic usage](usage/basic-usage.md) — `set`, `get`, `has`,
    `remove`, scalar type preservation and value validation.
  - [TTL and expiry](usage/ttl-and-expiry.md) — per-key TTL vs. the
    `ttl` option, expiry-on-read, `null`/zero/negative TTL.
  - [Reading and removing](usage/reading-and-removing.md) — `get` vs.
    `pull`, `all`, `remove`, `flush` vs. `destroy`.
  - [Sending and lifecycle](usage/sending-and-lifecycle.md) — staged
    writes, `send()` no-op semantics, the destructor safety-net,
    headers-before-output.
- [Configuration options](configuration.md) — full options reference.
- [Security model](security-model.md) — how signing works, what it
  protects, salt management, object-injection hardening.
- [API reference](api-reference.md) — the constructor and every public
  method, listed.
- [Exceptions](exceptions.md) — when and why the package throws.
- **Recipes**
  - [Remember me](recipes/remember-me.md) — a long-lived signed login
    token cookie.
  - [Flash messages](recipes/flash-messages.md) — short-TTL one-time
    messages with `pull`.
  - [Testing cookies](recipes/testing-cookies.md) — unit-test code
    that uses `Cookie` with an injected source and writer.
- [Upgrading from 1.x](upgrading-from-1.x.md) — BC notes for 2.0.
- [FAQ](faq.md) — common pitfalls and clarifications.

## How to read these docs

Every page is structured as **Goal → Working example → Expected output
→ Common mistakes**. Snippets are copy-paste ready against the released
package; the behaviors they show are pinned by the package test suite.

## What this package is (and is not)

`Cookie` stores several scalar values inside a **single** browser
cookie and signs the whole payload with HMAC-SHA256. The signature
proves the data was issued by you (integrity and authenticity) and
makes client-side tampering detectable. It does **not** encrypt the
values — the client can read them. See the
[security model](security-model.md) before storing anything sensitive.
