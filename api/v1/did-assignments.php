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

    $customerId = (int)$customerIdValue;
    $smsEnabled = ($payload['sms_enabled'] ?? true) !== false;
    $voiceEnabled = ($payload['voice_enabled'] ?? true) !== false;

    $result = $assignmentService->assign($customerId, $did, $smsEnabled, $voiceEnabled, $actor);

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
