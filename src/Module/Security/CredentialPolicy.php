<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Security;

final class CredentialPolicy
{
    private const DEFAULT_CREDENTIALS = [
        'root:changepassword',
        'root:password',
        'admin:admin',
        'admin:password',
        'a2billing:a2billing',
        'a2billinguser:a2billing',
    ];

    public function isDefaultCredential(string $username, string $password): bool
    {
        return in_array(strtolower(trim($username) . ':' . trim($password)), self::DEFAULT_CREDENTIALS, true);
    }

    public function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 16
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1;
    }
}
