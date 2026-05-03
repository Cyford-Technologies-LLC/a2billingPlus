<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

final class VectaVoIPInstallationRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
        $this->ensureTable();
    }

    /**
     * @param array<string, string> $metadata
     * @return array<string, string>
     */
    public function register(VectaVoIPRegistrationRequest $request): array
    {
        $existing = $this->findByInstallKey($request->getInstallKey());
        if ($existing !== null) {
            return $existing;
        }

        $installationId = 'inst_' . bin2hex(random_bytes(12));
        $apiKey = 'vvp_' . bin2hex(random_bytes(24));
        $apiSecret = 'vvs_' . bin2hex(random_bytes(32));
        $now = gmdate('Y-m-d H:i:s');
        $metadata = [
            'provider' => 'vectavoip',
            'mode' => 'production',
            'region' => 'us',
        ];

        $statement = $this->pdo->prepare(
            'INSERT INTO cc_vectavoip_installations
                (installation_id, install_key, api_key, api_secret_hash, company_name, company_domain,
                 contact_name, contact_email, contact_phone, details, app_name, app_version, status,
                 metadata_json, created_at, updated_at)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $installationId,
            $request->getInstallKey(),
            $apiKey,
            password_hash($apiSecret, PASSWORD_DEFAULT),
            $request->getCompanyName(),
            $request->getCompanyDomain(),
            $request->getContactName(),
            $request->getContactEmail(),
            $request->getContactPhone(),
            $request->getDetails(),
            $request->getAppName(),
            $request->getAppVersion(),
            'active',
            json_encode($metadata, JSON_UNESCAPED_SLASHES),
            $now,
            $now,
        ]);

        return [
            'installation_id' => $installationId,
            'install_key' => $request->getInstallKey(),
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'status' => 'active',
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}',
        ];
    }

    /**
     * @return null|array<string, string>
     */
    public function findByInstallKey(string $installKey): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT installation_id, install_key, api_key, status, metadata_json
             FROM cc_vectavoip_installations
             WHERE install_key = ?
             LIMIT 1'
        );
        $statement->execute([$installKey]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $this->stringRow($row) : null;
    }

    /**
     * @return null|array<string, string>
     */
    public function findByApiKey(string $apiKey): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT installation_id, install_key, api_key, api_secret_hash, status, metadata_json
             FROM cc_vectavoip_installations
             WHERE api_key = ?
             LIMIT 1'
        );
        $statement->execute([$apiKey]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $this->stringRow($row) : null;
    }

    /**
     * @return null|array<string, string>
     */
    public function verifyCredentials(string $apiKey, string $apiSecret = ''): ?array
    {
        $installation = $this->findByApiKey($apiKey);
        if ($installation === null || ($installation['status'] ?? '') !== 'active') {
            return null;
        }

        $secretHash = $installation['api_secret_hash'] ?? '';
        if ($secretHash !== '' && $apiSecret !== '' && !password_verify($apiSecret, $secretHash)) {
            return null;
        }

        $this->touchLastSeen($installation['installation_id']);

        return $installation;
    }

    /**
     * @return null|array<string, string>
     */
    public function rotateSecret(string $apiKey, string $apiSecret): ?array
    {
        $installation = $this->verifyCredentials($apiKey, $apiSecret);
        if ($installation === null) {
            return null;
        }

        $newSecret = 'vvs_' . bin2hex(random_bytes(32));
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE cc_vectavoip_installations SET api_secret_hash = ?, updated_at = ? WHERE api_key = ?'
        );
        $statement->execute([password_hash($newSecret, PASSWORD_DEFAULT), $now, $apiKey]);

        $installation['api_secret'] = $newSecret;
        $installation['updated_at'] = $now;

        return $installation;
    }

    private function touchLastSeen(string $installationId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE cc_vectavoip_installations SET last_seen_at = ?, updated_at = ? WHERE installation_id = ?'
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([$now, $now, $installationId]);
    }

    private function ensureTable(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS cc_vectavoip_installations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    installation_id TEXT NOT NULL UNIQUE,
                    install_key TEXT NOT NULL UNIQUE,
                    api_key TEXT NOT NULL UNIQUE,
                    api_secret_hash TEXT NOT NULL,
                    company_name TEXT NOT NULL,
                    company_domain TEXT NOT NULL,
                    contact_name TEXT NOT NULL,
                    contact_email TEXT NOT NULL,
                    contact_phone TEXT NOT NULL DEFAULT \'\',
                    details TEXT NULL,
                    app_name TEXT NOT NULL,
                    app_version TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT \'active\',
                    metadata_json TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    last_seen_at TEXT NULL
                )'
            );
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_vectavoip_installations (
                id BIGINT NOT NULL AUTO_INCREMENT,
                installation_id VARCHAR(64) NOT NULL,
                install_key VARCHAR(128) NOT NULL,
                api_key VARCHAR(128) NOT NULL,
                api_secret_hash VARCHAR(128) NOT NULL,
                company_name VARCHAR(191) NOT NULL,
                company_domain VARCHAR(191) NOT NULL,
                contact_name VARCHAR(191) NOT NULL,
                contact_email VARCHAR(191) NOT NULL,
                contact_phone VARCHAR(64) NOT NULL DEFAULT \'\',
                details TEXT NULL,
                app_name VARCHAR(128) NOT NULL,
                app_version VARCHAR(64) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'active\',
                metadata_json TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                last_seen_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_vectavoip_installation_id (installation_id),
                UNIQUE KEY uniq_vectavoip_install_key (install_key),
                UNIQUE KEY uniq_vectavoip_api_key (api_key),
                KEY idx_vectavoip_contact_email (contact_email),
                KEY idx_vectavoip_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function stringRow(array $row): array
    {
        $mapped = [];
        foreach ($row as $key => $value) {
            if (is_scalar($value)) {
                $mapped[(string)$key] = (string)$value;
            }
        }

        return $mapped;
    }
}
