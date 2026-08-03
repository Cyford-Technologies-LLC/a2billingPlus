<?php

declare(strict_types=1);

/**
 * GET /api/v1/subscriptions.php?customer_id={cc_card.id}
 * GET /api/v1/subscriptions.php?customer_username={cc_card.username}
 *
 * Returns active recurring subscriptions for a customer.
 * Active = cs.stopdate > NOW() (or stopdate is the sentinel 0000-00-00).
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
    echo json_encode(['success' => false, 'error' => ['code' => 'api_auth_not_configured', 'message' => 'API service key authentication is not configured.']]);
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
    echo json_encode(['success' => false, 'error' => ['code' => 'invalid_service_key', 'message' => 'The supplied API service key is not valid.']]);
    exit;
}

// ── GET only ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'This endpoint supports GET only.']]);
    exit;
}

// ── Params ───────────────────────────────────────────────────────────────────
$customerId       = isset($_GET['customer_id'])       ? (int)$_GET['customer_id']             : null;
$customerUsername = isset($_GET['customer_username']) ? trim((string)$_GET['customer_username']) : null;

if ($customerId === null && ($customerUsername === null || $customerUsername === '')) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => ['code' => 'missing_parameter', 'message' => 'Provide customer_id or customer_username.']]);
    exit;
}

// ── DB ────────────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        $config->databaseDsn(),
        $config->string('A2BP_DB_USER', 'a2billinguser'),
        $config->string('A2BP_DB_PASSWORD', 'a2billing'),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (\PDOException $e) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => ['code' => 'db_error', 'message' => 'Database connection failed.']]);
    exit;
}

// ── Query ─────────────────────────────────────────────────────────────────────
try {
    if ($customerId !== null) {
        $sql = "
            SELECT
                cs.id                AS id,
                ss.id                AS service_id,
                ss.label             AS service_label,
                ss.fee               AS fee,
                cs.startdate         AS startdate,
                cs.stopdate          AS stopdate,
                cs.paid_status       AS paid_status,
                cs.last_run          AS last_run,
                cs.next_billing_date AS next_billing_date
            FROM cc_card_subscription cs
            JOIN cc_subscription_service ss ON ss.id = cs.id_subscription_fee
            JOIN cc_card c ON c.id = cs.id_cc_card
            WHERE c.id = :customer_id
              AND (cs.stopdate = '0000-00-00' OR cs.stopdate > NOW())
            ORDER BY cs.startdate DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':customer_id' => $customerId]);
    } else {
        $sql = "
            SELECT
                cs.id                AS id,
                ss.id                AS service_id,
                ss.label             AS service_label,
                ss.fee               AS fee,
                cs.startdate         AS startdate,
                cs.stopdate          AS stopdate,
                cs.paid_status       AS paid_status,
                cs.last_run          AS last_run,
                cs.next_billing_date AS next_billing_date
            FROM cc_card_subscription cs
            JOIN cc_subscription_service ss ON ss.id = cs.id_subscription_fee
            JOIN cc_card c ON c.id = cs.id_cc_card
            WHERE c.username = :username
              AND (cs.stopdate = '0000-00-00' OR cs.stopdate > NOW())
            ORDER BY cs.startdate DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $customerUsername]);
    }

    $subscriptions = $stmt->fetchAll();
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => ['code' => 'query_error', 'message' => 'Query failed.']]);
    exit;
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'data'    => [
        'subscriptions' => $subscriptions,
        'count'         => count($subscriptions),
    ],
]);
