<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\Didww;

use A2BillingPlus\Module\Provider\ProviderConnectionResult;
use A2BillingPlus\Module\Provider\ProviderCredentials;

final class DidwwStatusClient
{
    public const STATUS_PATH = '/v3/balance';

    /**
     * @param null|callable(string, ProviderCredentials): array{status:int, body:string} $transport
     */
    public function __construct(private $transport = null, private readonly int $maxAttempts = 1)
    {
    }

    public function check(ProviderCredentials $credentials): ProviderConnectionResult
    {
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
            return new ProviderConnectionResult(false, 'DIDWW status check failed: ' . $lastError);
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return new ProviderConnectionResult(false, 'DIDWW status check returned invalid JSON.');
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            return new ProviderConnectionResult(false, $this->errorMessage($decoded));
        }

        $data = $decoded['data'] ?? [];
        return new ProviderConnectionResult(true, 'DIDWW API credentials are active.', [
            'resource_count' => is_array($data) && array_is_list($data) ? count($data) : 1,
            'balance' => $this->nestedStringValue($decoded, ['data', 'attributes', 'balance']),
            'api_version' => $this->nestedStringValue($decoded, ['meta', 'api_version'], $credentials->getMetadataValue('api_version')),
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
     * @param array<string,mixed> $payload
     */
    private function errorMessage(array $payload): string
    {
        $errors = $payload['errors'] ?? null;
        if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
            $detail = $this->stringValue($errors[0], 'detail');
            if ($detail !== '') {
                return $detail;
            }
            $title = $this->stringValue($errors[0], 'title');
            if ($title !== '') {
                return $title;
            }
        }

        return 'DIDWW credentials were rejected.';
    }

    /**
     * @param array<string,mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        return is_scalar($value) ? (string)$value : $default;
    }

    /**
     * @param array<string,mixed> $values
     * @param list<string> $path
     */
    private function nestedStringValue(array $values, array $path, string $default = ''): string
    {
        $current = $values;
        foreach ($path as $key) {
            $current = $current[$key] ?? null;
            if (!is_array($current) && $key !== $path[array_key_last($path)]) {
                return $default;
            }
        }

        return is_scalar($current) ? (string)$current : $default;
    }
}
