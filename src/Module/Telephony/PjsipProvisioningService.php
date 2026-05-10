<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

use A2BillingPlus\Module\Security\AuditLogRepository;

final class PjsipProvisioningService
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly ?AuditLogRepository $auditLog = null
    ) {
        $this->ensureTables();
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function provisionCustomerDevice(array $payload, string $actor): array
    {
        $customerId = $this->intValue($payload, 'customer_id');
        $extension = $this->stringValue($payload, 'extension') ?: $this->stringValue($payload, 'username');
        $secret = $this->stringValue($payload, 'secret');
        if (($customerId ?? 0) <= 0 || $extension === '' || $secret === '') {
            return $this->error(422, 'pjsip_validation_failed', 'customer_id, extension, and secret are required.', 'customer_device');
        }

        // Globally unique endpoint_id per tenant — allows every tenant to have extension 100
        $endpointId = $this->endpointId("c{$customerId}", $extension);
        // All tenant devices land in a2billing-tenant; dialplan extracts tenant from endpoint name
        $context = $this->stringValue($payload, 'context', 'a2billing-tenant');
        $allow = $this->stringValue($payload, 'allow', 'ulaw,alaw');
        $accountcode = $this->stringValue($payload, 'accountcode') ?: $this->customerAccountCode($customerId);

        // SIP username = endpointId so Asterisk auth_username lookup is globally unique
        $this->writeEndpoint($endpointId, $endpointId, $secret, $context, $allow, null, 1, 'auth_username,username', $accountcode);
        $this->writeMapping($endpointId, 'customer_device', $customerId, $extension);
        $this->audit($actor, 'pjsip.customer_device.provision', $endpointId, ['customer_id' => $customerId, 'extension' => $extension]);

        return ['status' => 201, 'body' => ['success' => true, 'endpoint' => $this->publicEndpoint($endpointId, 'customer_device', $customerId, $extension)]];
    }

    /**
     * @param array<string,mixed> $account
     * @return array{status:int,body:array<string,mixed>}
     */
    public function syncLegacySipAccount(array $account, string $actor): array
    {
        return $this->provisionCustomerDevice([
            'customer_id' => $account['id_cc_card'] ?? null,
            'extension'   => $account['username'] ?? '',
            'secret'      => $account['secret'] ?? '',
            'context'     => $account['context'] ?? null,
            'allow'       => $account['allow'] ?? 'ulaw,alaw',
            'accountcode' => $account['accountcode'] ?? '',
        ], $actor);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function provisionTrunk(array $payload, string $actor): array
    {
        $trunkCode = strtoupper($this->stringValue($payload, 'trunkcode'));
        $host = $this->stringValue($payload, 'host');
        $username = $this->stringValue($payload, 'username');
        $secret = $this->stringValue($payload, 'secret');
        if ($trunkCode === '' || $host === '') {
            return $this->error(422, 'pjsip_validation_failed', 'trunkcode and host are required.', 'trunk');
        }

        $endpointId = $this->endpointId('trunk', $trunkCode);
        $allow = $this->stringValue($payload, 'allow', 'ulaw,alaw');
        $register = $this->boolValue($payload, 'register', $username !== '' && $secret !== '');
        $this->writeTrunkEndpoint($endpointId, $host, $username, $secret, 'from-pstn', $allow, $register);
        $this->writeMapping($endpointId, 'trunk', 0, $trunkCode);
        $this->audit($actor, 'pjsip.trunk.provision', $endpointId, ['trunkcode' => $trunkCode, 'host' => $host]);

        return ['status' => 201, 'body' => ['success' => true, 'endpoint' => $this->publicEndpoint($endpointId, 'trunk', 0, $trunkCode)]];
    }

    /**
     * @param array<string,mixed> $trunk
     * @return array{status:int,body:array<string,mixed>}
     */
    public function syncLegacyTrunk(array $trunk, string $actor): array
    {
        return $this->provisionTrunk([
            'trunkcode' => $trunk['trunkcode'] ?? '',
            'host' => $trunk['providerip'] ?? '',
            'username' => $trunk['username'] ?? '',
            'secret' => $trunk['secret'] ?? '',
            'allow' => $trunk['allow'] ?? 'ulaw,alaw',
            'register' => $trunk['register'] ?? false,
        ], $actor);
    }

    /**
     * @return array{items:list<array<string,mixed>>,columns:list<string>}
     */
    public function listEndpoints(int $limit, int $offset, string $type = '', ?int $ownerId = null): array
    {
        $where = [];
        $bindings = [];
        if ($type !== '') {
            $where[] = 'm.endpoint_type = :endpoint_type';
            $bindings[':endpoint_type'] = $type;
        }
        if ($ownerId !== null) {
            $where[] = 'm.owner_id = :owner_id';
            $bindings[':owner_id'] = $ownerId;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $statement = $this->pdo->prepare(
            'SELECT
                m.endpoint_id,
                m.endpoint_type,
                m.owner_id,
                m.label,
                e.accountcode,
                e.context,
                e.allow,
                a.max_contacts,
                a.contact,
                m.updated_at
             FROM cc_a2bp_pjsip_endpoint_map m
             INNER JOIN ps_endpoints e ON e.id = m.endpoint_id
             INNER JOIN ps_aors a ON a.id = m.endpoint_id' . $whereSql . '
             ORDER BY m.updated_at DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($bindings as $parameter => $value) {
            $statement->bindValue($parameter, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(\PDO::FETCH_ASSOC),
            'columns' => ['endpoint_id', 'endpoint_type', 'owner_id', 'label', 'accountcode', 'context', 'allow', 'max_contacts', 'contact', 'updated_at'],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function endpointDetail(string $endpointId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                m.endpoint_id,
                m.endpoint_type,
                m.owner_id,
                m.label,
                e.transport,
                e.aors,
                e.auth,
                e.accountcode,
                e.context,
                e.identify_by,
                e.disallow,
                e.allow,
                e.direct_media,
                e.rtp_symmetric,
                e.force_rport,
                e.rewrite_contact,
                a.max_contacts,
                a.remove_existing,
                a.contact,
                m.created_at,
                m.updated_at
             FROM cc_a2bp_pjsip_endpoint_map m
             INNER JOIN ps_endpoints e ON e.id = m.endpoint_id
             INNER JOIN ps_aors a ON a.id = m.endpoint_id
             WHERE m.endpoint_id = :endpoint_id'
        );
        $statement->bindValue(':endpoint_id', $endpointId);
        $statement->execute();

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function updateEndpoint(string $endpointId, array $payload, string $actor): array
    {
        if ($this->endpointDetail($endpointId) === null) {
            return $this->error(404, 'pjsip_endpoint_not_found', 'PJSIP endpoint was not found.', 'endpoint_id');
        }

        $endpointUpdates = [];
        foreach (['context', 'allow', 'identify_by', 'accountcode'] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = $this->stringValue($payload, $field);
                if ($value === '' || strlen($value) > 100) {
                    return $this->error(422, 'pjsip_validation_failed', $field . ' must be 1 to 100 characters.', $field);
                }
                $endpointUpdates[$field] = $value;
            }
        }

        $aorUpdates = [];
        if (array_key_exists('contact', $payload)) {
            $contact = $this->stringValue($payload, 'contact');
            if (strlen($contact) > 255) {
                return $this->error(422, 'pjsip_validation_failed', 'contact must be 255 characters or fewer.', 'contact');
            }
            $aorUpdates['contact'] = $contact;
        }
        if (array_key_exists('max_contacts', $payload)) {
            $maxContacts = $this->intValue($payload, 'max_contacts');
            if (($maxContacts ?? 0) < 0 || ($maxContacts ?? 0) > 20) {
                return $this->error(422, 'pjsip_validation_failed', 'max_contacts must be between 0 and 20.', 'max_contacts');
            }
            $aorUpdates['max_contacts'] = $maxContacts;
        }

        $this->pdo->beginTransaction();
        try {
            $this->updateColumns('ps_endpoints', 'id', $endpointId, $endpointUpdates);
            $this->updateColumns('ps_aors', 'id', $endpointId, $aorUpdates);
            $this->updateColumns('cc_a2bp_pjsip_endpoint_map', 'endpoint_id', $endpointId, ['updated_at' => gmdate('Y-m-d H:i:s')]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $this->audit($actor, 'pjsip.endpoint.update', $endpointId, array_keys($endpointUpdates + $aorUpdates));

        return ['status' => 200, 'body' => ['success' => true, 'endpoint' => $this->endpointDetail($endpointId)]];
    }

    private function writeEndpoint(string $endpointId, string $username, string $secret, string $context, string $allow, ?string $contact, int $maxContacts, string $identifyBy, string $accountcode = ''): void
    {
        $realm = $this->stringValue([
            'realm' => getenv('A2BP_ASTERISK_REALM') ?: 'asterisk',
        ], 'realm', 'asterisk');
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $this->upsert('ps_auths', ['id' => $endpointId . '-auth'], [
                'auth_type' => 'md5',
                'username' => $username,
                'password' => $secret,
                'realm' => $realm,
                'md5_cred' => md5($username . ':' . $realm . ':' . $secret),
            ]);
            $this->upsert('ps_aors', ['id' => $endpointId], [
                'max_contacts' => $maxContacts,
                'remove_existing' => 'yes',
                'contact' => $contact ?? '',
            ]);
            $this->upsert('ps_endpoints', ['id' => $endpointId], [
                'transport' => 'transport-udp',
                'aors' => $endpointId,
                'auth' => $endpointId . '-auth',
                'accountcode' => $accountcode,
                'context' => $context,
                'identify_by' => $identifyBy,
                'disallow' => 'all',
                'allow' => $allow,
                'direct_media' => 'no',
                'rtp_symmetric' => 'yes',
                'force_rport' => 'yes',
                'rewrite_contact' => 'yes',
            ]);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function writeTrunkEndpoint(string $endpointId, string $host, string $username, string $secret, string $context, string $allow, bool $register): void
    {
        $realm = $this->stringValue([
            'realm' => getenv('A2BP_ASTERISK_REALM') ?: 'asterisk',
        ], 'realm', 'asterisk');
        $authId = $username !== '' && $secret !== '' ? $endpointId . '-auth' : '';
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            if ($authId !== '') {
                $this->upsert('ps_auths', ['id' => $authId], [
                    'auth_type' => 'md5',
                    'username' => $username,
                    'password' => $secret,
                    'realm' => $realm,
                    'md5_cred' => md5($username . ':' . $realm . ':' . $secret),
                ]);
            }
            $this->upsert('ps_aors', ['id' => $endpointId], [
                'max_contacts' => 0,
                'remove_existing' => 'yes',
                'contact' => 'sip:' . $host,
            ]);
            $this->upsert('ps_endpoints', ['id' => $endpointId], [
                'transport' => 'transport-udp',
                'aors' => $endpointId,
                'auth' => $authId,
                'context' => $context,
                'identify_by' => 'username,ip',
                'disallow' => 'all',
                'allow' => $allow,
                'direct_media' => 'no',
                'rtp_symmetric' => 'yes',
                'force_rport' => 'yes',
                'rewrite_contact' => 'yes',
            ]);
            $this->upsert('ps_endpoint_id_ips', ['id' => $endpointId], [
                'endpoint' => $endpointId,
                'match' => $this->identifyMatchForHost($host),
                'srv_lookups' => 'yes',
                'match_header' => '',
            ]);
            if ($register && $authId !== '') {
                $this->upsert('ps_registrations', ['id' => $endpointId], [
                    'transport' => 'transport-udp',
                    'outbound_auth' => $authId,
                    'server_uri' => 'sip:' . $host,
                    'client_uri' => 'sip:' . $username . '@' . $host,
                    'contact_user' => $username,
                    'endpoint' => $endpointId,
                    'expiration' => 3600,
                    'retry_interval' => 60,
                    'forbidden_retry_interval' => 600,
                    'fatal_retry_interval' => 600,
                    'max_retries' => 10000,
                    'outbound_proxy' => '',
                    'support_path' => 'no',
                    'line' => 'no',
                    'auth_rejection_permanent' => 'no',
                ]);
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function identifyMatchForHost(string $host): string
    {
        $host = trim($host);
        if (preg_match('/^54\.172\.60\.\d{1,3}$/', $host) === 1) {
            return '54.172.60.0/24';
        }

        return $host;
    }

    /**
     * @param array<string,mixed> $key
     * @param array<string,mixed> $values
     */
    private function upsert(string $table, array $key, array $values): void
    {
        $data = $key + $values;
        $columns = array_keys($data);
        $keyColumn = (string)array_key_first($key);
        $quotedColumns = array_map([$this, 'quoteIdentifier'], $columns);
        $quotedKeyColumn = $this->quoteIdentifier($keyColumn);
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $assignments = array_map(fn (string $column): string => $this->quoteIdentifier($column) . ' = excluded.' . $this->quoteIdentifier($column), array_keys($values));
            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT(%s) DO UPDATE SET %s',
                $table,
                implode(', ', $quotedColumns),
                implode(', ', array_fill(0, count($columns), '?')),
                $quotedKeyColumn,
                implode(', ', $assignments)
            );
        } else {
            $assignments = array_map(fn (string $column): string => $this->quoteIdentifier($column) . ' = VALUES(' . $this->quoteIdentifier($column) . ')', array_keys($values));
            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
                $table,
                implode(', ', $quotedColumns),
                implode(', ', array_fill(0, count($columns), '?')),
                implode(', ', $assignments)
            );
        }
        $this->pdo->prepare($sql)->execute(array_values($data));
    }

    private function writeMapping(string $endpointId, string $type, int $ownerId, string $label): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->upsert('cc_a2bp_pjsip_endpoint_map', ['endpoint_id' => $endpointId], [
            'endpoint_type' => $type,
            'owner_id' => $ownerId,
            'label' => $label,
            'updated_at' => $now,
            'created_at' => $now,
        ]);
    }

    /**
     * @param array<string,mixed> $values
     */
    private function updateColumns(string $table, string $keyColumn, string $keyValue, array $values): void
    {
        if ($values === []) {
            return;
        }

        $assignments = [];
        foreach (array_keys($values) as $column) {
            $assignments[] = $column . ' = :' . $column;
        }

        $statement = $this->pdo->prepare(sprintf(
            'UPDATE %s SET %s WHERE %s = :key_value',
            $table,
            implode(', ', $assignments),
            $keyColumn
        ));
        foreach ($values as $column => $value) {
            $statement->bindValue(':' . $column, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->bindValue(':key_value', $keyValue);
        $statement->execute();
    }

    /**
     * @return array<string,mixed>
     */
    private function publicEndpoint(string $endpointId, string $type, int $ownerId, string $label): array
    {
        return [
            'endpoint_id'  => $endpointId,
            'endpoint_type' => $type,
            'owner_id'     => $ownerId,
            'label'        => $label,
            'sip_username' => $endpointId,
            'auth_id'      => $endpointId . '-auth',
            'aor_id'       => $endpointId,
        ];
    }

    private function ensureTables(): void
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS ps_auths (id TEXT PRIMARY KEY, auth_type TEXT, username TEXT, password TEXT, realm TEXT, md5_cred TEXT, nonce_lifetime INTEGER)');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS ps_aors (id TEXT PRIMARY KEY, max_contacts INTEGER, remove_existing TEXT, contact TEXT)');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS ps_endpoints (id TEXT PRIMARY KEY, transport TEXT, aors TEXT, auth TEXT, accountcode TEXT, context TEXT, identify_by TEXT, disallow TEXT, allow TEXT, direct_media TEXT, rtp_symmetric TEXT, force_rport TEXT, rewrite_contact TEXT)');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS ps_endpoint_id_ips (id TEXT PRIMARY KEY, endpoint TEXT, `match` TEXT, srv_lookups TEXT, match_header TEXT)');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS ps_registrations (id TEXT PRIMARY KEY, transport TEXT, outbound_auth TEXT, server_uri TEXT, client_uri TEXT, contact_user TEXT, endpoint TEXT, expiration INTEGER, retry_interval INTEGER, forbidden_retry_interval INTEGER, fatal_retry_interval INTEGER, max_retries INTEGER, outbound_proxy TEXT, support_path TEXT, line TEXT, auth_rejection_permanent TEXT)');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS cc_a2bp_pjsip_endpoint_map (endpoint_id TEXT PRIMARY KEY, endpoint_type TEXT, owner_id INTEGER, label TEXT, created_at TEXT, updated_at TEXT)');
            $this->ensureColumn('ps_endpoints', 'accountcode', 'TEXT NOT NULL DEFAULT ""');
            return;
        }

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS ps_auths (id VARCHAR(80) NOT NULL, auth_type VARCHAR(20) NOT NULL DEFAULT "userpass", username VARCHAR(80) NOT NULL DEFAULT "", password VARCHAR(120) NOT NULL DEFAULT "", realm VARCHAR(255) DEFAULT NULL, md5_cred VARCHAR(40) DEFAULT NULL, nonce_lifetime INT DEFAULT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS ps_aors (id VARCHAR(80) NOT NULL, max_contacts INT NOT NULL DEFAULT 1, remove_existing VARCHAR(3) NOT NULL DEFAULT "yes", contact VARCHAR(255) NOT NULL DEFAULT "", PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS ps_endpoints (id VARCHAR(80) NOT NULL, transport VARCHAR(80) NOT NULL DEFAULT "transport-udp", aors VARCHAR(80) NOT NULL DEFAULT "", auth VARCHAR(80) NOT NULL DEFAULT "", accountcode VARCHAR(80) NOT NULL DEFAULT "", context VARCHAR(80) NOT NULL DEFAULT "a2billing", identify_by VARCHAR(80) NOT NULL DEFAULT "username,ip", disallow VARCHAR(100) NOT NULL DEFAULT "all", allow VARCHAR(100) NOT NULL DEFAULT "ulaw,alaw", direct_media VARCHAR(3) NOT NULL DEFAULT "no", rtp_symmetric VARCHAR(3) NOT NULL DEFAULT "yes", force_rport VARCHAR(3) NOT NULL DEFAULT "yes", rewrite_contact VARCHAR(3) NOT NULL DEFAULT "yes", PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS ps_endpoint_id_ips (id VARCHAR(80) NOT NULL, endpoint VARCHAR(80) NOT NULL DEFAULT "", `match` VARCHAR(255) NOT NULL DEFAULT "", srv_lookups VARCHAR(3) NOT NULL DEFAULT "yes", match_header VARCHAR(255) NOT NULL DEFAULT "", PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS ps_registrations (id VARCHAR(80) NOT NULL, transport VARCHAR(80) NOT NULL DEFAULT "transport-udp", outbound_auth VARCHAR(80) NOT NULL DEFAULT "", server_uri VARCHAR(255) NOT NULL DEFAULT "", client_uri VARCHAR(255) NOT NULL DEFAULT "", contact_user VARCHAR(80) NOT NULL DEFAULT "", endpoint VARCHAR(80) NOT NULL DEFAULT "", expiration INT NOT NULL DEFAULT 3600, retry_interval INT NOT NULL DEFAULT 60, forbidden_retry_interval INT NOT NULL DEFAULT 600, fatal_retry_interval INT NOT NULL DEFAULT 600, max_retries INT NOT NULL DEFAULT 10000, outbound_proxy VARCHAR(255) NOT NULL DEFAULT "", support_path VARCHAR(3) NOT NULL DEFAULT "no", line VARCHAR(3) NOT NULL DEFAULT "no", auth_rejection_permanent VARCHAR(3) NOT NULL DEFAULT "no", PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS cc_a2bp_pjsip_endpoint_map (endpoint_id VARCHAR(80) NOT NULL, endpoint_type VARCHAR(32) NOT NULL, owner_id BIGINT NOT NULL DEFAULT 0, label VARCHAR(120) NOT NULL DEFAULT "", created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (endpoint_id), KEY idx_a2bp_pjsip_owner (endpoint_type, owner_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->ensureColumn('ps_endpoints', 'accountcode', 'VARCHAR(80) NOT NULL DEFAULT "" AFTER auth');
    }

    private function customerAccountCode(int $customerId): string
    {
        try {
            $statement = $this->pdo->prepare('SELECT username FROM cc_card WHERE id = :id');
            $statement->bindValue(':id', $customerId, \PDO::PARAM_INT);
            $statement->execute();
            $username = $statement->fetchColumn();
        } catch (\Throwable) {
            return '';
        }

        return is_scalar($username) ? substr(trim((string)$username), 0, 80) : '';
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $columns = $this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($columns as $existing) {
                if (($existing['name'] ?? '') === $column) {
                    return;
                }
            }
            $this->pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
            return;
        }

        $statement = $this->pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE :column');
        $statement->bindValue(':column', $column);
        $statement->execute();
        if ($statement->fetch(\PDO::FETCH_ASSOC)) {
            return;
        }
        $this->pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    private function endpointId(string $prefix, string $value): string
    {
        $safe = strtolower(preg_replace('/[^A-Za-z0-9_.-]+/', '-', $value) ?? '');
        $safe = trim($safe, '-');
        return substr($prefix === '' ? $safe : $prefix . '-' . $safe, 0, 80);
    }

    private function audit(string $actor, string $action, string $endpointId, array $metadata): void
    {
        $this->auditLog?->record($actor, $action, 'ps_endpoints', $endpointId, $metadata);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function stringValue(array $payload, string $key, string $default = ''): string
    {
        $value = $payload[$key] ?? $default;
        return is_scalar($value) ? trim((string)$value) : $default;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function intValue(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            return (int)$value;
        }

        return null;
    }

    private function boolValue(array $payload, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $payload)) {
            return $default;
        }
        $value = $payload[$key];
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('Unsafe SQL identifier.');
        }

        return '`' . $identifier . '`';
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    private function error(int $status, string $code, string $message, string $field): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'code' => $code, 'message' => $message, 'field' => $field]];
    }
}
