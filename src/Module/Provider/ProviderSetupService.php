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
     * @return list<array{code:string,name:string,support_email:string,api_base_url:string}>
     */
    public function providers(): array
    {
        $response = $this->controller->handle(new JsonRequest('GET'));
        $payload = $response->getPayload();
        $providers = $payload['providers'] ?? [];
        return is_array($providers) ? $providers : [];
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
    public function testConnection(array $input): array
    {
        return $this->post([
            'action' => 'test_connection',
            'provider' => $input['provider'],
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'api_version' => $input['api_version'] ?? '',
            'account_sid' => $input['account_sid'] ?? '',
            'byoc_trunk_sid' => $input['twilio_byoc_trunk_sid'] ?? '',
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
            'provider' => $input['provider'] ?? 'vectavoip',
            'base_url' => $input['base_url'],
            'install_key' => $input['install_key'],
            'registration_username' => $input['registration_username'] ?? '',
            'registration_password' => $input['registration_password'] ?? '',
            'company_name' => $input['company_name'],
            'company_domain' => $input['company_domain'],
            'contact_email' => $input['contact_email'],
            'app_name' => 'A2BillingPlus',
            'app_version' => '0.1.0-alpha',
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function didwwInventorySnapshot(array $input): array
    {
        return $this->post([
            'action' => 'didww_inventory_snapshot',
            'provider' => 'didww',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'api_version' => $input['api_version'] ?? '',
            'page_size' => $input['didww_page_size'] ?? '25',
            'orders_page_size' => $input['didww_orders_page_size'] ?? '10',
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function didwwSearchAvailableDids(array $input): array
    {
        return $this->post([
            'action' => 'didww_search_available_dids',
            'provider' => 'didww',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'api_version' => $input['api_version'] ?? '',
            'page_size' => $input['didww_search_page_size'] ?? '20',
            'filter[number_contains]' => $input['didww_number_contains'] ?? '',
            'filter[country.id]' => $input['didww_country_id'] ?? '',
            'filter[region.id]' => $input['didww_region_id'] ?? '',
            'filter[city.id]' => $input['didww_city_id'] ?? '',
            'filter[did_group.features]' => $input['didww_features'] ?? '',
            'filter[did_group.needs_registration]' => $input['didww_needs_registration'] ?? '',
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function didwwOrderDid(array $input): array
    {
        return $this->post([
            'action' => 'didww_order_did',
            'provider' => 'didww',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'api_version' => $input['api_version'] ?? '',
            'available_did_id' => $input['didww_available_did_id'] ?? '',
            'sku_id' => $input['didww_sku_id'] ?? '',
            'callback_url' => $input['didww_order_callback_url'] ?? '',
            'allow_back_ordering' => $input['didww_allow_back_ordering'] ?? '',
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function didwwCreateInboundTrunk(array $input): array
    {
        return $this->post([
            'action' => 'didww_create_inbound_trunk',
            'provider' => 'didww',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'api_version' => $input['api_version'] ?? '',
            'trunk_name' => $input['didww_trunk_name'] ?? '',
            'trunk_host' => $input['didww_trunk_host'] ?? '',
            'trunk_username' => $input['didww_trunk_username'] ?? '',
            'trunk_auth_enabled' => $input['didww_trunk_auth_enabled'] ?? '',
            'trunk_auth_user' => $input['didww_trunk_auth_user'] ?? '',
            'trunk_auth_password' => $input['didww_trunk_auth_password'] ?? '',
            'trunk_capacity_limit' => $input['didww_trunk_capacity_limit'] ?? '10',
            'trunk_priority' => $input['didww_trunk_priority'] ?? '10',
            'trunk_weight' => $input['didww_trunk_weight'] ?? '10',
            'trunk_cli_format' => $input['didww_trunk_cli_format'] ?? 'e164',
            'trunk_cli_prefix' => $input['didww_trunk_cli_prefix'] ?? '',
            'trunk_resolve_ruri' => $input['didww_trunk_resolve_ruri'] ?? '1',
            'trunk_enabled_sip_registration' => $input['didww_trunk_enabled_sip_registration'] ?? '',
            'trunk_use_did_in_ruri' => $input['didww_trunk_use_did_in_ruri'] ?? '1',
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function didwwSyncInventory(array $input): array
    {
        return $this->post([
            'action' => 'didww_sync_inventory',
            'provider' => 'didww',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'api_version' => $input['api_version'] ?? '',
            'page_size' => $input['didww_sync_page_size'] ?? '100',
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function didwwSyncCompletedOrders(array $input): array
    {
        return $this->post([
            'action' => 'didww_sync_completed_orders',
            'provider' => 'didww',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'api_version' => $input['api_version'] ?? '',
            'orders_page_size' => $input['didww_orders_page_size'] ?? '25',
            'page_size' => $input['didww_sync_page_size'] ?? '100',
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function twilioInventorySnapshot(array $input): array
    {
        return $this->post([
            'action' => 'twilio_inventory_snapshot',
            'provider' => 'twilio',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'account_sid' => $input['account_sid'] ?? '',
            'byoc_trunk_sid' => $input['twilio_byoc_trunk_sid'] ?? '',
            'page_size' => $input['twilio_page_size'] ?? '25',
            'trunks_page_size' => $input['twilio_trunks_page_size'] ?? '25',
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function twilioSearchAvailableNumbers(array $input): array
    {
        return $this->post([
            'action' => 'twilio_search_available_numbers',
            'provider' => 'twilio',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'account_sid' => $input['account_sid'] ?? '',
            'country_code' => $input['twilio_country_code'] ?? 'US',
            'contains' => $input['twilio_contains'] ?? '',
            'area_code' => $input['twilio_area_code'] ?? '',
            'sms_enabled' => $input['twilio_sms_enabled'] ?? '',
            'voice_enabled' => $input['twilio_voice_enabled'] ?? '',
            'page_size' => $input['twilio_search_page_size'] ?? '20',
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function twilioPurchaseNumber(array $input): array
    {
        return $this->post([
            'action' => 'twilio_purchase_number',
            'provider' => 'twilio',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'account_sid' => $input['account_sid'] ?? '',
            'byoc_trunk_sid' => $input['twilio_byoc_trunk_sid'] ?? '',
            'phone_number' => $input['twilio_phone_number'] ?? '',
            'voice_url' => $input['twilio_voice_url'] ?? '',
            'sms_url' => $input['twilio_sms_url'] ?? '',
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function twilioCreateTrunk(array $input): array
    {
        return $this->post([
            'action' => 'twilio_create_trunk',
            'provider' => 'twilio',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'account_sid' => $input['account_sid'] ?? '',
            'byoc_trunk_sid' => $input['twilio_byoc_trunk_sid'] ?? '',
            'friendly_name' => $input['twilio_trunk_friendly_name'] ?? '',
            'domain_name' => $input['twilio_trunk_domain_name'] ?? '',
            'cnam_lookup_enabled' => $input['twilio_trunk_cnam_lookup_enabled'] ?? '',
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function twilioRegisterExistingTrunk(array $input): array
    {
        return $this->post([
            'action' => 'twilio_register_existing_trunk',
            'provider' => 'twilio',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'account_sid' => $input['account_sid'] ?? '',
            'byoc_trunk_sid' => $input['twilio_byoc_trunk_sid'] ?? '',
        ]);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function twilioSyncInventory(array $input): array
    {
        return $this->post([
            'action' => 'twilio_sync_inventory',
            'provider' => 'twilio',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'account_sid' => $input['account_sid'] ?? '',
            'byoc_trunk_sid' => $input['twilio_byoc_trunk_sid'] ?? '',
            'page_size' => $input['twilio_sync_page_size'] ?? '100',
            'trunks_page_size' => $input['twilio_trunks_page_size'] ?? '100',
            'trunk_numbers_page_size' => $input['twilio_trunk_numbers_page_size'] ?? '100',
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
        if (!empty($input['target_trunk_id'])) {
            $body['target_trunk_id'] = $input['target_trunk_id'];
        }
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
        if (($input['prefix_filter'] ?? '') !== '') {
            $filters['prefix'] = $input['prefix_filter'];
        }
        if (($input['country_filter'] ?? '') !== '') {
            $filters['countries'] = $input['country_filter'];
        }
        if (($input['markup_percent'] ?? '') !== '') {
            $filters['markup_percent'] = $input['markup_percent'];
        }

        return [
            'action' => $action,
            'provider' => $input['provider'] ?? 'vectavoip',
            'base_url' => $input['base_url'],
            'api_key' => $input['api_key'],
            'api_secret' => $input['api_secret'],
            'api_version' => $input['api_version'] ?? '',
            'account_sid' => $input['account_sid'] ?? '',
            'byoc_trunk_sid' => $input['twilio_byoc_trunk_sid'] ?? '',
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
