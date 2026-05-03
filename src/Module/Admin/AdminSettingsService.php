<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Admin;

use A2BillingPlus\Module\Security\AuditLogRepository;

final class AdminSettingsService
{
    private const ALLOWLIST = [
        'admin_email' => ['type' => 'email', 'max' => 120],
        'base_currency' => ['type' => 'currency', 'max' => 3],
        'customer_ui_url' => ['type' => 'string', 'max' => 180],
        'manager_host' => ['type' => 'hostname', 'max' => 120],
        'smtp_host' => ['type' => 'hostname', 'max' => 120],
        'smtp_secure' => ['type' => 'enum', 'values' => ['', 'tls', 'ssl']],
        'use_realtime' => ['type' => 'bool'],
        'show_help' => ['type' => 'bool'],
    ];

    private const FORBIDDEN_PATTERNS = [
        '/secret/i',
        '/password/i',
        '/api.*key/i',
        '/MODULE_PAYMENT_/i',
    ];

    public function __construct(
        private readonly AdminSettingsRepository $repository,
        private readonly ?AuditLogRepository $auditLog = null
    ) {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function list(): array
    {
        return $this->repository->list(array_keys(self::ALLOWLIST));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function detail(string $key): ?array
    {
        if (!$this->isAllowed($key)) {
            return null;
        }

        return $this->repository->find($key);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function update(string $key, string $value, string $actor): array
    {
        if ($this->isForbidden($key) || !$this->isAllowed($key)) {
            return $this->error(403, 'setting_forbidden', 'This setting is not writable through the safe settings API.', 'key');
        }

        $validation = $this->validate($key, $value);
        if ($validation !== null) {
            return $validation;
        }

        $existing = $this->repository->find($key);
        if ($existing === null) {
            return $this->error(404, 'setting_not_found', 'Setting was not found.', 'key');
        }

        $updated = $this->repository->update($key, $this->normalize($key, $value));
        $this->auditLog?->record($actor, 'admin_setting.update', 'cc_config', (string)($updated['id'] ?? $key), [
            'key' => $key,
            'old_value' => $this->redact($key, (string)($existing['config_value'] ?? '')),
            'new_value' => $this->redact($key, (string)($updated['config_value'] ?? '')),
        ]);

        return ['status' => 200, 'body' => ['success' => true, 'setting' => $updated]];
    }

    private function isAllowed(string $key): bool
    {
        return isset(self::ALLOWLIST[$key]);
    }

    private function isForbidden(string $key): bool
    {
        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (preg_match($pattern, $key) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return null|array{status:int,body:array<string,mixed>}
     */
    private function validate(string $key, string $value): ?array
    {
        $rule = self::ALLOWLIST[$key];
        $value = trim($value);
        if (($rule['type'] ?? '') === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return $this->error(422, 'setting_validation_failed', 'Value must be a valid email address.', 'value');
        }
        if (($rule['type'] ?? '') === 'currency' && preg_match('/^[A-Za-z]{3}$/', $value) !== 1) {
            return $this->error(422, 'setting_validation_failed', 'Value must be a three-letter currency code.', 'value');
        }
        if (($rule['type'] ?? '') === 'bool' && !in_array(strtolower($value), ['0', '1', 'yes', 'no', 'true', 'false'], true)) {
            return $this->error(422, 'setting_validation_failed', 'Value must be boolean-like.', 'value');
        }
        if (($rule['type'] ?? '') === 'enum' && !in_array(strtolower($value), $rule['values'], true)) {
            return $this->error(422, 'setting_validation_failed', 'Value is not allowed for this setting.', 'value');
        }
        if (isset($rule['max']) && strlen($value) > (int)$rule['max']) {
            return $this->error(422, 'setting_validation_failed', 'Value is too long.', 'value');
        }

        return null;
    }

    private function normalize(string $key, string $value): string
    {
        $type = self::ALLOWLIST[$key]['type'] ?? '';
        $value = trim($value);
        if ($type === 'currency') {
            return strtolower($value);
        }
        if ($type === 'bool') {
            return in_array(strtolower($value), ['1', 'yes', 'true'], true) ? '1' : '0';
        }
        if ($type === 'enum') {
            return strtolower($value);
        }

        return $value;
    }

    private function redact(string $key, string $value): string
    {
        return $this->isForbidden($key) ? '[redacted]' : $value;
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    private function error(int $status, string $code, string $message, string $field): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'code' => $code, 'message' => $message, 'field' => $field]];
    }
}
