<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Security;

final class CsrfTokenService
{
    public function issue(string $sessionId, string $formName, ?string $nonce = null): string
    {
        $nonce ??= bin2hex(random_bytes(16));
        $signature = hash_hmac('sha256', $sessionId . '|' . $formName . '|' . $nonce, $sessionId);

        return $nonce . ':' . $signature;
    }

    public function verify(string $sessionId, string $formName, string $token): bool
    {
        [$nonce, $signature] = array_pad(explode(':', $token, 2), 2, '');
        if ($nonce === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $sessionId . '|' . $formName . '|' . $nonce, $sessionId);

        return hash_equals($expected, $signature);
    }
}
