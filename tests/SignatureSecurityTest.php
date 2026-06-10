<?php

declare(strict_types=1);

namespace InitPHP\Cookies\Tests;

use InitPHP\Cookies\Tests\Fixture\MaliciousProbe;

use function base64_encode;
use function hash_hmac;
use function serialize;
use function time;

/**
 * Security tests for the signed, hardened cookie envelope.
 *
 * Covers tamper detection (HMAC-SHA256 + hash_equals), rejection of
 * malformed input, and the object-injection guard
 * (`allowed_classes => false`, deserialization gated behind the
 * signature check).
 */
final class SignatureSecurityTest extends CookieTestCase
{
    public function testValidSignatureIsAccepted(): void
    {
        $source = [self::NAME => $this->encodeCookie([
            'user' => ['value' => 'ada', 'ttl' => null],
        ])];

        $cookie = $this->cookie([], $source);

        self::assertSame('ada', $cookie->get('user'));
    }

    public function testTamperedPayloadIsRejected(): void
    {
        $valid = $this->encodeCookie(['user' => ['value' => 'ada', 'ttl' => null]]);
        // Flip the first character of the payload; the stored signature
        // no longer matches the mutated bytes.
        $tampered = ($valid[0] === 'A' ? 'B' : 'A') . substr($valid, 1);

        $cookie = $this->cookie([], [self::NAME => $tampered]);

        self::assertNull($cookie->get('user'));
        self::assertSame([], $cookie->all());
    }

    public function testTamperedPayloadIsReissuedClean(): void
    {
        $valid = $this->encodeCookie(['user' => ['value' => 'ada', 'ttl' => null]]);
        $tampered = ($valid[0] === 'A' ? 'B' : 'A') . substr($valid, 1);

        $cookie = $this->cookie([], [self::NAME => $tampered]);
        $cookie->send();

        self::assertCount(1, $this->written);
        $reader = $this->cookie([], [self::NAME => $this->lastWrittenValue()]);
        self::assertSame([], $reader->all());
    }

    public function testSignatureFromADifferentSaltIsRejected(): void
    {
        $source = [self::NAME => $this->encodeCookie(
            ['user' => ['value' => 'ada', 'ttl' => null]],
            'a-different-salt'
        )];

        $cookie = $this->cookie([], $source);

        self::assertNull($cookie->get('user'));
    }

    public function testMissingDelimiterIsRejected(): void
    {
        $cookie = $this->cookie([], [self::NAME => 'no-delimiter-here']);

        self::assertSame([], $cookie->all());
    }

    public function testInvalidBase64PayloadIsRejected(): void
    {
        $payload = '!!!not-base64!!!';
        $value = $payload . '.' . hash_hmac('sha256', $payload, self::SALT);

        $cookie = $this->cookie([], [self::NAME => $value]);

        self::assertSame([], $cookie->all());
    }

    public function testNonArrayPayloadIsRejected(): void
    {
        // A correctly signed payload whose content is a scalar, not the
        // expected entries map.
        $payload = base64_encode(serialize('i am not an array'));
        $value = $payload . '.' . hash_hmac('sha256', $payload, self::SALT);

        $cookie = $this->cookie([], [self::NAME => $value]);

        self::assertSame([], $cookie->all());
    }

    public function testEmptyCookieValueIsIgnored(): void
    {
        $cookie = $this->cookie([], [self::NAME => '']);

        self::assertSame([], $cookie->all());
    }

    public function testObjectInjectionIsNeutralized(): void
    {
        MaliciousProbe::$awoken = false;

        // A correctly signed payload that smuggles a serialized object.
        // Even with a valid signature, deserialization must not wake the
        // object, because instantiation is forbidden.
        $payload = base64_encode(serialize(['x' => new MaliciousProbe()]));
        $value = $payload . '.' . hash_hmac('sha256', $payload, self::SALT);

        $cookie = $this->cookie([], [self::NAME => $value]);

        self::assertFalse(MaliciousProbe::$awoken, 'allowed_classes guard failed: __wakeup ran');
        // The smuggled object is not a valid entry, so it reads as absent.
        self::assertNull($cookie->get('x'));
    }

    public function testForgedSignatureCannotExtendExpiry(): void
    {
        // Without the salt an attacker cannot forge a signature, so a
        // hand-built (unsigned-by-us) future-dated entry is rejected.
        $payload = base64_encode(serialize([
            'admin' => ['value' => true, 'ttl' => time() + 999999],
        ]));
        $forgedSignature = hash_hmac('sha256', $payload, 'attacker-guess');
        $value = $payload . '.' . $forgedSignature;

        $cookie = $this->cookie([], [self::NAME => $value]);

        self::assertNull($cookie->get('admin'));
    }
}
