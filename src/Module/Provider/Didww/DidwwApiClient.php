<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Didww;

use A2BillingPlus\Module\Provider\ProviderCredentials;

final class DidwwApiClient
{
    /**
     * @param null|callable(string, string, ProviderCredentials, array<string, mixed>|null): array{status:int, body:string} $transport
     */
    public function __construct(private $transport = null, private readonly int $maxAttempts = 1)
    {
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function listDids(ProviderCredentials $credentials, array $filters = []): array
    {
        return $this->request('GET', '/v3/dids', $credentials, $this->query($filters, [
            'include' => 'voice_in_trunk,order,did_group',
        ]));
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function listInboundTrunks(ProviderCredentials $credentials, array $filters = []): array
    {
        return $this->request('GET', '/v3/voice_in_trunks', $credentials, $this->query($filters, [
            'include' => 'voice_in_trunk_group,pop',
        ]));
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function listOrders(ProviderCredentials $credentials, array $filters = []): array
    {
        return $this->request('GET', '/v3/orders', $credentials, $this->query($filters));
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(ProviderCredentials $credentials, string $orderId): array
    {
        return $this->request('GET', '/v3/orders/' . rawurlencode($orderId), $credentials);
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function searchAvailableDids(ProviderCredentials $credentials, array $filters = []): array
    {
        return $this->request('GET', '/v3/available_dids', $credentials, $this->query($filters, [
            'include' => 'did_group,did_group.stock_keeping_units,nanpa_prefix',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function createOrder(
        ProviderCredentials $credentials,
        string $availableDidId,
        string $skuId,
        string $callbackUrl = '',
        bool $allowBackOrdering = false
    ): array {
        $payload = [
            'data' => [
                'type' => 'orders',
                'attributes' => [
                    'allow_back_ordering' => $allowBackOrdering,
                    'items' => [[
                        'type' => 'did_order_items',
                        'attributes' => [
                            'available_did_id' => $availableDidId,
                            'sku_id' => $skuId,
                        ],
                    ]],
                ],
            ],
        ];

        if ($callbackUrl !== '') {
            $payload['data']['attributes']['callback_url'] = $callbackUrl;
            $payload['data']['attributes']['callback_method'] = 'POST';
        }

        return $this->request('POST', '/v3/orders', $credentials, [], $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createInboundTrunk(ProviderCredentials $credentials, array $payload): array
    {
        return $this->request('POST', '/v3/voice_in_trunks', $credentials, [], [
            'data' => [
                'type' => 'voice_in_trunks',
                'attributes' => $payload,
            ],
        ]);
    }

    /**
     * @param array<string, string> $query
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $path,
        ProviderCredentials $credentials,
        array $query = [],
        ?array $payload = null
    ): array {
        $url = $credentials->getBaseUrl() . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
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
            throw new \RuntimeException('DIDWW request failed: ' . $lastError);
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('DIDWW returned invalid JSON.');
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
        if (is_callable($this->transport)) {
            return ($this->transport)($method, $url, $credentials, $payload);
        }

        if (function_exists('curl_init')) {
            return $this->sendWithCurl($method, $url, $credentials, $payload);
        }

        return $this->sendWithStreams($method, $url, $credentials, $payload);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{status:int, body:string}
     */
    private function sendWithCurl(string $method, string $url, ProviderCredentials $credentials, ?array $payload = null): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Could not initialize curl.');
        }

        $headers = $this->headers($credentials);
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ];

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
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
     * @return array{status:int, body:string}
     */
    private function sendWithStreams(string $method, string $url, ProviderCredentials $credentials, ?array $payload = null): array
    {
        $content = $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) : null;
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $this->headers($credentials)) . "\r\n",
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
     * @return list<string>
     */
    private function headers(ProviderCredentials $credentials): array
    {
        $headers = [
            'Accept: application/vnd.api+json',
            'Content-Type: application/vnd.api+json',
            'Api-Key: ' . $credentials->getApiKey(),
        ];

        $apiVersion = $credentials->getMetadataValue('api_version');
        if ($apiVersion !== '') {
            $headers[] = 'X-DIDWW-API-Version: ' . $apiVersion;
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function errorMessage(array $payload): string
    {
        $errors = $payload['errors'] ?? null;
        if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
            foreach (['detail', 'title', 'code'] as $key) {
                $value = $errors[0][$key] ?? null;
                if (is_scalar($value) && (string) $value !== '') {
                    return (string) $value;
                }
            }
        }

        return 'DIDWW request was rejected.';
    }

    /**
     * @param array<string, string> $filters
     * @param array<string, string> $base
     * @return array<string, string>
     */
    private function query(array $filters, array $base = []): array
    {
        foreach ($filters as $key => $value) {
            if ($value === '') {
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }
}
