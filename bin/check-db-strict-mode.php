#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use A2BillingPlus\Config\AppConfig;

$config = AppConfig::fromEnvironment();
$pdo = new PDO(
    $config->databaseDsn(),
    $config->string('A2BP_DB_USER', 'a2billinguser'),
    $config->string('A2BP_DB_PASSWORD', 'a2billing'),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$expectedPjsip = [
    'ps_auths' => ['id', 'auth_type', 'username', 'password', 'realm', 'md5_cred', 'nonce_lifetime'],
    'ps_aors' => ['id', 'max_contacts', 'remove_existing', 'contact', 'qualify_frequency', 'authenticate_qualify', 'default_expiration', 'maximum_expiration', 'minimum_expiration'],
    'ps_endpoints' => ['id', 'transport', 'aors', 'auth', 'accountcode', 'context', 'identify_by', 'disallow', 'allow', 'callerid', 'dtmf_mode', 'language', 'mailboxes', 'moh_suggest', 'from_user', 'from_domain', 'outbound_proxy', 'rtp_keepalive', 'rtp_timeout', 'rtp_timeout_hold', 'send_rpid', 'trust_id_inbound', 'trust_id_outbound', 'allow_transfer', 'set_var', 'direct_media', 'rtp_symmetric', 'force_rport', 'rewrite_contact'],
];

$zeroDateDefaults = $pdo->query(
    "SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, COLUMN_TYPE AS column_type, COLUMN_DEFAULT AS column_default
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND DATA_TYPE IN ('timestamp', 'datetime')
       AND COLUMN_DEFAULT LIKE '0000-00-00%'
     ORDER BY TABLE_NAME, ORDINAL_POSITION"
)->fetchAll();

$notNullNoDefault = $pdo->query(
    "SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, COLUMN_TYPE AS column_type, COLUMN_KEY AS column_key, EXTRA AS extra
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND IS_NULLABLE = 'NO'
       AND COLUMN_DEFAULT IS NULL
       AND EXTRA NOT LIKE '%auto_increment%'
     ORDER BY TABLE_NAME, ORDINAL_POSITION"
)->fetchAll();

$missingPjsipColumns = [];
foreach ($expectedPjsip as $table => $columns) {
    if (!tableExists($pdo, $table)) {
        $missingPjsipColumns[$table] = $columns;
        continue;
    }

    $existing = existingColumns($pdo, $table);
    $missing = array_values(array_diff($columns, $existing));
    if ($missing !== []) {
        $missingPjsipColumns[$table] = $missing;
    }
}

echo json_encode([
    'success' => true,
    'database' => (string)$pdo->query('SELECT DATABASE()')->fetchColumn(),
    'sql_mode' => (string)$pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn(),
    'zero_date_defaults' => [
        'count' => count($zeroDateDefaults),
        'items' => $zeroDateDefaults,
    ],
    'not_null_without_default' => [
        'count' => count($notNullNoDefault),
        'items' => $notNullNoDefault,
    ],
    'pjsip_missing_columns' => $missingPjsipColumns,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

function tableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute([$table]);
    return (int)$statement->fetchColumn() > 0;
}

/**
 * @return list<string>
 */
function existingColumns(PDO $pdo, string $table): array
{
    $statement = $pdo->prepare(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute([$table]);
    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
}
