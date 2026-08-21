<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\Config;
use App\Exception\SignatureVerificationException;

class SignatureVerifier
{
    public function verify(string $rawBody, string $signatureHeader): void
    {
        if (!str_starts_with($signatureHeader, 'sha256=')) {
            throw new SignatureVerificationException('Invalid signature format');
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, Config::fbAppSecret());

        if (!hash_equals($expected, $signatureHeader)) {
            throw new SignatureVerificationException('Signature mismatch');
        }
    }
}
