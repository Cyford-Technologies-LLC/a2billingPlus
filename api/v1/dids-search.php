<?php

declare(strict_types=1);

/**
 * Live DID search — queries the active SMS provider's available number inventory.
 *
 * Provider selected by SMS_PROVIDER runtime setting (telnyx | twilio).
 * Defaults to Telnyx when TELNYX_API_KEY is set, Twilio otherwise.
 *
 * GET /api/v1/dids-search.php?country=US&region=GA&area_code=404&limit=20
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

$country  = strtoupper(trim($request->getString('country', 'US')));
$region   = trim($request->getString('region'));
$areaCode = trim($request->getString('area_code'));
$contains = trim($request->getString('contains'));
$limit    = max(1, min(50, $request->getInt('limit', 20)));

if ($country === '') {
    ApiResponder::error('missing_country', 'country is required (e.g. US, CA).', 422, ['field' => 'country'])->send();
    exit;
}

// ── Pull already-purchased inventory numbers (status='available') first ───────
$inventoryDids = [];
try {
    $pdo = new PDO(
        $config->databaseDsn(),
        $config->string('A2BP_DB_USER', 'a2billinguser'),
        $config->string('A2BP_DB_PASSWORD', 'a2billing'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $sql    = "SELECT did, country, region, monthly_rate, currency FROM cc_vectavoip_did_inventory WHERE status='available'";
    $params = [];
    if ($country !== '') { $sql .= ' AND country = ?'; $params[] = $country; }
    if ($region  !== '') { $sql .= ' AND region = ?';  $params[] = $region; }
    if ($areaCode !== '') {
        // match area code prefix after country code (+1 for US)
        $sql .= ' AND (did LIKE ? OR did LIKE ?)';
        $params[] = '+1' . $areaCode . '%';
        $params[] = $areaCode . '%';
    }
    if ($contains !== '') { $sql .= ' AND did LIKE ?'; $params[] = '%' . $contains . '%'; }
    $sql .= ' ORDER BY did ASC LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        $inventoryDids[] = [
            'did'          => $row['did'],
            'friendly'     => $row['did'],
            'country'      => $row['country'] ?: $country,
            'region'       => $row['region']  ?: '',
            'monthly_rate' => $row['monthly_rate'] ?? '1.00',
            'currency'     => $row['currency']     ?? 'USD',
            'capabilities' => ['sms', 'voice'],
            'source'       => 'inventory',
        ];
    }
} catch (\Throwable $e) {
    // Non-fatal — proceed without inventory results
}

// Resolve active provider
$smsProvider = $config->string('SMS_PROVIDER');
$telnyxKey   = $config->string('TELNYX_API_KEY');
$telnyxBase  = $config->string('TELNYX_API_BASE_URL', 'https://api.telnyx.com');

if ($smsProvider === '') {
    $smsProvider = $telnyxKey !== '' ? 'telnyx' : 'twilio';
}

// ── Telnyx ────────────────────────────────────────────────────────────────────
if ($smsProvider === 'telnyx') {
    if ($telnyxKey === '') {
        ApiResponder::error('provider_not_configured', 'Telnyx API key is not configured.', 503)->send();
        exit;
    }

    // Build query string
    $params = [
        'filter[country_code]'     => $country,
        'filter[features][]'       => 'sms',   // separate voice filter added below
        'page[size]'               => $limit,
        'filter[phone_number_type]' => 'local',
    ];
    if ($region   !== '') { $params['filter[administrative_area]'] = $region; }
    if ($areaCode !== '') { $params['filter[national_destination_code]'] = $areaCode; }
    if ($contains !== '') { $params['filter[phone_number][contains]'] = $contains; }

    $qs  = http_build_query($params) . '&filter[features][]=voice';
    $url = rtrim($telnyxBase, '/') . '/v2/available_phone_numbers?' . $qs;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $telnyxKey,
            'Accept: application/json',
        ],
    ]);
    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $curlErr !== '') {
        ApiResponder::error('telnyx_error', 'Number search failed: ' . $curlErr, 502)->send();
        exit;
    }

    $body = json_decode((string)$raw, true);
    if ($httpCode >= 400) {
        $msg = $body['errors'][0]['detail'] ?? $body['errors'][0]['title'] ?? 'Telnyx API error';
        ApiResponder::error('telnyx_error', $msg, 502)->send();
        exit;
    }

    $numbers = $body['data'] ?? [];
    $dids = array_map(static function (array $n) use ($country): array {
        // Extract state abbreviation from region_information array
        $region = '';
        foreach ($n['region_information'] ?? [] as $r) {
            $rType = $r['region_type'] ?? '';
            if ($rType === 'state_abbreviation') {
                $region = $r['region_name'] ?? '';
                break;
            }
            if ($rType === 'state' && $region === '') {
                $region = $r['region_name'] ?? '';
            }
        }
        // Extract feature names from array of {name: "sms"} objects
        $caps = [];
        foreach ($n['features'] ?? [] as $f) {
            if (isset($f['name'])) { $caps[] = $f['name']; }
        }
        return [
            'did'          => $n['phone_number'] ?? '',
            'friendly'     => $n['phone_number'] ?? '',
            'country'      => $country,
            'region'       => $region,
            'monthly_rate' => $n['cost_information']['monthly_cost'] ?? '1.00',
            'currency'     => $n['cost_information']['currency']     ?? 'USD',
            'capabilities' => $caps,
            'source'       => 'telnyx_live',
        ];
    }, $numbers);

    // Deduplicate: remove live results already covered by inventory
    $inventoryNumbers = array_column($inventoryDids, 'did');
    $dids = array_values(array_filter($dids, static fn($d) => !in_array($d['did'], $inventoryNumbers, true)));

    $merged = array_merge($inventoryDids, $dids);
    ApiResponder::ok(
        ['dids' => $merged],
        ['resource' => 'dids', 'source' => 'telnyx_live', 'total' => count($merged), 'country' => $country]
    )->send();
    exit;
}

// ── Twilio (fallback) ─────────────────────────────────────────────────────────
$twilioAccountSid = $config->string('TWILIO_ACCOUNT_SID');
$twilioAuthToken  = $config->string('TWILIO_AUTH_TOKEN');

if ($twilioAccountSid === '' || $twilioAuthToken === '') {
    ApiResponder::error('provider_not_configured', 'No SMS provider credentials are configured.', 503)->send();
    exit;
}

$credentials = new ProviderCredentials(
    TwilioApiClient::API_BASE_URL,
    $twilioAccountSid,
    $twilioAuthToken,
    ['account_sid' => $twilioAccountSid]
);

$filters = ['PageSize' => (string)$limit, 'VoiceEnabled' => 'true', 'SmsEnabled' => 'true'];
if ($region   !== '') { $filters['InRegion'] = $region; }
if ($areaCode !== '') { $filters['AreaCode'] = $areaCode; }
if ($contains !== '') { $filters['Contains'] = $contains; }

try {
    $client = new TwilioApiClient();
    $result = $client->searchAvailableLocalNumbers($credentials, $country, $filters);
} catch (\Throwable $e) {
    ApiResponder::error('twilio_error', 'Number search failed: ' . $e->getMessage(), 502)->send();
    exit;
}

$numbers = $result['available_phone_numbers'] ?? [];
$dids = array_map(static function (array $n) use ($country): array {
    return [
        'did'          => $n['phone_number']  ?? '',
        'friendly'     => $n['friendly_name'] ?? '',
        'country'      => $country,
        'region'       => $n['region']        ?? '',
        'monthly_rate' => '1.15',
        'currency'     => 'USD',
        'capabilities' => $n['capabilities']  ?? [],
        'source'       => 'twilio_live',
    ];
}, $numbers);

// Deduplicate: remove live results already covered by inventory
$inventoryNumbers = array_column($inventoryDids, 'did');
$dids = array_values(array_filter($dids, static fn($d) => !in_array($d['did'], $inventoryNumbers, true)));

$merged = array_merge($inventoryDids, $dids);
ApiResponder::ok(
    ['dids' => $merged],
    ['resource' => 'dids', 'source' => 'twilio_live', 'total' => count($merged), 'country' => $country]
)->send();
