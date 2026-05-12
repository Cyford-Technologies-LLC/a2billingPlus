<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

use A2BillingPlus\Module\Provider\ProviderCredentials;
use A2BillingPlus\Module\Provider\RateImporterInterface;
use A2BillingPlus\Module\Provider\RateImportPreview;
use A2BillingPlus\Module\Provider\RateImportRequest;
use A2BillingPlus\Module\Provider\RateImportResult;

final class VectaVoIPRateImporter implements RateImporterInterface
{
    public const PREVIEW_PATH = '/v1/rates/preview';

    private readonly ProviderCredentials $credentials;

    /**
     * @param null|callable(string, ProviderCredentials): array{status:int, body:string} $transport
     */
    public function __construct(
        ProviderCredentials $credentials,
        private $transport = null
    ) {
        $this->credentials = new ProviderCredentials(
            VectaVoIPConnector::API_BASE_URL,
            $credentials->getApiKey(),
            $credentials->getApiSecret(),
            $credentials->getMetadata()
        );
    }

    public function preview(RateImportRequest $request): RateImportPreview
    {
        try {
            $response = $this->getJson($this->previewUrl($request));
        } catch (\Throwable $exception) {
            return new RateImportPreview(0, [], 'VectaVoIP rate preview failed: ' . $exception->getMessage());
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return new RateImportPreview(0, [], 'VectaVoIP rate preview returned invalid JSON.');
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            return new RateImportPreview(
                0,
                [],
                $this->stringValue($decoded, 'message', 'VectaVoIP rate preview was rejected.')
            );
        }

        $sampleRows = $decoded['sample_rows'] ?? [];
        if (!is_array($sampleRows)) {
            $sampleRows = [];
        }

        return new RateImportPreview(
            $this->intValue($decoded, 'total_rows'),
            $this->rows($sampleRows),
            $this->stringValue($decoded, 'message', 'VectaVoIP rate preview completed.')
        );
    }

    public function import(RateImportRequest $request): RateImportResult
    {
        if ($request->isDryRun()) {
            return new RateImportResult(true, 0, 0, 'Dry run completed. No rates were imported.');
        }

        return new RateImportResult(false, 0, 0, 'VectaVoIP API rate import is not implemented yet.');
    }

    public function getCredentials(): ProviderCredentials
    {
        return $this->credentials;
    }

    private function previewUrl(RateImportRequest $request): string
    {
        $query = array_merge([
            'rate_deck' => $request->getRateDeck(),
            'currency' => $request->getTargetCurrency(),
        ], $request->getFilters());

        return $this->credentials->getBaseUrl() . self::PREVIEW_PATH . '?' . http_build_query($query);
    }

    /**
     * @return array{status:int, body:string}
     */
    private function getJson(string $url): array
    {
        if (is_callable($this->transport)) {
            return ($this->transport)($url, $this->credentials);
        }

        if (function_exists('curl_init')) {
            return $this->getWithCurl($url);
        }

        return $this->getWithStreams($url);
    }

    /**
     * @return array{status:int, body:string}
     */
    private function getWithCurl(string $url): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Could not initialize curl.');
        }

        curl_setopt_array($handle, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $this->headers(),
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
    private function getWithStreams(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $this->headers()) . "\r\n",
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
    private function headers(): array
    {
        $headers = ['Accept: application/json'];
        if ($this->credentials->getApiKey() !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->credentials->getApiKey();
        }
        if ($this->credentials->getApiSecret() !== '') {
            $headers[] = 'X-VectaVoIP-Secret: ' . $this->credentials->getApiSecret();
        }

        return $headers;
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
     * @param array<string, mixed> $values
     */
    private function intValue(array $values, string $key): int
    {
        $value = $values[$key] ?? 0;
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * @param array<int|string, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }
}
