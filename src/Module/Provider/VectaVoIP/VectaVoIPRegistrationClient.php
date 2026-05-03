<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

final class VectaVoIPRegistrationClient
{
    public const REGISTER_PATH = '/v1/installations/register';

    /**
     * @param null|callable(string, array<string, string>): array{status:int, body:string} $transport
     */
    public function __construct(
        private readonly string $baseUrl = VectaVoIPConnector::API_BASE_URL,
        private $transport = null,
        private readonly int $maxAttempts = 1
    ) {
    }

    public function register(VectaVoIPRegistrationRequest $request): VectaVoIPRegistrationResult
    {
        $url = rtrim($this->baseUrl, '/') . self::REGISTER_PATH;
        $payload = $request->toPayload();

        $response = null;
        $lastError = '';
        for ($attempt = 1; $attempt <= max(1, $this->maxAttempts); $attempt++) {
            try {
                $response = $this->postJson($url, $payload);
                if ($response['status'] < 500) {
                    break;
                }
                $lastError = 'HTTP ' . $response['status'];
            } catch (\Throwable $exception) {
                $lastError = $exception->getMessage();
            }
        }
        if ($response === null) {
            return new VectaVoIPRegistrationResult(false, 'VectaVoIP registration failed: ' . $lastError);
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return new VectaVoIPRegistrationResult(false, 'VectaVoIP registration returned invalid JSON.');
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            return new VectaVoIPRegistrationResult(
                false,
                $this->stringValue($decoded, 'message', 'VectaVoIP registration was rejected.')
            );
        }

        $apiKey = $this->stringValue($decoded, 'api_key');
        if ($apiKey === '') {
            return new VectaVoIPRegistrationResult(false, 'VectaVoIP registration did not return an API key.');
        }

        return new VectaVoIPRegistrationResult(
            true,
            $this->stringValue($decoded, 'message', 'VectaVoIP registration completed.'),
            $this->stringValue($decoded, 'installation_id'),
            $apiKey,
            $this->stringValue($decoded, 'api_secret'),
            $this->stringMap($decoded['metadata'] ?? [])
        );
    }

    /**
     * @param array<string, string> $payload
     * @return array{status:int, body:string}
     */
    private function postJson(string $url, array $payload): array
    {
        if (is_callable($this->transport)) {
            return ($this->transport)($url, $payload);
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        if (function_exists('curl_init')) {
            return $this->postWithCurl($url, $json);
        }

        return $this->postWithStreams($url, $json);
    }

    /**
     * @return array{status:int, body:string}
     */
    private function postWithCurl(string $url, string $json): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Could not initialize curl.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
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
    private function postWithStreams(string $url, string $json): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $json,
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
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        return is_scalar($value) ? (string)$value : $default;
    }

    /**
     * @param mixed $values
     * @return array<string, string>
     */
    private function stringMap(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $mapped = [];
        foreach ($values as $key => $value) {
            if (is_scalar($value)) {
                $mapped[(string)$key] = (string)$value;
            }
        }

        return $mapped;
    }
}
