<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;

/**
 * Generates a unique Account Number (username) + LOGIN (useralias) pair for
 * cc_card, the same way the classic admin UI does via gen_card_with_alias()/
 * MDP() (admin/lib/Misc.php) -- reimplemented standalone here rather than
 * modifying that legacy ADOdb-based function or any core REST resource file.
 *
 * A caller (e.g. ZeroAI-CRM) fetches a pair from this endpoint first, then
 * submits it as normal username/useralias fields on POST /api/v1/customers.php
 * -- exactly like the admin form pre-fills the Account Number before the
 * admin submits the "add card" form. customers.php itself is untouched.
 *
 * GET /api/v1/account-number.php
 * Response: {success, data: {username, useralias}}
 */

$config = AppConfig::fromEnvironment();
$request = JsonRequest::fromGlobals();

$authError = (new ApiServiceKeyAuthenticator($config))->authenticate($request);
if ($authError !== null) {
    $authError->send();
    exit;
}

if ($request->getMethod() !== 'GET') {
    ApiResponder::error('method_not_allowed', 'This endpoint supports GET only.', 405)->send();
    exit;
}

$usernameLength = 10;
$aliasLength = 15;

$pdo = new PDO($config->databaseDsn(), $config->string('A2BP_DB_USER', 'a2billinguser'), $config->string('A2BP_DB_PASSWORD', 'a2billing'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$check = $pdo->prepare('SELECT COUNT(*) FROM cc_card WHERE username = :u OR useralias = :a');

$username = null;
$useralias = null;
for ($attempt = 0; $attempt < 200; $attempt++) {
    $candidateUsername = randomDigits($usernameLength);
    $candidateAlias = randomDigits($aliasLength);
    $check->execute(['u' => $candidateUsername, 'a' => $candidateAlias]);
    if ((int)$check->fetchColumn() === 0) {
        $username = $candidateUsername;
        $useralias = $candidateAlias;
        break;
    }
}

if ($username === null) {
    ApiResponder::error('account_number_generation_failed', 'Could not generate a unique Account Number/LOGIN after 200 attempts.', 500)->send();
    exit;
}

ApiResponder::ok(['username' => $username, 'useralias' => $useralias])->send();

function randomDigits(int $length): string
{
    $digits = '';
    for ($i = 0; $i < $length; $i++) {
        $digits .= (string)random_int(0, 9);
    }
    return $digits;
}
