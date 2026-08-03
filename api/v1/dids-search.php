<?php

declare(strict_types=1);

/**
 * Live DID search — queries Twilio's available number inventory in real time.
 *
 * GET /api/v1/dids-search.php?country=US&region=GA&area_code=404&limit=20
 *
 * Returns numbers directly from Twilio so tenants pick from live inventory
 * rather than a pre-loaded local table. VectaVoIP purchases on demand when
 * the tenant selects a number.
 *
 * Auth: Bearer A2BP_API_SERVICE_KEY (same as all other v1 endpoints)
 */

use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\Twilio\TwilioApiClient;

require_once __DIR__ . '/../../vendor/autoload.php';

$config  = AppConfig::fromEnvironment();
$request = JsonRequest::fromGlobals();

$authError = (new ApiServiceKeyAuthenticator($config))->authenticate($request);
if ($authError !== null) {
    $authError->send();
    exit;
}

if ($request->getMethod() !== 'GET') {
    ApiResponder::error('method_not_allowed', 'Use GET to search available DIDs.', 405)->send();
    exit;
}

$country   = strtoupper(trim($request->getString('country', 'US')));
$region    = trim($request->getString('region'));
$areaCode  = trim($request->getString('area_code'));
$contains  = trim($request->getString('contains'));
$limit     = max(1, min(50, $request->getInt('limit', 20)));

if ($country === '') {
    ApiResponder::error('missing_country', 'country is required (e.g. US, CA).', 422, ['field' => 'country'])->send();
    exit;
}

$twilioAccountSid = $config->string('TWILIO_ACCOUNT_SID');
$twilioAuthToken  = $config->string('TWILIO_AUTH_TOKEN');

if ($twilioAccountSid === '' || $twilioAuthToken === '') {
    ApiResponder::error('provider_not_configured', 'Twilio credentials are not configured on this platform.', 503)->send();
    exit;
}

$credentials = new ProviderCredentials(
    TwilioApiClient::API_BASE_URL,
    $twilioAccountSid,
    $twilioAuthToken,
    ['account_sid' => $twilioAccountSid]
);

$filters = ['PageSize' => (string)$limit, 'VoiceEnabled' => 'true', 'SmsEnabled' => 'true'];
if ($region    !== '') { $filters['InRegion']    = $region; }
if ($areaCode  !== '') { $filters['AreaCode']    = $areaCode; }
if ($contains  !== '') { $filters['Contains']    = $contains; }

try {
    $client = new TwilioApiClient();
    $result = $client->searchAvailableLocalNumbers($credentials, $country, $filters);
} catch (\Throwable $e) {
    ApiResponder::error('twilio_error', 'Number search failed: ' . $e->getMessage(), 502)->send();
    exit;
}

$numbers = $result['available_phone_numbers'] ?? [];

// Normalise to the format ZeroAI numbers.php expects
$dids = array_map(static function (array $n) use ($country): array {
    return [
        'did'          => $n['phone_number']   ?? '',
        'friendly'     => $n['friendly_name']  ?? '',
        'country'      => $country,
        'region'       => $n['region']         ?? '',
        'monthly_rate' => '1.15',   // Twilio US local standard rate
        'currency'     => 'USD',
        'capabilities' => $n['capabilities']   ?? [],
    ];
}, $numbers);

ApiResponder::ok(
    ['dids' => $dids],
    ['resource' => 'dids', 'source' => 'twilio_live', 'total' => count($dids), 'country' => $country]
)->send();
