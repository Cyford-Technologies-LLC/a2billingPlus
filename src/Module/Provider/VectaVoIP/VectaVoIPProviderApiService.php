<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

final class VectaVoIPProviderApiService
{
    public function __construct(private readonly VectaVoIPInstallationRepository $installations)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int, body:array<string, mixed>}
     */
    public function registerInstallation(array $payload): array
    {
        $request = new VectaVoIPRegistrationRequest(
            $this->requiredString($payload, 'install_key'),
            $this->requiredString($payload, 'username'),
            $this->requiredString($payload, 'password'),
            $this->stringValue($payload, 'company_name', $this->requiredString($payload, 'username')),
            $this->stringValue($payload, 'company_domain'),
            $this->stringValue($payload, 'contact_email'),
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
