<?php

declare(strict_types=1);

/**
 * Customer Group (cc_card_group) management.
 *
 * The `provisioning` column on cc_card_group is repurposed as a lookup key
 * for CRM tenants: value = crm_{org_id}. This lets the ZeroAI CRM find or
 * create the group for a given tenant without scanning every group.
 *
 * GET  ?external_id={crm_{org_id}}  — find group by CRM provisioning key
 * GET  ?id={id}                     — get group + member card IDs
 * POST {name, description, external_id} — create group (provisioning=external_id)
 *
 * Auth: Bearer token (A2BP_API_SERVICE_KEY) — same as all other endpoints.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use A2BillingPlus\Config\AppConfig;

header('Content-Type: application/json; charset=utf-8');

// ── Auth ──────────────────────────────────────────────────────────────────────
$config = AppConfig::fromEnvironment();
$expectedKey = $config->string('A2BP_API_SERVICE_KEY');
if ($expectedKey === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => ['code' => 'api_auth_not_configured', 'message' => 'API service key not configured.']]);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $hName => $hVal) {
        if (strtolower($hName) === 'authorization') {
            $authHeader = $hVal;
            break;
        }
    }
}
if (!str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => ['code' => 'missing_authorization', 'message' => 'Bearer token required.']]);
    exit;
}
$providedKey = trim(substr($authHeader, 7));
if ($providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => ['code' => 'invalid_service_key', 'message' => 'Invalid API service key.']]);
    exit;
}

// ── DB ────────────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        $config->databaseDsn(),
        $config->string('A2BP_DB_USER', 'a2billinguser'),
        $config->string('A2BP_DB_PASSWORD', 'a2billing'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (\PDOException $e) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => ['code' => 'db_error', 'message' => 'Database connection failed.']]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ?external_id — find group by CRM provisioning key ─────────────────────
if ($method === 'GET' && isset($_GET['external_id'])) {
    $externalId = trim((string)$_GET['external_id']);
    if ($externalId === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => ['code' => 'missing_parameter', 'message' => 'Provide external_id.']]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, description, users_perms, id_agent, provisioning
         FROM cc_card_group
         WHERE provisioning = :ext LIMIT 1'
    );
    $stmt->execute([':ext' => $externalId]);
    $group = $stmt->fetch();
    if (!$group) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'group_not_found', 'message' => 'No group with that external_id.']]);
        exit;
    }

    // Fetch member card IDs
    $cards = $pdo->prepare('SELECT id, username, external_id, status FROM cc_card WHERE id_group = :gid ORDER BY id ASC');
    $cards->execute([':gid' => $group['id']]);

    echo json_encode([
        'success' => true,
        'data' => [
            'group' => $group,
            'cards' => $cards->fetchAll(),
        ],
    ]);
    exit;
}

// ── GET ?id — get group + member cards ────────────────────────────────────────
if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => ['code' => 'invalid_id', 'message' => 'id must be a positive integer.']]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, description, users_perms, id_agent, provisioning
         FROM cc_card_group WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $group = $stmt->fetch();
    if (!$group) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'group_not_found', 'message' => 'Group not found.']]);
        exit;
    }

    $cards = $pdo->prepare('SELECT id, username, external_id, status FROM cc_card WHERE id_group = :gid ORDER BY id ASC');
    $cards->execute([':gid' => $id]);

    echo json_encode([
        'success' => true,
        'data' => [
            'group' => $group,
            'cards' => $cards->fetchAll(),
        ],
    ]);
    exit;
}

// ── POST — create a customer group ────────────────────────────────────────────
if ($method === 'POST') {
    try {
    $body        = json_decode((string)file_get_contents('php://input'), true) ?? [];
    $name        = trim((string)($body['name']        ?? ''));
    $description = trim((string)($body['description'] ?? ''));
    $externalId  = trim((string)($body['external_id'] ?? ''));

    if ($name === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => ['code' => 'missing_parameter', 'message' => 'name is required.']]);
        exit;
    }
    if ($externalId === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => ['code' => 'missing_parameter', 'message' => 'external_id is required.']]);
        exit;
    }
    if (strlen($externalId) > 128) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => ['code' => 'invalid_external_id', 'message' => 'external_id must be 128 characters or fewer.']]);
        exit;
    }

    // Check for duplicate provisioning key
    $exists = $pdo->prepare('SELECT id FROM cc_card_group WHERE provisioning = :ext LIMIT 1');
    $exists->execute([':ext' => $externalId]);
    $existing = $exists->fetch();
    if ($existing) {
        // Return existing group rather than failing — idempotent
        $stmt = $pdo->prepare(
            'SELECT id, name, description, users_perms, id_agent, provisioning
             FROM cc_card_group WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $existing['id']]);
        $group = $stmt->fetch();
        echo json_encode([
            'success' => true,
            'data'    => ['group' => $group],
            'meta'    => ['action' => 'existing', 'resource' => 'customer-groups'],
        ]);
        exit;
    }

    $insert = $pdo->prepare(
        'INSERT INTO cc_card_group (name, description, users_perms, id_agent, provisioning)
         VALUES (:name, :desc, :perms, :agent, :prov)'
    );
    $insert->execute([
        ':name'  => $name,
        ':desc'  => $description,
        ':perms' => 0,
        ':agent' => 0,
        ':prov'  => $externalId,
    ]);
    $newId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'SELECT id, name, description, users_perms, id_agent, provisioning
         FROM cc_card_group WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $newId]);
    $group = $stmt->fetch();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'data'    => ['group' => $group],
        'meta'    => ['action' => 'create', 'resource' => 'customer-groups'],
    ]);
    exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => ['code' => 'group_create_failed', 'message' => $e->getMessage()]]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Supported: GET, POST.']]);
