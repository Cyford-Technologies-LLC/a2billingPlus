<?php

declare(strict_types=1);

use A2BillingPlus\Module\Admin\AdminSettingsRepository;
use A2BillingPlus\Module\Admin\AdminSettingsService;
use A2BillingPlus\Module\Security\AuditLogRepository;
use PHPUnit\Framework\TestCase;

final class AdminSettingsServiceTest extends TestCase
{
    public function testListsOnlySafeSettings(): void
    {
        $settings = $this->service($this->pdo())->list();
        $keys = array_column($settings, 'config_key');

        $this->assertContains('admin_email', $keys);
        $this->assertNotContains('manager_secret', $keys);
    }

    public function testUpdatesAllowedSettingAndAudits(): void
    {
        $pdo = $this->pdo();
        $result = $this->service($pdo)->update('base_currency', 'USD', 'admin:root');

        $this->assertSame(200, $result['status']);
        $this->assertSame('usd', $result['body']['setting']['config_value']);
        $this->assertSame('admin_setting.update', $pdo->query('SELECT action FROM cc_a2bp_audit_log')->fetchColumn());
    }

    public function testRejectsForbiddenAndInvalidSettings(): void
    {
        $service = $this->service($this->pdo());

        $forbidden = $service->update('manager_secret', 'new-secret', 'admin:root');
        $invalid = $service->update('admin_email', 'not-an-email', 'admin:root');

        $this->assertSame(403, $forbidden['status']);
        $this->assertSame(422, $invalid['status']);
    }

    private function service(PDO $pdo): AdminSettingsService
    {
        return new AdminSettingsService(new AdminSettingsRepository($pdo), new AuditLogRepository($pdo));
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE cc_config (id INTEGER PRIMARY KEY, config_title TEXT, config_key TEXT, config_value TEXT, config_description TEXT, config_valuetype INTEGER, config_listvalues TEXT, config_group_title TEXT)');
        $pdo->exec("INSERT INTO cc_config (id, config_title, config_key, config_value, config_description, config_valuetype, config_group_title) VALUES (1, 'Admin Email', 'admin_email', 'root@example.test', 'Admin email', 0, 'global')");
        $pdo->exec("INSERT INTO cc_config (id, config_title, config_key, config_value, config_description, config_valuetype, config_group_title) VALUES (2, 'Base Currency', 'base_currency', 'usd', 'Base currency', 0, 'global')");
        $pdo->exec("INSERT INTO cc_config (id, config_title, config_key, config_value, config_description, config_valuetype, config_group_title) VALUES (3, 'Manager Password', 'manager_secret', 'mycode', 'Manager password', 0, 'global')");

        return $pdo;
    }
}
