<?php

declare(strict_types=1);

$dsn = getenv('A2BP_DB_DSN');
$user = getenv('A2BP_DB_USER') ?: 'a2billinguser';
$password = getenv('A2BP_DB_PASSWORD') ?: 'a2billing';

if (!$dsn) {
    fwrite(STDERR, "A2BP_DB_DSN is not set.\n");
    exit(1);
}

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $exception) {
    fwrite(STDERR, '[sandbox-bootstrap] DB connection failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

$tariffPlanId = (int) ($pdo->query('SELECT id FROM cc_tariffplan ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
$trunkId = (int) ($pdo->query('SELECT id_trunk FROM cc_trunk ORDER BY id_trunk ASC LIMIT 1')->fetchColumn() ?: 0);

if ($tariffPlanId < 1) {
    $insertTariffPlan = $pdo->prepare(
        'INSERT INTO cc_tariffplan
            (iduser, tariffname, description, id_trunk, dnidprefix, calleridprefix)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insertTariffPlan->execute([
        0,
        'Sandbox Default',
        'Default sandbox tariff plan for local customer creation tests.',
        $trunkId > 0 ? $trunkId : 0,
        'all',
        'all',
    ]);
    $tariffPlanId = (int) $pdo->lastInsertId();
    fwrite(STDOUT, "[sandbox-bootstrap] Created tariff plan #{$tariffPlanId}.\n");
}

$tariffGroupId = (int) ($pdo->query('SELECT id FROM cc_tariffgroup ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
if ($tariffGroupId < 1) {
    $tariffPlanNameStatement = $pdo->prepare('SELECT tariffname FROM cc_tariffplan WHERE id = ?');
    $tariffPlanNameStatement->execute([$tariffPlanId]);
    $tariffPlanNameValue = (string) ($tariffPlanNameStatement->fetchColumn() ?: 'Sandbox Default');

    $insertTariffGroup = $pdo->prepare(
        'INSERT INTO cc_tariffgroup
            (iduser, idtariffplan, tariffgroupname, lcrtype, removeinterprefix, id_cc_package_offer)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insertTariffGroup->execute([
        0,
        $tariffPlanId,
        $tariffPlanNameValue,
        0,
        0,
        -1,
    ]);
    $tariffGroupId = (int) $pdo->lastInsertId();
    fwrite(STDOUT, "[sandbox-bootstrap] Created call plan #{$tariffGroupId}.\n");
}

$mappingStatement = $pdo->prepare('SELECT COUNT(*) FROM cc_tariffgroup_plan WHERE idtariffgroup = ? AND idtariffplan = ?');
$mappingStatement->execute([$tariffGroupId, $tariffPlanId]);
if ((int) $mappingStatement->fetchColumn() < 1) {
    $insertMapping = $pdo->prepare('INSERT INTO cc_tariffgroup_plan (idtariffgroup, idtariffplan) VALUES (?, ?)');
    $insertMapping->execute([$tariffGroupId, $tariffPlanId]);
    fwrite(STDOUT, "[sandbox-bootstrap] Linked call plan #{$tariffGroupId} to tariff plan #{$tariffPlanId}.\n");
}

$defaultGroupStatement = $pdo->query('SELECT COUNT(*) FROM cc_card_group WHERE id = 1');
if ((int) ($defaultGroupStatement->fetchColumn() ?: 0) < 1) {
    $insertCardGroup = $pdo->prepare(
        'INSERT INTO cc_card_group (id, name, description, users_perms) VALUES (?, ?, ?, ?)'
    );
    $insertCardGroup->execute([1, 'Default', 'Default sandbox group', 524287]);
    fwrite(STDOUT, "[sandbox-bootstrap] Created default customer group.\n");
}

/**
 * Ensure legacy Asterisk manager settings point at the local Docker PBX.
 * The admin VoIP tooling reads these values from cc_config, not a2billing.conf.
 */
$configDefaults = [
    'manager_host' => [
        'title' => 'Manager Host',
        'value' => 'asterisk',
        'description' => 'Manager Host Address',
    ],
    'manager_username' => [
        'title' => 'Manager User ID',
        'value' => getenv('ASTERISK_AMI_USER') ?: 'a2billing',
        'description' => 'Manger Host User Name',
    ],
    'manager_secret' => [
        'title' => 'Manager Password',
        'value' => getenv('ASTERISK_AMI_PASSWORD') ?: 'a2billing-ami',
        'description' => 'Manager Host Password',
    ],
];

$selectConfigStatement = $pdo->prepare('SELECT id FROM cc_config WHERE config_key = ? ORDER BY id ASC LIMIT 1');
$insertConfigStatement = $pdo->prepare(
    'INSERT INTO cc_config
        (config_title, config_key, config_value, config_description, config_valuetype, config_listvalues, config_group_title)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$updateConfigStatement = $pdo->prepare('UPDATE cc_config SET config_value = ? WHERE id = ?');

foreach ($configDefaults as $configKey => $configMeta) {
    $selectConfigStatement->execute([$configKey]);
    $configId = (int) ($selectConfigStatement->fetchColumn() ?: 0);

    if ($configId > 0) {
        $updateConfigStatement->execute([$configMeta['value'], $configId]);
        continue;
    }

    $insertConfigStatement->execute([
        $configMeta['title'],
        $configKey,
        $configMeta['value'],
        $configMeta['description'],
        0,
        null,
        'global',
    ]);
    fwrite(STDOUT, "[sandbox-bootstrap] Created config {$configKey}.\n");
}

fwrite(STDOUT, "[sandbox-bootstrap] Ready. tariff_plan_id={$tariffPlanId} tariff_group_id={$tariffGroupId}\n");
