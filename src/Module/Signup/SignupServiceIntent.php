<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Signup;

final class SignupServiceIntent
{
    public const SESSION_KEY = 'vectavoip_signup_service';

    private const PREFIX = 'VectaVoIP service: ';

    private string $service;

    private function __construct(string $service)
    {
        $this->service = $service;
    }

    public static function fromRaw(?string $service): self
    {
        $service = trim((string) $service);
        $service = preg_replace('/\s+/', ' ', $service) ?? '';
        $service = preg_replace('/[^a-zA-Z0-9 _.\-\/+&]/', '', $service) ?? '';
        $service = trim(str_replace(',', ' ', $service));

        return new self(substr($service, 0, 80));
    }

    public function hasService(): bool
    {
        return $this->service !== '';
    }

    public function service(): string
    {
        return $this->service;
    }

    public function queryString(): string
    {
        if (!$this->hasService()) {
            return '';
        }

        return 'service=' . rawurlencode($this->service);
    }

    public function note(): string
    {
        if (!$this->hasService()) {
            return '';
        }

        return self::PREFIX . $this->service;
    }

    public function mergeTrafficTarget(?string $existing): string
    {
        $existing = trim((string) $existing);
        $note = $this->note();

        if ($note === '') {
            return substr($existing, 0, 300);
        }

        if ($existing === '') {
            return $note;
        }

        if (stripos($existing, $note) !== false) {
            return substr($existing, 0, 300);
        }

        return substr($existing . ' | ' . $note, 0, 300);
    }
}
