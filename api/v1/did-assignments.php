<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Module\Security\AuditLogRepository;
use A2BillingPlus\Module\Telephony\DidAssignmentService;
use A2BillingPlus\Module\Telephony\DidRepository;

require_once __DIR__ . '/../../vendor/autoload.php';

$config = AppConfig::fromEnvironment();
$request = JsonRequest::fromGlobals();

$authError = (new ApiServiceKeyAuthenticator($config))->authenticate($request);
if ($authError !== null) {
    $authError->send();
    exit;
}

$pdo = new PDO(
    $config->databaseDsn(),
    $config->string('A2BP_DB_USER', 'a2billinguser'),
    $config->string('A2BP_DB_PASSWORD', 'a2billing'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$didRepo = new DidRepository($pdo);
$assignmentService = new DidAssignmentService($pdo, new AuditLogRepository($pdo));
$method = $request->getMethod();
$actor = $request->getHeader('X-A2BP-Actor') ?: 'service-key';

if ($method === 'GET') {
    $limit = $request->getInt('limit', 50);
    $offset = $request->getInt('offset', 0);

    if ($limit < 1 || $limit > 100) {
        ApiResponder::error('invalid_limit', 'Limit must be between 1 and 100.', 422, ['field' => 'limit'])->send();
        exit;
    }

    $customerIdValue = $request->getString('customer_id');
    if ($customerIdValue !== '') {
        if (preg_match('/^[1-9][0-9]*$/', $customerIdValue) !== 1) {
            ApiResponder::error('invalid_customer_id', 'customer_id must be a positive integer.', 422, ['field' => 'customer_id'])->send();
            exit;
        }
        $customerId = (int)$customerIdValue;
        $result = $didRepo->listAssignedToCustomer($customerId, $limit, $offset);

        ApiResponder::ok(
            ['did_assignments' => $result['items']],
            ['resource' => 'did-assignments', 'customer_id' => $customerId, 'limit' => $limit, 'offset' => $offset, 'total' => $result['total']]
        )->send();
        exit;
    }

    $country = trim($request->getString('country'));
    $region = trim($request->getString('region'));
    $result = $didRepo->listAvailable($limit, $offset, $country, $region);

    ApiResponder::ok(
        ['available_dids' => $result['items']],
        ['resource' => 'did-assignments', 'mode' => 'available', 'limit' => $limit, 'offset' => $offset, 'total' => $result['total']]
    )->send();
    exit;
}

if ($method === 'POST') {
    $payload = $request->getArray('assignment');

    $customerIdValue = trim((string)($payload['customer_id'] ?? ''));
    if ($customerIdValue === '' || preg_match('/^[1-9][0-9]*$/', $customerIdValue) !== 1) {
        ApiResponder::error('invalid_customer_id', 'assignment.customer_id must be a positive integer.', 422, ['field' => 'customer_id'])->send();
        exit;
    }

    $did = trim((string)($payload['did'] ?? ''));
    if ($did === '') {
        ApiResponder::error('missing_did', 'assignment.did is required.', 422, ['field' => 'did'])->send();
        exit;
    }

    $customerId  = (int)$customerIdValue;
    $smsEnabled  = ($payload['sms_enabled']   ?? true) !== false;
    $voiceEnabled = ($payload['voice_enabled'] ?? true) !== false;
    // Default webhook URL to CRM inbound handler if not provided
    $crmWebhook  = (string)(getenv('CRM_SMS_WEBHOOK_URL') ?: 'https://zeroaiboss.com/api/integrations/phone-text/sms_inbound.php');
    $webhookUrl  = trim((string)($payload['webhook_url'] ?? '')) ?: $crmWebhook;

    // ── Auto-provision DID into inventory for Telnyx ─────────────────────────
    // If the DID isn't in cc_vectavoip_did_inventory yet and Telnyx is active,
    // purchase it from Telnyx first then seed the inventory row so the
    // DidAssignmentService can find it.
    if ($didRepo->findByNumber($did) === null) {
        $smsProvider = $config->string('SMS_PROVIDER');
        $telnyxKey   = $config->string('TELNYX_API_KEY');
        $telnyxBase  = $config->string('TELNYX_API_BASE_URL', 'https://api.telnyx.com');

        if ($smsProvider === '' && $telnyxKey !== '') { $smsProvider = 'telnyx'; }

        if ($smsProvider === 'telnyx' && $telnyxKey !== '') {
            // Purchase the number from Telnyx
            $purchaseUrl = rtrim($telnyxBase, '/') . '/v2/phone_numbers';
            $ch = curl_init($purchaseUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $telnyxKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'phone_numbers' => [['phone_number' => $did]],
                ]),
            ]);
            $raw      = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $curlErr !== '') {
                ApiResponder::error('telnyx_purchase_failed', 'Could not purchase number: ' . $curlErr, 502)->send();
                exit;
            }

            $purchaseBody = json_decode((string)$raw, true);
            if ($httpCode >= 400) {
                $rawMsg = $purchaseBody['errors'][0]['detail']
                    ?? $purchaseBody['errors'][0]['title']
                    ?? 'Telnyx purchase error';
                // Pretrial / permission errors — surface a helpful message
                $isPretrial = $httpCode === 404
                    || stripos($rawMsg, 'not found') !== false
                    || stripos($rawMsg, 'not permitted') !== false
                    || stripos($rawMsg, 'upgrade') !== false;
                $msg = $isPretrial
                    ? 'Your Telnyx account must be upgraded past Pretrial before purchasing numbers. Add a payment method at telnyx.com/upgrade.'
                    : $rawMsg;
                ApiResponder::error('telnyx_purchase_failed', $msg, 502)->send();
                exit;
            }

            // Telnyx returns either data[] array (bulk) or data{} object
            $purchasedNumbers = $purchaseBody['data'] ?? [];
            if (isset($purchasedNumbers['id'])) { $purchasedNumbers = [$purchasedNumbers]; }
            $providerRef = $purchasedNumbers[0]['id'] ?? $did;

            // Seed inventory row so DidAssignmentService can proceed
            $now = gmdate('Y-m-d H:i:s');
            $pdo->prepare(
                "INSERT INTO cc_vectavoip_did_inventory
                    (did, country, region, monthly_rate, setup_rate, currency, status, provider_reference, created_at, updated_at)
                 VALUES (?, 'US', '', '1.00', '1.00', 'USD', 'available', ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status='available', provider_reference=VALUES(provider_reference), updated_at=VALUES(updated_at)"
            )->execute([$did, $providerRef, $now, $now]);
        } else {
            ApiResponder::error('assignment_failed', 'DID not found in inventory.', 422)->send();
            exit;
        }
    }

    $result = $assignmentService->assign($customerId, $did, $smsEnabled, $voiceEnabled, $actor, $webhookUrl);

    if (!$result['success']) {
        ApiResponder::error('assignment_failed', $result['message'], 422)->send();
        exit;
    }

    ApiResponder::ok(['assignment' => $result['assignment']], ['resource' => 'did-assignments', 'action' => 'assign'], 201)->send();
    exit;
}

if ($method === 'DELETE') {
    $customerIdValue = $request->getString('customer_id');
    $did = trim($request->getString('did'));

    if ($customerIdValue === '' || preg_match('/^[1-9][0-9]*$/', $customerIdValue) !== 1) {
        ApiResponder::error('invalid_customer_id', 'customer_id must be a positive integer.', 422, ['field' => 'customer_id'])->send();
        exit;
    }
    if ($did === '') {
        ApiResponder::error('missing_did', 'did query parameter is required.', 422, ['field' => 'did'])->send();
        exit;
    }

    $result = $assignmentService->release((int)$customerIdValue, $did, $actor);

    if (!$result['success']) {
        ApiResponder::error('release_failed', $result['message'], 422)->send();
        exit;
    }

    ApiResponder::ok([], ['resource' => 'did-assignments', 'action' => 'release'])->send();
    exit;
}

ApiResponder::error('method_not_allowed', 'Use GET to list DIDs/assignments, POST to assign, DELETE to release.', 405)->send();
