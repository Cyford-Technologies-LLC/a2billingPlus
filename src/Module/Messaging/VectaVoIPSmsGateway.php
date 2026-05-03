<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Messaging;

final class VectaVoIPSmsGateway implements SmsGatewayInterface
{
    private const SMS_PATH = '/v1/sms/send';

    /**
     * @param null|callable(string, string, array<string,string>): array{status:int,body:string} $transport
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private $transport = null
    ) {
    }

    public function send(string $from, string $to, string $body): SmsResult
    {
        $url = rtrim($this->baseUrl, '/') . self::SMS_PATH;
        $payload = json_encode(['from' => $from, 'to' => $to, 'body' => $body], JSON_THROW_ON_ERROR);

        try {
            $response = $this->post($url, $payload);
        } catch (\Throwable $exception) {
            return new SmsResult(false, 'SMS delivery failed: ' . $exception->getMessage());
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return new SmsResult(false, 'VectaVoIP SMS API returned invalid JSON.');
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $msg = is_string($decoded['message'] ?? null) ? $decoded['message'] : 'SMS rejected by provider.';
            return new SmsResult(false, $msg);
        }

        $messageId = is_string($decoded['message_id'] ?? null) ? $decoded['message_id'] : '';

        return new SmsResult(true, 'SMS queued for delivery.', $messageId);
    }

    /**
     * @return array{status:int,body:string}
     */
    private function post(string $url, string $json): array
    {
        if (is_callable($this->transport)) {
            return ($this->transport)($url, $json, $this->authHeaders());
        }

        if (function_exists('curl_init')) {
            return $this->postWithCurl($url, $json);
        }

        return $this->postWithStreams($url, $json);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-VectaVoIP-Api-Key' => $this->apiKey,
            'X-VectaVoIP-Api-Secret' => $this->apiSecret,
        ];
    }

    /**
     * @return array{status:int,body:string}
     */
    private function postWithCurl(string $url, string $json): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Could not initialize curl.');
        }

        $headers = [];
        foreach ($this->authHeaders() as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
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
     * @return array{status:int,body:string}
     */
    private function postWithStreams(string $url, string $json): array
    {
        $headerLines = '';
        foreach ($this->authHeaders() as $name => $value) {
            $headerLines .= "{$name}: {$value}\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerLines,
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
}
