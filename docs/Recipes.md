# Recipes

Practical, copy-pasteable patterns built on top of the public API. Each recipe
is self-contained and links back into the reference material for the methods
it uses.

| Recipe | Solves |
| --- | --- |
| [Remember Me](Recipe-Remember-Me.md) | A long-lived signed login token — store an opaque selector/validator in the cookie, keep authority server-side. |
| [Flash Messages](Recipe-Flash-Messages.md) | One-time post-redirect messages using `pull()` and a short per-key TTL. |
| [Consent Cookie](Recipe-Consent-Cookie.md) | A long-lived cookie-consent / preference flag that gates analytics and supports withdrawal. |

If you have built something on top of the package that other users might
benefit from, please
[open an issue](https://github.com/InitPHP/Cookies/issues/new) with a sketch —
recipe contributions are welcomed.

## Before you reach for a recipe

Every recipe rests on the same two rules:

- **The client can read the cookie.** Store opaque references, never secrets.
  See the [Security Model](Security-Model.md#integrity-is-not-secrecy).
- **Nothing is written until `send()`.** Issue, refresh, and delete all
  require a `send()` before output. See [Sending & Lifecycle](Sending-and-Lifecycle.md).

## See also

- [Quick Start](Quick-Start.md) — the five-minute tour the recipes build on.
- [Configuration](Configuration.md) — the cookie attributes each recipe sets.
- [API Reference](API-Reference.md) — every method the recipes call.
