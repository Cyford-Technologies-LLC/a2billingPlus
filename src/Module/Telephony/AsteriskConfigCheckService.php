<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Telephony;

final class AsteriskConfigCheckService
{
    private const SUPPORTED_MAJOR_VERSIONS = [20, 22];

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
}
