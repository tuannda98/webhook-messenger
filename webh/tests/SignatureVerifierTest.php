<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\SignatureVerificationException;
use App\Service\SignatureVerifier;
use PHPUnit\Framework\TestCase;

class SignatureVerifierTest extends TestCase
{
    private SignatureVerifier $verifier;

    protected function setUp(): void
    {
        $_ENV['FB_APP_SECRET'] = 'test_secret';
        $this->verifier = new SignatureVerifier();
    }

    public function testValidSignature(): void
    {
        $body = '{"object":"page"}';
        $sig  = 'sha256=' . hash_hmac('sha256', $body, 'test_secret');

        $this->verifier->verify($body, $sig);
        $this->addToAssertionCount(1);
    }

    public function testInvalidSignatureThrows(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->verifier->verify('{"object":"page"}', 'sha256=invalidsig');
    }

    public function testMissingPrefixThrows(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->verifier->verify('body', 'invalid_format');
    }
}
