<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

use A2BillingPlus\Module\Provider\ProviderConnectionResult;
use A2BillingPlus\Module\Provider\ProviderCredentials;

final class VectaVoIPStatusClient
{
    public const STATUS_PATH = '/v1/installations/status';

    /**
     * @param null|callable(string, ProviderCredentials): array{status:int, body:string} $transport
     */
    public function __construct(private $transport = null, private readonly int $maxAttempts = 1)
    {
    }

    public function check(ProviderCredentials $credentials): ProviderConnectionResult
    {
        $credentials = $this->canonicalCredentials($credentials);
        $response = null;
        $lastError = '';
        for ($attempt = 1; $attempt <= max(1, $this->maxAttempts); $attempt++) {
            try {
                $response = $this->getJson($credentials->getBaseUrl() . self::STATUS_PATH, $credentials);
                if ($response['status'] < 500) {
                    break;
                }
                $lastError = 'HTTP ' . $response['status'];
            } catch (\Throwable $exception) {
                $lastError = $exception->getMessage();
            }
        }
        if ($response === null) {
            return new ProviderConnectionResult(false, 'VectaVoIP status check failed: ' . $lastError);
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return new ProviderConnectionResult(false, 'VectaVoIP status check returned invalid JSON.');
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            return new ProviderConnectionResult(false, $this->stringValue($decoded, 'message', 'VectaVoIP credentials were rejected.'));
        }

        return new ProviderConnectionResult(true, $this->stringValue($decoded, 'message', 'VectaVoIP credentials are active.'), [
            'installation_id' => $this->stringValue($decoded, 'installation_id'),
            'status' => $this->stringValue($decoded, 'status'),
        ]);
    }

    /**
     * @return array{status:int, body:string}
     */
    private function getJson(string $url, ProviderCredentials $credentials): array
    {
        if (is_callable($this->transport)) {
            return ($this->transport)($url, $credentials);
        }

        if (function_exists('curl_init')) {
            return $this->getWithCurl($url, $credentials);
        }

        return $this->getWithStreams($url, $credentials);
    }

    /**
     * @return array{status:int, body:string}
     */
    private function getWithCurl(string $url, ProviderCredentials $credentials): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Could not initialize curl.');
        }

        curl_setopt_array($handle, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $this->headers($credentials),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($body)) {
            throw new \RuntimeException($error !== '' ? $error : 'No response body.');
        }

        return ['status' => $status, 'body' => $body];
    }

    /**
     * @return array{status:int, body:string}
     */
    private function getWithStreams(string $url, ProviderCredentials $credentials): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $this->headers($credentials)) . "\r\n",
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
                $status = (int)$matches[1];
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
        return [
            'Accept: application/json',
            'Authorization: Bearer ' . $credentials->getApiKey(),
            'X-VectaVoIP-Secret: ' . $credentials->getApiSecret(),
        ];
    }

    private function canonicalCredentials(ProviderCredentials $credentials): ProviderCredentials
    {
        return new ProviderCredentials(
            VectaVoIPConnector::API_BASE_URL,
            $credentials->getApiKey(),
            $credentials->getApiSecret(),
            $credentials->getMetadata()
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        return is_scalar($value) ? (string)$value : $default;
    }
}
