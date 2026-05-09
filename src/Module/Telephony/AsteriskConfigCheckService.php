<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

final class AsteriskConfigCheckService
{
    private const SUPPORTED_MAJOR_VERSIONS = [20, 22];

    public function __construct(private readonly ?\PDO $pdo = null)
    {
    }

    /**
     * @param array<string, string> $settings
     * @return array{success:bool,checks:list<array{name:string,success:bool,message:string}>}
     */
    public function check(array $settings): array
    {
        $checks = [
            $this->checkVersion($settings['version'] ?? ''),
            $this->checkCredential('AMI', $settings['ami_user'] ?? '', $settings['ami_password'] ?? ''),
            $this->checkCredential('ARI', $settings['ari_user'] ?? '', $settings['ari_password'] ?? ''),
            $this->checkPjsipMode($settings['channel_driver'] ?? 'pjsip'),
            $this->checkRealtime($settings['realtime_enabled'] ?? 'yes'),
        ];
        if (in_array(strtolower($settings['probe_runtime'] ?? 'no'), ['1', 'yes', 'true', 'on'], true)) {
            $checks = array_merge($checks, [
                $this->checkAmiRuntime(
                    $settings['ami_host'] ?? 'asterisk',
                    $settings['ami_port'] ?? '5038',
                    $settings['ami_user'] ?? '',
                    $settings['ami_password'] ?? ''
                ),
                $this->checkDatabaseConnection(),
                $this->checkRealtimeSchema(),
                $this->checkPjsipRealtimeRows(),
                $this->checkProvisioningSources(),
            ]);
        }

        return [
            'success' => count(array_filter($checks, static fn (array $check): bool => !$check['success'])) === 0,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{name:string,success:bool,message:string}
     */
    private function checkVersion(string $version): array
    {
        if (!preg_match('/^(\d+)(?:\.|$)/', $version, $matches)) {
            return [
                'name' => 'asterisk_version',
                'success' => false,
                'message' => 'Asterisk version could not be parsed.',
            ];
        }

        $major = (int)$matches[1];
        if (!in_array($major, self::SUPPORTED_MAJOR_VERSIONS, true)) {
            return [
                'name' => 'asterisk_version',
                'success' => false,
                'message' => 'Use Asterisk 20 LTS or 22 LTS for A2BillingPlus launch installs.',
            ];
        }

        return [
            'name' => 'asterisk_version',
            'success' => true,
            'message' => sprintf('Asterisk %d is supported.', $major),
        ];
    }

    /**
     * @return array{name:string,success:bool,message:string}
     */
    private function checkCredential(string $name, string $user, string $password): array
    {
        $defaultPasswords = ['a2billing', 'a2billing-ami', 'a2billing-ari', 'changepassword', 'password'];
        $success = trim($user) !== '' && strlen($password) >= 16 && !in_array($password, $defaultPasswords, true);

        return [
            'name' => strtolower($name) . '_credentials',
            'success' => $success,
            'message' => $success
                ? $name . ' credentials are set.'
                : $name . ' credentials must use a non-default password of at least 16 characters.',
        ];
    }

    /**
     * @return array{name:string,success:bool,message:string}
     */
    private function checkPjsipMode(string $driver): array
    {
        $success = strtolower($driver) === 'pjsip';

        return [
            'name' => 'channel_driver',
            'success' => $success,
            'message' => $success
                ? 'PJSIP is selected.'
                : 'Use PJSIP for new installs; chan_sip is legacy-only.',
        ];
    }

    /**
     * @return array{name:string,success:bool,message:string}
     */
    private function checkRealtime(string $enabled): array
    {
        $success = in_array(strtolower($enabled), ['1', 'yes', 'true', 'on'], true);

        return [
            'name' => 'asterisk_realtime',
            'success' => $success,
            'message' => $success
                ? 'Asterisk Realtime is enabled for database-backed routing data.'
                : 'Enable Asterisk Realtime before scaling beyond a single static sandbox.',
        ];
    }

    /**
     * @return array{name:string,success:bool,message:string}
     */
    private function checkAmiRuntime(string $host, string $port, string $user, string $password): array
    {
        if (trim($user) === '' || trim($password) === '') {
            return [
                'name' => 'ami_runtime',
                'success' => false,
                'message' => 'AMI runtime probe skipped because credentials are missing.',
            ];
        }

        $socket = @fsockopen($host !== '' ? $host : 'asterisk', max(1, (int) $port), $errno, $errstr, 3.0);
        if (!is_resource($socket)) {
            return [
                'name' => 'ami_runtime',
                'success' => false,
                'message' => sprintf('AMI socket failed: %s (%d).', $errstr !== '' ? $errstr : 'connection failed', (int) $errno),
            ];
        }

        stream_set_timeout($socket, 3);
        fgets($socket);

        fwrite($socket, "Action: Login\r\nUsername: {$user}\r\nSecret: {$password}\r\nEvents: off\r\n\r\n");
        $loginResponse = $this->readAmiResponse($socket);
        if (stripos($loginResponse, 'Response: Success') === false) {
            fclose($socket);
            return [
                'name' => 'ami_runtime',
                'success' => false,
                'message' => 'AMI login failed.',
            ];
        }

        fwrite($socket, "Action: Ping\r\n\r\n");
        $pingResponse = $this->readAmiResponse($socket);
        fwrite($socket, "Action: Logoff\r\n\r\n");
        fclose($socket);

        return [
            'name' => 'ami_runtime',
            'success' => stripos($pingResponse, 'Response: Success') !== false,
            'message' => stripos($pingResponse, 'Response: Success') !== false
                ? 'AMI accepted login and responded to Ping.'
                : 'AMI login succeeded but Ping did not return success.',
        ];
    }

    /**
     * @return array{name:string,success:bool,message:string}
     */
    private function checkDatabaseConnection(): array
    {
        if ($this->pdo === null) {
            return [
                'name' => 'database_connection',
                'success' => false,
                'message' => 'Database probe is unavailable in this context.',
            ];
        }

        try {
            $result = $this->pdo->query('SELECT 1');
            $success = $result !== false && (string) $result->fetchColumn() === '1';
        } catch (\Throwable) {
            $success = false;
        }

        return [
            'name' => 'database_connection',
            'success' => $success,
            'message' => $success
                ? 'Database connection succeeded.'
                : 'Database probe failed.',
        ];
    }

    /**
     * @return array{name:string,success:bool,message:string}
     */
    private function checkRealtimeSchema(): array
    {
        $tables = ['ps_endpoints', 'ps_auths', 'ps_aors', 'ps_endpoint_id_ips', 'cc_a2bp_pjsip_endpoint_map'];
        if ($this->pdo === null) {
            return [
                'name' => 'realtime_schema',
                'success' => false,
                'message' => 'Realtime schema probe is unavailable in this context.',
            ];
        }

        $missing = [];
        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                $missing[] = $table;
            }
        }

        return [
            'name' => 'realtime_schema',
            'success' => $missing === [],
            'message' => $missing === []
                ? 'Realtime schema tables are present.'
                : 'Missing realtime tables: ' . implode(', ', $missing) . '.',
        ];
    }

