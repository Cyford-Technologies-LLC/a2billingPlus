<?php

declare(strict_types=1);

use A2BillingPlus\Api\AdminSettingsController;
use A2BillingPlus\Api\ApiServiceKeyAuthenticator;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class AdminSettingsControllerTest extends TestCase
{
    public function testListsAndLoadsSafeSettings(): void
    {
        $controller = $this->controller($this->pdo());

        $list = $controller->handle(new JsonRequest('GET', [], [], $this->headers()));
        $detail = $controller->handle(new JsonRequest('GET', ['key' => 'admin_email'], [], $this->headers()));

        $this->assertSame(200, $list->getStatusCode());
        $this->assertSame('admin_email', $list->getPayload()['data']['settings'][0]['config_key']);
        $this->assertSame('root@example.test', $detail->getPayload()['data']['setting']['config_value']);
    }

    public function testUpdatesAllowedSettingThroughApi(): void
    {
        $pdo = $this->pdo();
        $response = $this->controller($pdo)->handle(new JsonRequest('PUT', ['key' => 'use_realtime'], [
            'value' => 'no',
        ], $this->headers()));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('0', $response->getPayload()['data']['setting']['config_value']);
        $this->assertSame('admin:root', $pdo->query('SELECT actor FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testRejectsForbiddenSettingWrite(): void
    {
        $response = $this->controller($this->pdo())->handle(new JsonRequest('PUT', ['key' => 'manager_secret'], [
            'value' => 'new-secret',
        ], $this->headers()));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('setting_forbidden', $response->getPayload()['error']['code']);
    }

    public function testRequiresServiceKey(): void
    {
        $response = $this->controller($this->pdo())->handle(new JsonRequest('GET'));

        $this->assertSame(401, $response->getStatusCode());
    }

    private function controller(PDO $pdo): AdminSettingsController
    {
        return new AdminSettingsController(
            new ApiServiceKeyAuthenticator(new AppConfig(['A2BP_API_SERVICE_KEY' => 'secret-key'])),
            fn (): PDO => $pdo
        );
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer secret-key',
            'X-A2BP-Actor' => 'admin:root',
        ];
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE cc_config (id INTEGER PRIMARY KEY, config_title TEXT, config_key TEXT, config_value TEXT, config_description TEXT, config_valuetype INTEGER, config_listvalues TEXT, config_group_title TEXT)');
        $pdo->exec("INSERT INTO cc_config (id, config_title, config_key, config_value, config_description, config_valuetype, config_group_title) VALUES (1, 'Admin Email', 'admin_email', 'root@example.test', 'Admin email', 0, 'global')");
        $pdo->exec("INSERT INTO cc_config (id, config_title, config_key, config_value, config_description, config_valuetype, config_group_title) VALUES (2, 'Use Realtime', 'use_realtime', '1', 'Use realtime', 1, 'global')");
        $pdo->exec("INSERT INTO cc_config (id, config_title, config_key, config_value, config_description, config_valuetype, config_group_title) VALUES (3, 'Manager Password', 'manager_secret', 'mycode', 'Manager password', 0, 'global')");

        return $pdo;
    }
}
