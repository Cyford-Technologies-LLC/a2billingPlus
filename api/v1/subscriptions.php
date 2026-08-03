<?php

declare(strict_types=1);

/**
 * Subscription management endpoint.
 *
 * GET    ?customer_id={id}  — list active subscriptions for a customer
 * POST                       — subscribe a customer to a service plan
 *                              body: { customer_id, service_id }
 * DELETE ?id={sub_id}       — cancel a subscription (set stopdate = NOW)
 *
 * Also:
 * GET ?services=1           — list available cc_subscription_service options
 *
 * Auth: Bearer token (A2BP_API_SERVICE_KEY), same as all other endpoints.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use A2BillingPlus\Config\AppConfig;

header('Content-Type: application/json; charset=utf-8');

// ── Auth ──────────────────────────────────────────────────────────────────────
$config = AppConfig::fromEnvironment();

$expectedKey = $config->string('A2BP_API_SERVICE_KEY');
if ($expectedKey === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => ['code' => 'api_auth_not_configured', 'message' => 'API service key is not configured.']]);
    exit;
}

// Apache mod_php strips Authorization from $_SERVER — fall back to getallheaders()
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $hName => $hVal) {
        if (strtolower($hName) === 'authorization') { $authHeader = $hVal; break; }
    }
}
if (!str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => ['code' => 'missing_authorization', 'message' => 'Authorization header must use Bearer service key authentication.']]);
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

// ── GET ?services=1 — list available subscription services ───────────────────
if ($method === 'GET' && isset($_GET['services'])) {
    try {
        $stmt = $pdo->query("
            SELECT id, label, fee, status
            FROM cc_subscription_service
            WHERE status = 1
              AND (stopdate = '0000-00-00' OR stopdate > NOW() OR stopdate = '2038-01-01 00:00:00')
            ORDER BY fee ASC
        ");
        $services = $stmt->fetchAll();
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => ['code' => 'query_error', 'message' => 'Query failed.']]);
        exit;
    }
    echo json_encode(['success' => true, 'data' => ['services' => $services, 'count' => count($services)]]);
    exit;
}

// ── GET ?customer_id={id} — list active subscriptions ───────────────────────
if ($method === 'GET') {
    $customerId       = isset($_GET['customer_id'])       ? (int)$_GET['customer_id']               : null;
    $customerUsername = isset($_GET['customer_username']) ? trim((string)$_GET['customer_username']) : null;

    if ($customerId === null && ($customerUsername === null || $customerUsername === '')) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => ['code' => 'missing_parameter', 'message' => 'Provide customer_id or customer_username.']]);
        exit;
    }

    try {
        if ($customerId !== null) {
            $stmt = $pdo->prepare("
                SELECT cs.id, ss.id AS service_id, ss.label AS service_label, ss.fee,
                       cs.startdate, cs.stopdate, cs.paid_status, cs.last_run, cs.next_billing_date
                FROM cc_card_subscription cs
                JOIN cc_subscription_service ss ON ss.id = cs.id_subscription_fee
                JOIN cc_card c ON c.id = cs.id_cc_card
                WHERE c.id = ?
                  AND (cs.stopdate = '0000-00-00' OR cs.stopdate > NOW() OR cs.stopdate = '2038-01-01 00:00:00')
                ORDER BY cs.startdate DESC
            ");
            $stmt->execute([$customerId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT cs.id, ss.id AS service_id, ss.label AS service_label, ss.fee,
                       cs.startdate, cs.stopdate, cs.paid_status, cs.last_run, cs.next_billing_date
                FROM cc_card_subscription cs
                JOIN cc_subscription_service ss ON ss.id = cs.id_subscription_fee
                JOIN cc_card c ON c.id = cs.id_cc_card
                WHERE c.username = ?
                  AND (cs.stopdate = '0000-00-00' OR cs.stopdate > NOW() OR cs.stopdate = '2038-01-01 00:00:00')
                ORDER BY cs.startdate DESC
            ");
            $stmt->execute([$customerUsername]);
        }
        $subscriptions = $stmt->fetchAll();
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => ['code' => 'query_error', 'message' => 'Query failed.']]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => ['subscriptions' => $subscriptions, 'count' => count($subscriptions)]]);
    exit;
}

// ── POST — subscribe a customer to a service plan ────────────────────────────
if ($method === 'POST') {
    $body = json_decode((string)file_get_contents('php://input'), true) ?? [];
    $customerId = isset($body['customer_id']) ? (int)$body['customer_id'] : 0;
    $serviceId  = isset($body['service_id'])  ? (int)$body['service_id']  : 0;

    if ($customerId <= 0 || $serviceId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => ['code' => 'missing_parameter', 'message' => 'Provide customer_id and service_id.']]);
        exit;
    }

    try {
        // Verify customer exists
        $cStmt = $pdo->prepare("SELECT id FROM cc_card WHERE id = ? LIMIT 1");
        $cStmt->execute([$customerId]);
        if (!$cStmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => ['code' => 'customer_not_found', 'message' => 'Customer not found.']]);
            exit;
        }

        // Verify service exists and is active
        $sStmt = $pdo->prepare("SELECT id, label, fee FROM cc_subscription_service WHERE id = ? AND status = 1 LIMIT 1");
        $sStmt->execute([$serviceId]);
        $service = $sStmt->fetch();
        if (!$service) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => ['code' => 'service_not_found', 'message' => 'Subscription service not found or inactive.']]);
            exit;
        }

        // Cancel any existing active subscriptions for this customer first
        $pdo->prepare("
            UPDATE cc_card_subscription
            SET stopdate = NOW()
            WHERE id_cc_card = ?
              AND (stopdate = '0000-00-00' OR stopdate > NOW() OR stopdate = '2038-01-01 00:00:00')
        ")->execute([$customerId]);

        // Create the new subscription
        $pdo->prepare("
            INSERT INTO cc_card_subscription
              (id_cc_card, id_subscription_fee, startdate, stopdate, paid_status, last_run, next_billing_date)
            VALUES
              (?, ?, NOW(), '2038-01-01 00:00:00', 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00')
        ")->execute([$customerId, $serviceId]);

        $newId = (int)$pdo->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data' => [
                'subscription_id' => $newId,
                'service_id'      => $serviceId,
                'service_label'   => $service['label'],
                'fee'             => $service['fee'],
            ],
        ]);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => ['code' => 'insert_error', 'message' => 'Failed to create subscription.']]);
    }
    exit;
}

// ── DELETE ?id={sub_id} — cancel a subscription ──────────────────────────────
if ($method === 'DELETE') {
    $subId      = isset($_GET['id'])          ? (int)$_GET['id']          : 0;
    $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

    if ($subId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => ['code' => 'missing_parameter', 'message' => 'Provide subscription id.']]);
        exit;
    }

    try {
        $where = $customerId > 0
            ? "WHERE id = ? AND id_cc_card = ?"
            : "WHERE id = ?";
        $params = $customerId > 0 ? [$subId, $customerId] : [$subId];

        $stmt = $pdo->prepare("UPDATE cc_card_subscription SET stopdate = NOW() $where");
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => ['code' => 'not_found', 'message' => 'Subscription not found.']]);
            exit;
        }

        echo json_encode(['success' => true, 'data' => ['cancelled_id' => $subId]]);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => ['code' => 'update_error', 'message' => 'Failed to cancel subscription.']]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Supported: GET, POST, DELETE.']]);
