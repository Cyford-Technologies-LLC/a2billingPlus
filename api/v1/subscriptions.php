<?php

declare(strict_types=1);

/**
 * Per-DID subscription management.
 *
 * Each DID can have its own service plan subscription (1:1).
 * Pay As You Go = no subscription row for that DID.
 * A customer can have multiple subscriptions, one per DID.
 *
 * GET  ?customer_id={id}          — list all subscriptions for customer
 * GET  ?customer_id={id}&did={n}  — get subscription for a specific DID
 * GET  ?services=1                — list available cc_subscription_service options
 * POST {customer_id, service_id, did} — subscribe DID to plan (cancels existing for that DID)
 * DELETE ?did={n}&customer_id={id}    — cancel DID subscription (revert to PAYG)
 * DELETE ?id={sub_id}&customer_id={id} — cancel by subscription ID
 *
 * Auth: Bearer token (A2BP_API_SERVICE_KEY).
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
        if (strtolower($hName) === 'authorization') { $authHeader = $hVal; break; }
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

// ── GET ?services=1 — available service plans ─────────────────────────────────
if ($method === 'GET' && isset($_GET['services'])) {
    $stmt = $pdo->query("
        SELECT id, label, fee, status
        FROM cc_subscription_service
        WHERE status = 1
          AND (stopdate = '0000-00-00' OR stopdate >= NOW() OR stopdate >= '2038-01-01')
        ORDER BY fee ASC
    ");
    $services = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => ['services' => $services, 'count' => count($services)]]);
    exit;
}

// ── GET ?customer_id — list subscriptions (optionally filtered by DID) ────────
if ($method === 'GET') {
    $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
    $filterDid  = isset($_GET['did'])         ? trim((string)$_GET['did']) : null;

    if ($customerId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => ['code' => 'missing_parameter', 'message' => 'Provide customer_id.']]);
        exit;
    }

    $whereDid  = $filterDid !== null ? 'AND cs.product_id = ?' : '';
    $params    = $filterDid !== null ? [$customerId, $filterDid] : [$customerId];

    $stmt = $pdo->prepare("
        SELECT
            cs.id                AS id,
            ss.id                AS service_id,
            ss.label             AS service_label,
            ss.fee               AS fee,
            cs.product_id        AS did,
            cs.startdate, cs.stopdate,
            cs.paid_status, cs.last_run, cs.next_billing_date
        FROM cc_card_subscription cs
        JOIN cc_subscription_service ss ON ss.id = cs.id_subscription_fee
        WHERE cs.id_cc_card = ?
          $whereDid
          AND (cs.stopdate = '0000-00-00' OR cs.stopdate >= NOW() OR cs.stopdate >= '2038-01-01')
        ORDER BY cs.product_id ASC, cs.startdate DESC
    ");
    $stmt->execute($params);
    $subscriptions = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => ['subscriptions' => $subscriptions, 'count' => count($subscriptions)]]);
    exit;
}

// ── POST — subscribe a DID to a service plan ──────────────────────────────────
if ($method === 'POST') {
    $body       = json_decode((string)file_get_contents('php://input'), true) ?? [];
    $customerId = isset($body['customer_id']) ? (int)$body['customer_id']           : 0;
    $serviceId  = isset($body['service_id'])  ? (int)$body['service_id']            : 0;
    $did        = isset($body['did'])         ? trim((string)$body['did'])          : '';

    if ($customerId <= 0 || $serviceId <= 0 || $did === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => ['code' => 'missing_parameter', 'message' => 'Provide customer_id, service_id, and did.']]);
        exit;
    }

    // Verify customer exists
    $c = $pdo->prepare("SELECT id FROM cc_card WHERE id = ? LIMIT 1");
    $c->execute([$customerId]);
    if (!$c->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'customer_not_found', 'message' => 'Customer not found.']]);
        exit;
    }

    // Verify service plan exists and is active
    $s = $pdo->prepare("SELECT id, label, fee FROM cc_subscription_service WHERE id = ? AND status = 1 LIMIT 1");
    $s->execute([$serviceId]);
    $service = $s->fetch();
    if (!$service) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'service_not_found', 'message' => 'Service plan not found or inactive.']]);
        exit;
    }

    // Cancel any existing active subscription for this DID (1 plan per DID)
    $pdo->prepare("
        UPDATE cc_card_subscription
        SET stopdate = NOW()
        WHERE id_cc_card = ? AND product_id = ?
          AND (stopdate = '0000-00-00' OR stopdate >= NOW() OR stopdate >= '2038-01-01')
    ")->execute([$customerId, $did]);

    // Create new subscription for this DID
    $pdo->prepare("
        INSERT INTO cc_card_subscription
          (id_cc_card, id_subscription_fee, startdate, stopdate, product_id, product_name, paid_status, last_run, next_billing_date)
        VALUES
          (?, ?, NOW(), '2038-01-01 00:00:00', ?, ?, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00')
    ")->execute([$customerId, $serviceId, $did, $did]);

    $newId = (int)$pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'data'    => ['subscription_id' => $newId, 'did' => $did, 'service_id' => $serviceId, 'service_label' => $service['label'], 'fee' => $service['fee']],
    ]);
    exit;
}

// ── DELETE — cancel DID subscription (revert to PAYG) ────────────────────────
if ($method === 'DELETE') {
    $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
    $did        = isset($_GET['did'])         ? trim((string)$_GET['did']) : '';
    $subId      = isset($_GET['id'])          ? (int)$_GET['id']          : 0;

    if ($customerId <= 0 || ($did === '' && $subId <= 0)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => ['code' => 'missing_parameter', 'message' => 'Provide customer_id and did (or id).']]);
        exit;
    }

    if ($did !== '') {
        $stmt = $pdo->prepare("
            UPDATE cc_card_subscription SET stopdate = NOW()
            WHERE id_cc_card = ? AND product_id = ?
              AND (stopdate = '0000-00-00' OR stopdate >= NOW() OR stopdate >= '2038-01-01')
        ");
        $stmt->execute([$customerId, $did]);
    } else {
        $stmt = $pdo->prepare("UPDATE cc_card_subscription SET stopdate = NOW() WHERE id = ? AND id_cc_card = ?");
        $stmt->execute([$subId, $customerId]);
    }

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'not_found', 'message' => 'No active subscription found for this DID.']]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => ['did' => $did ?: null, 'cancelled_id' => $subId ?: null]]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Supported: GET, POST, DELETE.']]);