    /**
     * @return array{name:string,success:bool,message:string}
     */
    private function checkPjsipRealtimeRows(): array
    {
        if ($this->pdo === null || !$this->tableExists('ps_endpoints')) {
            return [
                'name' => 'pjsip_realtime_rows',
                'success' => false,
                'message' => 'PJSIP realtime row probe is unavailable.',
            ];
        }

        try {
            $endpointCount = (int) ($this->pdo->query('SELECT COUNT(*) FROM ps_endpoints')->fetchColumn() ?: 0);
            $aorCount = $this->tableExists('ps_aors')
                ? (int) ($this->pdo->query('SELECT COUNT(*) FROM ps_aors')->fetchColumn() ?: 0)
                : 0;
        } catch (\Throwable) {
            $endpointCount = 0;
            $aorCount = 0;
        }

        $success = $endpointCount > 0 && $aorCount > 0;
        return [
            'name' => 'pjsip_realtime_rows',
            'success' => $success,
            'message' => $success
                ? sprintf('Realtime rows loaded: %d endpoints, %d AORs.', $endpointCount, $aorCount)
                : 'No PJSIP realtime rows are currently provisioned.',
        ];
    }

    /**
     * @return array{name:string,success:bool,message:string}
     */
    private function checkProvisioningSources(): array
    {
        if ($this->pdo === null || !$this->tableExists('cc_sip_buddies')) {
            return [
                'name' => 'sip_provisioning_sources',
                'success' => false,
                'message' => 'SIP provisioning source probe is unavailable.',
            ];
        }

        $sql = "SELECT COUNT(*) FROM cc_sip_buddies WHERE COALESCE(id_cc_card, 0) > 0 AND COALESCE(username, '') <> '' AND COALESCE(secret, '') <> ''";
        try {
            $count = (int) ($this->pdo->query($sql)->fetchColumn() ?: 0);
        } catch (\Throwable) {
            $count = 0;
        }

        return [
            'name' => 'sip_provisioning_sources',
            'success' => $count > 0,
            'message' => $count > 0
                ? sprintf('Provisionable SIP account sources found: %d.', $count)
                : 'No provisionable SIP account rows were found in cc_sip_buddies.',
        ];
    }

    private function readAmiResponse($socket): string
    {
        $buffer = '';
        while (!feof($socket)) {
            $line = fgets($socket);
            if ($line === false) {
                break;
            }
            $buffer .= $line;
            if (rtrim($line, "\r\n") === '') {
                break;
            }
        }

        return $buffer;
    }

    private function tableExists(string $table): bool
    {
        if ($this->pdo === null || preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            return false;
        }

        if ((string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table");
        } else {
            $statement = $this->pdo->prepare('SHOW TABLES LIKE :table');
        }
        $statement->execute([':table' => $table]);

        return $statement->fetchColumn() !== false;
    }
}
