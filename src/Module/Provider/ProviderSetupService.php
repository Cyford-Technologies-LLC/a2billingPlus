<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

use A2BillingPlus\Api\ProviderApiController;
use A2BillingPlus\Http\JsonRequest;

final class ProviderSetupService
{
    /**
     * @param callable(): \PDO $pdoFactory
     */
    public function __construct(
        private readonly ProviderApiController $controller,
        private $pdoFactory
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function providerStatus(string $provider = 'vectavoip'): array
    {
        return $this->post([
            'action' => 'provider_status',
            'provider' => $provider,
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function registerInstall(array $input): array
    {
        return $this->post([
            'action' => 'register_install',
            'provider' => 'vectavoip',
            'base_url' => $input['base_url'],
            'install_key' => $input['install_key'],
            'company_name' => $input['company_name'],
            'company_domain' => $input['company_domain'],
            'contact_name' => $input['contact_name'],
            'contact_email' => $input['contact_email'],
            'contact_phone' => $input['contact_phone'],
            'details' => $input['details'],
            'app_name' => 'A2BillingPlus',
            'app_version' => '0.1.0-alpha',
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function previewRates(array $input): array
    {
        return $this->post($this->providerRateRequestBody('preview_rates', $input));
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function importPreviewRates(array $input, bool $dryRun): array
    {
        $body = $this->providerRateRequestBody('import_preview_rates', $input);
        $body['target_ratecard_id'] = $input['target_ratecard_id'];
        $body['dry_run'] = $dryRun ? '1' : '0';
        $body['update_existing'] = $input['update_existing'] === '1' ? '1' : '0';

        return $this->post($body);
    }

    /**
     * @return list<array{id:string, name:string}>
     */
    public function ratecards(): array
    {
        try {
            $statement = $this->pdo()->query('SELECT id, tariffname FROM cc_tariffplan ORDER BY tariffname ASC');
            if (!$statement) {
                return [];
            }

            $ratecards = [];
            foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $ratecards[] = [
                    'id' => (string)($row['id'] ?? ''),
                    'name' => (string)($row['tariffname'] ?? ''),
                ];
            }

            return $ratecards;
        } catch (\Throwable $exception) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentImports(int $limit = 10): array
    {
        try {
            return (new ProviderImportLogRepository($this->pdo()))->recent($limit);
        } catch (\Throwable $exception) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function post(array $body): array
    {
        $response = $this->controller->handle(new JsonRequest('POST', [], $body));
        $payload = $response->getPayload();
        $payload['http_status'] = $response->getStatusCode();

        if ($response->getStatusCode() >= 400) {
            $payload['error'] = (string)($payload['message'] ?? $payload['error'] ?? 'Provider request failed.');
        }

        return $payload;
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    private function providerRateRequestBody(string $action, array $input): array
    {
        $filters = [];
        if ($input['destination_filter'] !== '') {
            $filters['destination'] = $input['destination_filter'];
        }

        return [
            'action' => $action,
            'provider' => 'vectavoip',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'rate_deck' => $input['rate_deck'],
            'currency' => $input['currency'],
            'filters' => $filters,
        ];
    }

    private function pdo(): \PDO
    {
        return ($this->pdoFactory)();
    }
}
