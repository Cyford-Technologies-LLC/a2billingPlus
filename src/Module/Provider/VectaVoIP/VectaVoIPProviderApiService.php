<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\Twilio\TwilioApiClient;

final class VectaVoIPProviderApiService
{
    /**
     * @param null|callable(): TwilioApiClient $twilioClientFactory
     */
    public function __construct(
        private readonly VectaVoIPInstallationRepository $installations,
        private $twilioClientFactory = null
    )
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int, body:array<string, mixed>}
     */
    public function registerInstallation(array $payload): array
    {
        $contactEmail = $this->stringValue($payload, 'contact_email');
        if ($contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('contact_email is invalid.');
        }
        $username = $this->stringValue($payload, 'username');
        if ($username === '') {
            $username = $this->stringValue($payload, 'contact_name');
        }
        if ($username === '') {
            $username = $contactEmail !== '' ? $contactEmail : $this->requiredString($payload, 'install_key');
        }
        $password = $this->stringValue($payload, 'password');
        if ($password === '') {
            $password = bin2hex(random_bytes(16));
        }

        $request = new VectaVoIPRegistrationRequest(
            $this->requiredString($payload, 'install_key'),
            $username,
            $password,
            $this->stringValue($payload, 'company_name', $username),
            $this->stringValue($payload, 'company_domain'),
            $contactEmail,
            $this->stringValue($payload, 'request_ip'),
            $this->stringValue($payload, 'app_name', 'A2BillingPlus'),
            $this->stringValue($payload, 'app_version', '0.1.0-alpha')
        );

        $registration = $this->installations->register($request);
        $metadata = $this->metadata($registration['metadata_json'] ?? '{}');

        return [
            'status' => isset($registration['api_secret']) ? 201 : 200,
            'body' => [
                'message' => isset($registration['api_secret'])
                    ? 'Registration completed.'
                    : 'Installation was already registered.',
                'installation_id' => $registration['installation_id'],
                'api_key' => $registration['api_key'],
                'api_secret' => $registration['api_secret'] ?? '',
                'metadata' => $metadata,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int, body:array<string, mixed>}
     */
    public function createAccount(array $payload, string $apiKey, string $apiSecret): array
    {
        $installation = $this->authenticatedInstallation($apiKey, $apiSecret);
        if ($installation === null) {
            return $this->authError();
        }

        $name = $this->requiredString($payload, 'name');
        $email = $this->requiredString($payload, 'email');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['status' => 422, 'body' => ['message' => 'email is invalid.', 'field' => 'email']];
        }

        $contactMethods = $this->contactMethods($payload['contact_methods'] ?? ['email', 'text', 'sip']);
        if ($contactMethods === []) {
            return ['status' => 422, 'body' => ['message' => 'contact_methods must include email, text, or sip.', 'field' => 'contact_methods']];
        }

        $account = $this->installations->createProviderAccount($installation['installation_id'], [
            'external_id' => $this->stringValue($payload, 'external_id'),
            'name' => $name,
            'email' => $email,
            'phone' => $this->stringValue($payload, 'phone'),
            'company' => $this->stringValue($payload, 'company'),
            'contact_methods_json' => json_encode($contactMethods, JSON_UNESCAPED_SLASHES) ?: '[]',
            'sip_username' => $this->stringValue($payload, 'sip_username'),
            'sip_password' => $this->stringValue($payload, 'sip_password'),
        ]);

        return [
            'status' => 201,
            'body' => [
                'message' => 'Provider account created.',
                'installation_id' => $installation['installation_id'],
                'account' => $this->accountResponse($account),
            ],
        ];
    }

    /**
     * @param array<string, string> $query
     * @return array{status:int, body:array<string, mixed>}
     */
    public function availableDids(array $query, string $apiKey, string $apiSecret): array
    {
        $installation = $this->authenticatedInstallation($apiKey, $apiSecret);
        if ($installation === null) {
            return $this->authError();
        }

        $limit = $this->boundedInt($query['limit'] ?? '25', 1, 100);
        $offset = $this->boundedInt($query['offset'] ?? '0', 0, 1000000);
        $result = $this->installations->availableDids(
            $limit,
            $offset,
            strtoupper(trim($query['country'] ?? '')),
            trim($query['region'] ?? '')
        );

        return [
            'status' => 200,
            'body' => [
                'message' => 'Available DIDs returned.',
                'installation_id' => $installation['installation_id'],
                'total' => $result['total'],
                'dids' => $result['items'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int, body:array<string, mixed>}
     */
    public function purchaseDid(array $payload, string $apiKey, string $apiSecret): array
    {
        $installation = $this->authenticatedInstallation($apiKey, $apiSecret);
        if ($installation === null) {
            return $this->authError();
        }

        $accountId = $this->requiredInt($payload, 'account_id');
        $did = $this->requiredString($payload, 'did');
        $features = $this->contactMethods($payload['features'] ?? ['voice', 'sms']);
        $upstream = $this->normalizeUpstreamProvider($this->stringValue(
            $payload,
            'upstream_provider',
            $this->installations->getSetting('default_upstream_provider', $this->envString('VECTAVOIP_DEFAULT_UPSTREAM_PROVIDER', 'local'))
        ));

        if ($upstream === 'twilio') {
            return $this->purchaseTwilioDid($installation['installation_id'], $accountId, $did, $features, $payload);
        }

        $purchase = $this->installations->purchaseDid(
            $installation['installation_id'],
            $accountId,
            $did,
            $features,
            $this->stringValue($payload, 'routing_destination'),
            $this->stringValue($payload, 'webhook_url')
        );
        if (($purchase['success'] ?? false) !== true) {
            return ['status' => (int)($purchase['status'] ?? 422), 'body' => ['message' => (string)$purchase['message']]];
        }

        return [
            'status' => 201,
            'body' => [
                'message' => 'DID purchased.',
                'installation_id' => $installation['installation_id'],
                'purchase' => $purchase['purchase'],
            ],
        ];
    }

    /**
     * @return array{status:int, body:array<string, mixed>}
     */
    public function upstreamDefault(string $apiKey, string $apiSecret): array
    {
        $installation = $this->authenticatedInstallation($apiKey, $apiSecret);
        if ($installation === null) {
            return $this->authError();
        }

        return [
            'status' => 200,
            'body' => [
                'message' => 'Default upstream provider returned.',
                'installation_id' => $installation['installation_id'],
                'default_upstream_provider' => $this->installations->getSetting(
                    'default_upstream_provider',
                    $this->envString('VECTAVOIP_DEFAULT_UPSTREAM_PROVIDER', 'local')
                ),
                'supported_upstream_providers' => ['local', 'twilio'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int, body:array<string, mixed>}
     */
    public function setUpstreamDefault(array $payload, string $apiKey, string $apiSecret): array
    {
        $installation = $this->authenticatedInstallation($apiKey, $apiSecret);
        if ($installation === null) {
            return $this->authError();
        }

        $provider = $this->normalizeUpstreamProvider($this->requiredString($payload, 'provider'));
        $this->installations->setSetting('default_upstream_provider', $provider);

        return [
            'status' => 200,
            'body' => [
                'message' => 'Default upstream provider updated.',
                'installation_id' => $installation['installation_id'],
                'default_upstream_provider' => $provider,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int, body:array<string, mixed>}
     */
    public function sendSms(array $payload, string $apiKey, string $apiSecret): array
    {
        $installation = $this->authenticatedInstallation($apiKey, $apiSecret);
        if ($installation === null) {
            return $this->authError();
        }

        $from = $this->requiredString($payload, 'from');
        $to = $this->requiredString($payload, 'to');
        $body = $this->requiredString($payload, 'body');
        if (strlen($body) > 1600) {
            return ['status' => 422, 'body' => ['message' => 'body must be 1600 characters or fewer.', 'field' => 'body']];
        }

        $message = $this->installations->recordSms(
            $installation['installation_id'],
            $this->intValue($payload, 'account_id') ?? 0,
            $from,
            $to,
            $body,
            'outbound',
            'queued',
            'vvsms_' . bin2hex(random_bytes(8))
        );

        return [
            'status' => 202,
            'body' => [
                'message' => 'SMS queued for delivery.',
                'installation_id' => $installation['installation_id'],
                'message_id' => $message['gateway_message_id'],
                'sms' => $message,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int, body:array<string, mixed>}
     */
    public function inboundSms(array $payload, string $apiKey, string $apiSecret): array
    {
        $installation = $this->authenticatedInstallation($apiKey, $apiSecret);
        if ($installation === null) {
            return $this->authError();
        }

        $from = $this->requiredString($payload, 'from');
        $to = $this->requiredString($payload, 'to');
        $body = $this->requiredString($payload, 'body');
        $accountId = $this->installations->accountIdForDid($installation['installation_id'], $to);
        $message = $this->installations->recordSms(
            $installation['installation_id'],
            $accountId,
            $from,
            $to,
            $body,
            'inbound',
            'received',
            $this->stringValue($payload, 'message_id')
        );

        return [
            'status' => 201,
            'body' => [
                'message' => 'Inbound SMS recorded.',
                'installation_id' => $installation['installation_id'],
                'message_id' => $message['id'] ?? 0,
                'sms' => $message,
            ],
        ];
    }

    /**
     * @return array{status:int, body:array<string, mixed>}
     */
    public function installationStatus(string $apiKey, string $apiSecret): array
    {
        $installation = $this->installations->verifyCredentials($apiKey, $apiSecret);
        if ($installation === null) {
            return ['status' => 401, 'body' => ['message' => 'Invalid VectaVoIP API credentials.']];
        }

        return [
            'status' => 200,
            'body' => [
                'message' => 'VectaVoIP credentials are active.',
                'installation_id' => $installation['installation_id'],
                'status' => $installation['status'],
                'metadata' => $this->metadata($installation['metadata_json'] ?? '{}'),
            ],
        ];
    }

    /**
     * @return array{status:int, body:array<string, mixed>}
     */
    public function rotateCredentials(string $apiKey, string $apiSecret): array
    {
        $installation = $this->installations->rotateSecret($apiKey, $apiSecret);
        if ($installation === null) {
            return ['status' => 401, 'body' => ['message' => 'Invalid VectaVoIP API credentials.']];
        }

        return [
            'status' => 200,
            'body' => [
                'message' => 'VectaVoIP API secret rotated.',
                'installation_id' => $installation['installation_id'],
                'api_key' => $installation['api_key'],
                'api_secret' => $installation['api_secret'],
                'metadata' => $this->metadata($installation['metadata_json'] ?? '{}'),
            ],
        ];
    }

    /**
     * @param array<string, string> $query
     * @return array{status:int, body:array<string, mixed>}
     */
    public function ratePreview(array $query, string $apiKey, string $apiSecret): array
    {
        $installation = $this->installations->verifyCredentials($apiKey, $apiSecret);
        if ($installation === null) {
            return ['status' => 401, 'body' => ['message' => 'Invalid VectaVoIP API credentials.']];
        }

        $rateDeck = trim($query['rate_deck'] ?? 'default');
        $currency = strtoupper(trim($query['currency'] ?? 'USD'));

        if ($rateDeck === '') {
            return ['status' => 422, 'body' => ['message' => 'rate_deck is required.']];
        }
        if ($currency === '') {
            return ['status' => 422, 'body' => ['message' => 'currency is required.']];
        }

        $rows = [
            ['destination' => 'United States', 'prefix' => '1', 'rate' => '0.0100', 'currency' => $currency, 'increment' => 60, 'rate_deck' => $rateDeck],
            ['destination' => 'Canada', 'prefix' => '1', 'rate' => '0.0125', 'currency' => $currency, 'increment' => 60, 'rate_deck' => $rateDeck],
            ['destination' => 'United Kingdom', 'prefix' => '44', 'rate' => '0.0180', 'currency' => $currency, 'increment' => 60, 'rate_deck' => $rateDeck],
        ];

        return [
            'status' => 200,
            'body' => [
                'message' => 'VectaVoIP rate preview completed.',
                'installation_id' => $installation['installation_id'],
                'total_rows' => count($rows),
                'sample_rows' => $rows,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredString(array $payload, string $key): string
    {
        $value = $this->stringValue($payload, $key);
        if ($value === '') {
            throw new \InvalidArgumentException($key . ' is required.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredInt(array $payload, string $key): int
    {
        $value = $this->intValue($payload, $key);
        if ($value === null || $value <= 0) {
            throw new \InvalidArgumentException($key . ' must be a positive integer.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function intValue(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[0-9]+$/', $value) === 1) {
            return (int)$value;
        }

        return null;
    }

    private function boundedInt(string $value, int $min, int $max): int
    {
        $int = preg_match('/^[0-9]+$/', $value) === 1 ? (int)$value : $min;
        return max($min, min($max, $int));
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function contactMethods(mixed $value): array
    {
        $allowed = ['email', 'text', 'sms', 'sip', 'voice'];
        $items = is_array($value) ? $value : explode(',', is_scalar($value) ? (string)$value : '');
        $normalized = [];
        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $method = strtolower(trim((string)$item));
            if ($method === 'sms') {
                $method = 'text';
            }
            if (in_array($method, $allowed, true) && !in_array($method, $normalized, true)) {
                $normalized[] = $method;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, string>|null
     */
    private function authenticatedInstallation(string $apiKey, string $apiSecret): ?array
    {
        return $this->installations->verifyCredentials($apiKey, $apiSecret);
    }

    /**
     * @return array{status:int, body:array<string, mixed>}
     */
    private function authError(): array
    {
        return ['status' => 401, 'body' => ['message' => 'Invalid VectaVoIP API credentials.']];
    }

    /**
     * @param list<string> $features
     * @param array<string, mixed> $payload
     * @return array{status:int, body:array<string, mixed>}
     */
    private function purchaseTwilioDid(string $installationId, int $accountId, string $did, array $features, array $payload): array
    {
        $account = $this->installations->findProviderAccount($installationId, $accountId);
        if ($account === null) {
            return ['status' => 404, 'body' => ['message' => 'Provider account was not found.']];
        }

        try {
            $twilioPayload = $this->twilioPurchasePayload($did, $payload);
            $preferredTrunkSid = $this->preferredTwilioTrunkSid($payload);
            if ($this->twilioSandboxMode()) {
                $twilioNumber = $this->sandboxTwilioNumber($did, $twilioPayload, $preferredTrunkSid);
            } else {
                $client = $this->twilioClient();
                $credentials = $this->twilioCredentials();
                $twilioNumber = $client->purchaseIncomingPhoneNumber($credentials, $twilioPayload);
                if ($preferredTrunkSid !== '' && $this->stringValue($twilioNumber, 'sid') !== '' && method_exists($client, 'attachPhoneNumberToTrunk')) {
                    $client->attachPhoneNumberToTrunk($credentials, $preferredTrunkSid, $this->stringValue($twilioNumber, 'sid'));
                    $twilioNumber['trunk_sid'] = $preferredTrunkSid;
                }
            }
        } catch (\Throwable $exception) {
            return ['status' => 422, 'body' => ['message' => 'Twilio DID purchase failed: ' . $exception->getMessage()]];
        }

        $purchase = $this->installations->recordUpstreamDidPurchase(
            $installationId,
            $accountId,
            $did,
            $features,
            $this->stringValue($payload, 'routing_destination'),
            $this->stringValue($payload, 'webhook_url'),
            'twilio',
            $this->stringValue($twilioNumber, 'sid'),
            $this->stringValue($twilioNumber, 'trunk_sid', $this->preferredTwilioTrunkSid($payload)),
            $this->stringValue($twilioNumber, 'friendly_name')
        );

        return [
            'status' => 201,
            'body' => [
                'message' => 'DID purchased from Twilio.',
                'installation_id' => $installationId,
                'upstream_provider' => 'twilio',
                'purchase' => $purchase,
                'upstream' => [
                    'provider' => 'twilio',
                    'sandbox' => $this->twilioSandboxMode(),
                    'sid' => $this->stringValue($twilioNumber, 'sid'),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function twilioPurchasePayload(string $did, array $payload): array
    {
        $twilioPayload = ['PhoneNumber' => $did];
        foreach ([
            'VoiceUrl' => $this->stringValue($payload, 'voice_url', $this->envString('TWILIO_DEFAULT_VOICE_URL')),
            'SmsUrl' => $this->stringValue($payload, 'sms_url', $this->envString('TWILIO_DEFAULT_SMS_URL')),
            'FriendlyName' => $this->stringValue($payload, 'friendly_name'),
        ] as $key => $value) {
            if ($value !== '') {
                $twilioPayload[$key] = $value;
            }
        }

        return $twilioPayload;
    }

    private function twilioCredentials(): ProviderCredentials
    {
        $accountSid = $this->envString('TWILIO_ACCOUNT_SID');
        $apiKey = $this->envString('TWILIO_API_KEY', $accountSid);
        $apiSecret = $this->envString('TWILIO_API_SECRET', $this->envString('TWILIO_AUTH_TOKEN'));

        return new ProviderCredentials(
            $this->envString('TWILIO_API_BASE_URL', TwilioApiClient::API_BASE_URL),
            $apiKey,
            $apiSecret,
            ['account_sid' => $accountSid]
        );
    }

    private function twilioClient(): object
    {
        if (is_callable($this->twilioClientFactory)) {
            return ($this->twilioClientFactory)();
        }

        return new TwilioApiClient();
    }

    /**
     * @param array<string, string> $payload
     * @return array<string, string>
     */
    private function sandboxTwilioNumber(string $did, array $payload, string $preferredTrunkSid = ''): array
    {
        return [
            'sid' => 'PN_SANDBOX_' . substr(strtoupper(hash('sha256', $did)), 0, 20),
            'phone_number' => $did,
            'friendly_name' => $payload['FriendlyName'] ?? 'VectaVoIP Twilio Sandbox DID',
            'trunk_sid' => $preferredTrunkSid,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function preferredTwilioTrunkSid(array $payload = []): string
    {
        $requested = $this->stringValue($payload, 'trunk_sid');
        if ($requested !== '') {
            return $requested;
        }

        $elastic = $this->envString('TWILIO_ELASTIC_TRUNK_SID', $this->envString('TWILIO_TRUNK_SID'));
        if ($elastic !== '') {
            return $elastic;
        }

        return $this->envString('TWILIO_BYOC_TRUNK_SID');
    }

    private function twilioSandboxMode(): bool
    {
        return in_array(strtolower($this->envString('TWILIO_SANDBOX_MODE', '0')), ['1', 'true', 'yes', 'on'], true);
    }

    private function normalizeUpstreamProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        if ($provider === '' || $provider === 'vectavoip') {
            return 'local';
        }
        if (!in_array($provider, ['local', 'twilio'], true)) {
            throw new \InvalidArgumentException('Unsupported upstream provider.');
        }

        return $provider;
    }

    private function envString(string $key, string $default = ''): string
    {
        if (!str_starts_with($key, 'A2BP_DB_')) {
            $value = $this->installations->getRuntimeSetting($key);
            if ($value !== '') {
                return $value;
            }
        }

        $fileValues = self::envFileValues();
        if (($fileValues[$key . '_FILE'] ?? '') !== '' && is_readable($fileValues[$key . '_FILE'])) {
            $contents = file_get_contents($fileValues[$key . '_FILE']);
            if (is_string($contents)) {
                return trim($contents);
            }
        }

        if (($fileValues[$key] ?? '') !== '') {
            return $fileValues[$key];
        }

        $value = getenv($key);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $file = getenv($key . '_FILE');
        if (is_string($file) && $file !== '' && is_readable($file)) {
            $contents = file_get_contents($file);
            if (is_string($contents)) {
                return trim($contents);
            }
        }

        return $default;
    }

    /**
     * @return array<string,string>
     */
    private static function envFileValues(): array
    {
        $values = [];
        $envPath = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($envPath)) {
            return $values;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return $values;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }

            if (
                strlen($value) >= 2
                && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    private function accountResponse(array $account): array
    {
        $methods = json_decode((string)($account['contact_methods_json'] ?? '[]'), true);
        $account['contact_methods'] = is_array($methods) ? array_values($methods) : [];
        unset($account['contact_methods_json']);

        return $account;
    }

    /**
     * @param array<string, mixed> $payload
     */
    /**
     * @param array<string, mixed> $payload
     */
    private function stringValue(array $payload, string $key, string $default = ''): string
    {
        $value = $payload[$key] ?? $default;
        return is_scalar($value) ? trim((string)$value) : $default;
    }

    /**
     * @return array<string, string>
     */
    private function metadata(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $metadata = [];
        foreach ($decoded as $key => $value) {
            if (is_scalar($value)) {
                $metadata[(string)$key] = (string)$value;
            }
        }

        return $metadata;
    }
}
