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
        $requestIp = $request->getRequestIp() !== '' ? $request->getRequestIp() : '0.0.0.0';
        $accountNumber = 'VV' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
        $metadata = [
            'provider' => 'vectavoip',
            'mode' => 'production',
            'region' => 'us',
            'account_number' => $accountNumber,
            'registered_ip' => $requestIp,
            'allowed_ips' => $requestIp . '/32',
            'portal_username' => $request->getUsername(),
            'portal_password_hash' => password_hash($request->getPassword(), PASSWORD_DEFAULT),
            'available_packages_json' => json_encode($this->defaultPackages(), JSON_UNESCAPED_SLASHES),
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
            $request->getUsername(),
            $request->getContactEmail(),
            '',
            '',
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
        if ($secretHash === '' || $apiSecret === '' || !password_verify($apiSecret, $secretHash)) {
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

    /**
     * @param array<string, string> $account
     * @return array<string, mixed>
     */
    public function createProviderAccount(string $installationId, array $account): array
    {
        $this->ensureProviderApiTables();
        if (($account['sip_username'] ?? '') === '') {
            $account['sip_username'] = 'sip_' . strtolower(bin2hex(random_bytes(5)));
        }
        if (($account['sip_password'] ?? '') === '') {
            $account['sip_password'] = bin2hex(random_bytes(12));
        }

        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO cc_vectavoip_provider_accounts
                (installation_id, external_id, name, email, phone, company, contact_methods_json, sip_username, sip_password_hash, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $installationId,
            $account['external_id'] ?? '',
            $account['name'],
            $account['email'],
            $account['phone'] ?? '',
            $account['company'] ?? '',
            $account['contact_methods_json'],
            $account['sip_username'],
            password_hash($account['sip_password'], PASSWORD_DEFAULT),
            'active',
            $now,
            $now,
        ]);

        $row = $this->findProviderAccount($installationId, (int)$this->pdo->lastInsertId()) ?? [];
        $row['sip_password'] = $account['sip_password'];

        return $row;
    }

    /**
     * @return null|array<string, mixed>
     */
    public function findProviderAccount(string $installationId, int $accountId): ?array
    {
        $this->ensureProviderApiTables();
        $statement = $this->pdo->prepare(
            'SELECT id, installation_id, external_id, name, email, phone, company, contact_methods_json, sip_username, status, created_at, updated_at
             FROM cc_vectavoip_provider_accounts
             WHERE installation_id = ? AND id = ?
             LIMIT 1'
        );
        $statement->execute([$installationId, $accountId]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function availableDids(int $limit, int $offset, string $country = '', string $region = ''): array
    {
        $this->ensureProviderApiTables();
        $where = ['status = ?'];
        $params = ['available'];
        if ($country !== '') {
            $where[] = 'country = ?';
            $params[] = $country;
        }
        if ($region !== '') {
            $where[] = 'region = ?';
            $params[] = $region;
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM cc_vectavoip_did_inventory {$whereSql}");
        $count->execute($params);

        $list = $this->pdo->prepare(
            "SELECT id, did, country, region, monthly_rate, setup_rate, currency, status, provider_reference
             FROM cc_vectavoip_did_inventory {$whereSql}
             ORDER BY did ASC LIMIT ? OFFSET ?"
        );
        foreach ($params as $index => $param) {
            $list->bindValue($index + 1, $param);
        }
        $list->bindValue(count($params) + 1, $limit, \PDO::PARAM_INT);
        $list->bindValue(count($params) + 2, $offset, \PDO::PARAM_INT);
        $list->execute();

        return ['items' => $list->fetchAll(\PDO::FETCH_ASSOC), 'total' => (int)$count->fetchColumn()];
    }

    /**
     * @param list<string> $features
     * @return array<string, mixed>
     */
    public function purchaseDid(
        string $installationId,
        int $accountId,
        string $did,
        array $features,
        string $routingDestination,
        string $webhookUrl
    ): array {
        $this->ensureProviderApiTables();
        if ($this->findProviderAccount($installationId, $accountId) === null) {
            return ['success' => false, 'status' => 404, 'message' => 'Provider account was not found.'];
        }

        $inventory = $this->findDidInventory($did);
        if ($inventory === null) {
            return ['success' => false, 'status' => 404, 'message' => 'DID was not found.'];
        }
        if (($inventory['status'] ?? '') !== 'available') {
            return ['success' => false, 'status' => 409, 'message' => 'DID is not available.'];
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO cc_vectavoip_did_purchases
                    (installation_id, account_id, did, features_json, routing_destination, webhook_url, status, purchased_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $installationId,
                $accountId,
                $did,
                json_encode($features, JSON_UNESCAPED_SLASHES) ?: '[]',
                $routingDestination,
                $webhookUrl,
                'active',
                $now,
                $now,
            ]);
            $purchaseId = (int)$this->pdo->lastInsertId();
            $update = $this->pdo->prepare("UPDATE cc_vectavoip_did_inventory SET status = 'assigned', updated_at = ? WHERE did = ?");
            $update->execute([$now, $did]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return ['success' => true, 'purchase' => $this->findDidPurchase($purchaseId)];
    }

    /**
     * @param list<string> $features
     * @return array<string, mixed>
     */
    public function recordUpstreamDidPurchase(
        string $installationId,
        int $accountId,
        string $did,
        array $features,
        string $routingDestination,
        string $webhookUrl,
        string $providerCode,
        string $providerReference,
        string $providerTrunkReference,
        string $providerTrunkName
    ): array {
        $this->ensureProviderApiTables();
        if ($this->findProviderAccount($installationId, $accountId) === null) {
            throw new \RuntimeException('Provider account was not found.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $this->upsertDidInventory([
                'did' => $did,
                'country' => '',
                'region' => '',
                'monthly_rate' => '0.00000',
                'setup_rate' => '0.00000',
                'currency' => 'USD',
                'status' => 'assigned',
                'provider_code' => $providerCode,
                'provider_reference' => $providerReference,
                'provider_trunk_reference' => $providerTrunkReference,
                'provider_trunk_name' => $providerTrunkName,
                'order_reference' => '',
            ], $now);

            $insert = $this->pdo->prepare(
                'INSERT INTO cc_vectavoip_did_purchases
                    (installation_id, account_id, did, features_json, routing_destination, webhook_url, status, purchased_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $installationId,
                $accountId,
                $did,
                json_encode($features, JSON_UNESCAPED_SLASHES) ?: '[]',
                $routingDestination,
                $webhookUrl,
                'active',
                $now,
                $now,
            ]);
            $purchaseId = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->findDidPurchase($purchaseId);
    }

    public function accountIdForDid(string $installationId, string $did): int
    {
        $this->ensureProviderApiTables();
        $statement = $this->pdo->prepare(
            "SELECT account_id FROM cc_vectavoip_did_purchases
             WHERE installation_id = ? AND did = ? AND status = 'active'
             ORDER BY id DESC LIMIT 1"
        );
        $statement->execute([$installationId, $did]);

        return (int)($statement->fetchColumn() ?: 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function recordSms(
        string $installationId,
        int $accountId,
        string $from,
        string $to,
        string $body,
        string $direction,
        string $status,
        string $gatewayMessageId
    ): array {
        $this->ensureProviderApiTables();
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO cc_vectavoip_sms_messages
                (installation_id, account_id, from_number, to_number, body, direction, status, gateway_message_id, error_message, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([$installationId, $accountId, $from, $to, $body, $direction, $status, $gatewayMessageId, '', $now, $now]);

        $find = $this->pdo->prepare('SELECT * FROM cc_vectavoip_sms_messages WHERE id = ?');
        $find->execute([(int)$this->pdo->lastInsertId()]);
        $row = $find->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    public function getSetting(string $key, string $default = ''): string
    {
        $this->ensureSettingsTable();
        $statement = $this->pdo->prepare('SELECT setting_value FROM cc_vectavoip_provider_settings WHERE setting_key = ? LIMIT 1');
        $statement->execute([$key]);
        $value = $statement->fetchColumn();

        return is_scalar($value) && (string)$value !== '' ? (string)$value : $default;
    }

    public function getRuntimeSetting(string $key, string $default = ''): string
    {
        $this->ensureRuntimeSettingsTable();
        $statement = $this->pdo->prepare('SELECT setting_value FROM cc_a2bp_runtime_settings WHERE setting_key = ? LIMIT 1');
        $statement->execute([$key]);
        $value = $statement->fetchColumn();

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function ensureRuntimeSettingsTable(): void
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS cc_a2bp_runtime_settings (
                    setting_key TEXT NOT NULL PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    is_secret INTEGER NOT NULL DEFAULT 0,
                    updated_at TEXT NOT NULL
                )'
            );
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_a2bp_runtime_settings (
                setting_key VARCHAR(191) NOT NULL,
                setting_value TEXT NOT NULL,
                is_secret TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function setSetting(string $key, string $value): void
    {
        $this->ensureSettingsTable();
        $now = gmdate('Y-m-d H:i:s');
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare(
                'INSERT INTO cc_vectavoip_provider_settings (setting_key, setting_value, updated_at)
                 VALUES (?, ?, ?)
                 ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at'
            );
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO cc_vectavoip_provider_settings (setting_key, setting_value, updated_at)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)'
            );
        }
        $statement->execute([$key, $value, $now]);
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

    private function ensureProviderApiTables(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS cc_vectavoip_provider_accounts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    installation_id TEXT NOT NULL,
                    external_id TEXT NOT NULL DEFAULT \'\',
                    name TEXT NOT NULL,
                    email TEXT NOT NULL,
                    phone TEXT NOT NULL DEFAULT \'\',
                    company TEXT NOT NULL DEFAULT \'\',
                    contact_methods_json TEXT NOT NULL,
                    sip_username TEXT NOT NULL,
                    sip_password_hash TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT \'active\',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )'
            );
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS cc_vectavoip_did_inventory (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    did TEXT NOT NULL UNIQUE,
                    country TEXT NOT NULL DEFAULT \'\',
                    region TEXT NOT NULL DEFAULT \'\',
                    monthly_rate TEXT NOT NULL DEFAULT \'0.00000\',
                    setup_rate TEXT NOT NULL DEFAULT \'0.00000\',
                    currency TEXT NOT NULL DEFAULT \'USD\',
                    status TEXT NOT NULL DEFAULT \'available\',
                    provider_reference TEXT NOT NULL DEFAULT \'\',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )'
            );
            $this->ensureSqliteColumn('cc_vectavoip_did_inventory', 'provider_code', "TEXT NOT NULL DEFAULT ''");
            $this->ensureSqliteColumn('cc_vectavoip_did_inventory', 'provider_trunk_reference', "TEXT NOT NULL DEFAULT ''");
            $this->ensureSqliteColumn('cc_vectavoip_did_inventory', 'provider_trunk_name', "TEXT NOT NULL DEFAULT ''");
            $this->ensureSqliteColumn('cc_vectavoip_did_inventory', 'order_reference', "TEXT NOT NULL DEFAULT ''");
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS cc_vectavoip_did_purchases (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    installation_id TEXT NOT NULL,
                    account_id INTEGER NOT NULL,
                    did TEXT NOT NULL,
                    features_json TEXT NOT NULL,
                    routing_destination TEXT NOT NULL DEFAULT \'\',
                    webhook_url TEXT NOT NULL DEFAULT \'\',
                    status TEXT NOT NULL DEFAULT \'active\',
                    purchased_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )'
            );
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS cc_vectavoip_sms_messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    installation_id TEXT NOT NULL,
                    account_id INTEGER NOT NULL DEFAULT 0,
                    from_number TEXT NOT NULL,
                    to_number TEXT NOT NULL,
                    body TEXT NOT NULL,
                    direction TEXT NOT NULL,
                    status TEXT NOT NULL,
                    gateway_message_id TEXT NOT NULL DEFAULT \'\',
                    error_message TEXT NOT NULL DEFAULT \'\',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )'
            );
            $this->ensureSettingsTable();
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_vectavoip_provider_accounts (
                id BIGINT NOT NULL AUTO_INCREMENT,
                installation_id VARCHAR(64) NOT NULL,
                external_id VARCHAR(128) NOT NULL DEFAULT \'\',
                name VARCHAR(191) NOT NULL,
                email VARCHAR(191) NOT NULL,
                phone VARCHAR(64) NOT NULL DEFAULT \'\',
                company VARCHAR(191) NOT NULL DEFAULT \'\',
                contact_methods_json TEXT NOT NULL,
                sip_username VARCHAR(128) NOT NULL,
                sip_password_hash VARCHAR(128) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'active\',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_vectavoip_provider_accounts_installation (installation_id),
                KEY idx_vectavoip_provider_accounts_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_vectavoip_did_inventory (
                id BIGINT NOT NULL AUTO_INCREMENT,
                did VARCHAR(64) NOT NULL,
                country VARCHAR(64) NOT NULL DEFAULT \'\',
                region VARCHAR(64) NOT NULL DEFAULT \'\',
                monthly_rate DECIMAL(15,5) NOT NULL DEFAULT 0.00000,
                setup_rate DECIMAL(15,5) NOT NULL DEFAULT 0.00000,
                currency VARCHAR(3) NOT NULL DEFAULT \'USD\',
                status VARCHAR(32) NOT NULL DEFAULT \'available\',
                provider_code VARCHAR(32) NOT NULL DEFAULT \'\',
                provider_reference VARCHAR(128) NOT NULL DEFAULT \'\',
                provider_trunk_reference VARCHAR(128) NOT NULL DEFAULT \'\',
                provider_trunk_name VARCHAR(128) NOT NULL DEFAULT \'\',
                order_reference VARCHAR(128) NOT NULL DEFAULT \'\',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_vectavoip_did_inventory_did (did),
                KEY idx_vectavoip_did_inventory_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $this->ensureMysqlColumn('cc_vectavoip_did_inventory', 'provider_code', "VARCHAR(32) NOT NULL DEFAULT ''");
        $this->ensureMysqlColumn('cc_vectavoip_did_inventory', 'provider_trunk_reference', "VARCHAR(128) NOT NULL DEFAULT ''");
        $this->ensureMysqlColumn('cc_vectavoip_did_inventory', 'provider_trunk_name', "VARCHAR(128) NOT NULL DEFAULT ''");
        $this->ensureMysqlColumn('cc_vectavoip_did_inventory', 'order_reference', "VARCHAR(128) NOT NULL DEFAULT ''");
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_vectavoip_did_purchases (
                id BIGINT NOT NULL AUTO_INCREMENT,
                installation_id VARCHAR(64) NOT NULL,
                account_id BIGINT NOT NULL,
                did VARCHAR(64) NOT NULL,
                features_json TEXT NOT NULL,
                routing_destination VARCHAR(191) NOT NULL DEFAULT \'\',
                webhook_url VARCHAR(255) NOT NULL DEFAULT \'\',
                status VARCHAR(32) NOT NULL DEFAULT \'active\',
                purchased_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_vectavoip_did_purchases_installation (installation_id),
                KEY idx_vectavoip_did_purchases_account (account_id),
                KEY idx_vectavoip_did_purchases_did (did)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $this->ensureSettingsTable();
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_vectavoip_sms_messages (
                id BIGINT NOT NULL AUTO_INCREMENT,
                installation_id VARCHAR(64) NOT NULL,
                account_id BIGINT NOT NULL DEFAULT 0,
                from_number VARCHAR(64) NOT NULL,
                to_number VARCHAR(64) NOT NULL,
                body TEXT NOT NULL,
                direction VARCHAR(16) NOT NULL,
                status VARCHAR(32) NOT NULL,
                gateway_message_id VARCHAR(128) NOT NULL DEFAULT \'\',
                error_message TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_vectavoip_sms_installation (installation_id),
                KEY idx_vectavoip_sms_account (account_id),
                KEY idx_vectavoip_sms_numbers (from_number, to_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return null|array<string,mixed>
     */
    private function findDidInventory(string $did): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM cc_vectavoip_did_inventory WHERE did = ? LIMIT 1');
        $statement->execute([$did]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, string> $did
     */
    private function upsertDidInventory(array $did, string $now): void
    {
        $columns = [
            'did',
            'country',
            'region',
            'monthly_rate',
            'setup_rate',
            'currency',
            'status',
            'provider_code',
            'provider_reference',
            'provider_trunk_reference',
            'provider_trunk_name',
            'order_reference',
            'created_at',
            'updated_at',
        ];
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare(
                'INSERT INTO cc_vectavoip_did_inventory (' . implode(', ', $columns) . ')
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON CONFLICT(did) DO UPDATE SET
                    status = excluded.status,
                    provider_code = excluded.provider_code,
                    provider_reference = excluded.provider_reference,
                    provider_trunk_reference = excluded.provider_trunk_reference,
                    provider_trunk_name = excluded.provider_trunk_name,
                    order_reference = excluded.order_reference,
                    updated_at = excluded.updated_at'
            );
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO cc_vectavoip_did_inventory (' . implode(', ', $columns) . ')
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    provider_code = VALUES(provider_code),
                    provider_reference = VALUES(provider_reference),
                    provider_trunk_reference = VALUES(provider_trunk_reference),
                    provider_trunk_name = VALUES(provider_trunk_name),
                    order_reference = VALUES(order_reference),
                    updated_at = VALUES(updated_at)'
            );
        }
        $statement->execute([
            $did['did'],
            $did['country'],
            $did['region'],
            $did['monthly_rate'],
            $did['setup_rate'],
            $did['currency'],
            $did['status'],
            $did['provider_code'],
            $did['provider_reference'],
            $did['provider_trunk_reference'],
            $did['provider_trunk_name'],
            $did['order_reference'],
            $now,
            $now,
        ]);
    }

    private function ensureSettingsTable(): void
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS cc_vectavoip_provider_settings (
                    setting_key TEXT NOT NULL PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )'
            );
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_vectavoip_provider_settings (
                setting_key VARCHAR(128) NOT NULL,
                setting_value TEXT NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureSqliteColumn(string $table, string $column, string $definition): void
    {
        $statement = $this->pdo->query('PRAGMA table_info(' . $table . ')');
        $rows = $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
        foreach ($rows as $row) {
            if ((string)($row['name'] ?? '') === $column) {
                return;
            }
        }

        $this->pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }

    private function ensureMysqlColumn(string $table, string $column, string $definition): void
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute([$table, $column]);
        if ((int)$statement->fetchColumn() > 0) {
            return;
        }

        $this->pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }

    /**
     * @return array<string,mixed>
     */
    private function findDidPurchase(int $purchaseId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM cc_vectavoip_did_purchases WHERE id = ? LIMIT 1');
        $statement->execute([$purchaseId]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [];
        }
        $features = json_decode((string)($row['features_json'] ?? '[]'), true);
        $row['features'] = is_array($features) ? array_values($features) : [];
        unset($row['features_json']);

        return $row;
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

    /**
     * @return list<array<string, string>>
     */
    private function defaultPackages(): array
    {
        return [
            ['code' => 'starter', 'name' => 'Starter SIP', 'billing' => 'monthly', 'price' => '29.00'],
            ['code' => 'business', 'name' => 'Business Voice', 'billing' => 'monthly', 'price' => '79.00'],
            ['code' => 'wholesale', 'name' => 'Wholesale Origination', 'billing' => 'monthly', 'price' => '199.00'],
        ];
    }
}
