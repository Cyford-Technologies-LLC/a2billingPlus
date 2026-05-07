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

fwrite(STDOUT, "[sandbox-bootstrap] Ready. tariff_plan_id={$tariffPlanId} tariff_group_id={$tariffGroupId}\n");
