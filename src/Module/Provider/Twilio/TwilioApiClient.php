<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Twilio;

use A2BillingPlus\Module\Provider\ProviderCredentials;

final class TwilioApiClient
{
    public const API_BASE_URL = 'https://api.twilio.com';
    public const TRUNKING_BASE_URL = 'https://trunking.twilio.com';
    public const VOICE_BASE_URL = 'https://voice.twilio.com';
    public const PRICING_BASE_URL = 'https://pricing.twilio.com';

    /**
     * @param null|callable(string, string, ProviderCredentials, array<string, mixed>|null, array<string, string>): array{status:int, body:string} $transport
     */
    public function __construct(private $transport = null, private readonly int $maxAttempts = 1)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function account(ProviderCredentials $credentials): array
    {
        return $this->request('GET', $this->apiPath($credentials, '.json'), $credentials);
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function listIncomingPhoneNumbers(ProviderCredentials $credentials, array $filters = []): array
    {
        return $this->request('GET', $this->apiPath($credentials, '/IncomingPhoneNumbers.json'), $credentials, $filters);
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function searchAvailableLocalNumbers(ProviderCredentials $credentials, string $countryCode, array $filters = []): array
    {
        $countryCode = strtoupper(trim($countryCode));
        if ($countryCode === '') {
            throw new \RuntimeException('Twilio country code is required.');
        }

        return $this->request(
            'GET',
            $this->apiPath($credentials, '/AvailablePhoneNumbers/' . rawurlencode($countryCode) . '/Local.json'),
            $credentials,
            $filters
        );
    }

    /**
     * @param array<string, string> $payload
     * @return array<string, mixed>
     */
    public function purchaseIncomingPhoneNumber(ProviderCredentials $credentials, array $payload): array
    {
        return $this->request(
            'POST',
            $this->apiPath($credentials, '/IncomingPhoneNumbers.json'),
            $credentials,
            [],
            $payload
        );
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function listTrunks(ProviderCredentials $credentials, array $filters = []): array
    {
        return $this->request('GET', self::TRUNKING_BASE_URL . '/v1/Trunks', $credentials, $filters);
    }

    /**
     * @param array<string, string> $payload
     * @return array<string, mixed>
     */
    public function createTrunk(ProviderCredentials $credentials, array $payload): array
    {
        return $this->request('POST', self::TRUNKING_BASE_URL . '/v1/Trunks', $credentials, [], $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTrunk(ProviderCredentials $credentials, string $trunkSid): array
    {
        if (trim($trunkSid) === '') {
            throw new \RuntimeException('Twilio trunk SID is required.');
        }

        return $this->request(
            'GET',
            self::TRUNKING_BASE_URL . '/v1/Trunks/' . rawurlencode($trunkSid),
            $credentials
        );
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function listByocTrunks(ProviderCredentials $credentials, array $filters = []): array
    {
        return $this->request('GET', self::VOICE_BASE_URL . '/v1/ByocTrunks', $credentials, $filters);
    }

    /**
     * @return array<string, mixed>
     */
    public function getByocTrunk(ProviderCredentials $credentials, string $trunkSid): array
    {
        if (trim($trunkSid) === '') {
            throw new \RuntimeException('Twilio BYOC trunk SID is required.');
        }

        return $this->request(
            'GET',
            self::VOICE_BASE_URL . '/v1/ByocTrunks/' . rawurlencode($trunkSid),
            $credentials
        );
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function listTrunkPhoneNumbers(ProviderCredentials $credentials, string $trunkSid, array $filters = []): array
    {
        return $this->request(
            'GET',
            self::TRUNKING_BASE_URL . '/v1/Trunks/' . rawurlencode($trunkSid) . '/PhoneNumbers',
            $credentials,
            $filters
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attachPhoneNumberToTrunk(ProviderCredentials $credentials, string $trunkSid, string $phoneNumberSid): array
    {
        return $this->request(
            'POST',
            self::TRUNKING_BASE_URL . '/v1/Trunks/' . rawurlencode($trunkSid) . '/PhoneNumbers',
            $credentials,
            [],
            ['PhoneNumberSid' => $phoneNumberSid]
        );
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function listVoicePricingCountries(ProviderCredentials $credentials, array $filters = []): array
    {
        return $this->request('GET', self::PRICING_BASE_URL . '/v2/Voice/Countries', $credentials, $filters);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchVoicePricingCountry(ProviderCredentials $credentials, string $isoCountry): array
    {
        $isoCountry = strtoupper(trim($isoCountry));
        if ($isoCountry === '') {
            throw new \RuntimeException('Twilio pricing country code is required.');
        }

        return $this->request(
            'GET',
            self::PRICING_BASE_URL . '/v2/Voice/Countries/' . rawurlencode($isoCountry),
            $credentials
        );
    }

    private function apiPath(ProviderCredentials $credentials, string $path): string
    {
        $accountSid = trim($credentials->getMetadataValue('account_sid'));
        if ($accountSid === '') {
            throw new \RuntimeException('Twilio Account SID is required.');
        }

        return rtrim($credentials->getBaseUrl() !== '' ? $credentials->getBaseUrl() : self::API_BASE_URL, '/')
            . '/2010-04-01/Accounts/' . rawurlencode($accountSid) . $path;
    }

    /**
     * @param array<string, string> $query
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $url,
        ProviderCredentials $credentials,
        array $query = [],
        ?array $payload = null
    ): array {
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $response = null;
        $lastError = '';
        for ($attempt = 1; $attempt <= max(1, $this->maxAttempts); $attempt++) {
            try {
                $response = $this->send($method, $url, $credentials, $payload);
                if ($response['status'] < 500) {
                    break;
                }
                $lastError = 'HTTP ' . $response['status'];
            } catch (\Throwable $exception) {
                $lastError = $exception->getMessage();
            }
        }

        if ($response === null) {
            throw new \RuntimeException('Twilio request failed: ' . $lastError);
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Twilio returned invalid JSON.');
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new \RuntimeException($this->errorMessage($decoded));
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{status:int, body:string}
     */
    private function send(string $method, string $url, ProviderCredentials $credentials, ?array $payload = null): array
    {
        $authUser = trim($credentials->getApiKey()) !== '' ? $credentials->getApiKey() : $credentials->getMetadataValue('account_sid');
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($authUser . ':' . $credentials->getApiSecret()),
        ];
        if (is_callable($this->transport)) {
            return ($this->transport)($method, $url, $credentials, $payload, $headers);
        }

        if (function_exists('curl_init')) {
            return $this->sendWithCurl($method, $url, $payload, $headers);
        }

        return $this->sendWithStreams($method, $url, $payload, $headers);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string> $headers
     * @return array{status:int, body:string}
     */
    private function sendWithCurl(string $method, string $url, ?array $payload, array $headers): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Could not initialize curl.');
        }

        $flatHeaders = [];
        foreach ($headers as $name => $value) {
            $flatHeaders[] = $name . ': ' . $value;
        }
        $flatHeaders[] = 'Accept: application/json';

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $flatHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ];

        if ($payload !== null) {
            $flatHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
            $options[CURLOPT_HTTPHEADER] = $flatHeaders;
            $options[CURLOPT_POSTFIELDS] = http_build_query($payload);
        }

        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($body)) {
            throw new \RuntimeException($error !== '' ? $error : 'No response body.');
        }

        return ['status' => $status, 'body' => $body];
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string> $headers
     * @return array{status:int, body:string}
     */
    private function sendWithStreams(string $method, string $url, ?array $payload, array $headers): array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $headerLines[] = 'Accept: application/json';
        $content = null;
        if ($payload !== null) {
            $headerLines[] = 'Content-Type: application/x-www-form-urlencoded';
            $content = http_build_query($payload);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines) . "\r\n",
                'content' => $content,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $body = file_get_contents($url, false, $context);
        if (!is_string($body)) {
            throw new \RuntimeException('HTTP request failed.');
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                $status = (int) $matches[1];
                break;
            }
        }

        return ['status' => $status, 'body' => $body];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function errorMessage(array $payload): string
    {
        foreach (['message', 'detail'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return 'Twilio request was rejected.';
    }
}
