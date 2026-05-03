<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Messaging;

/** @deprecated Use PhoneTextInstaller */
final class VoipTextingInstaller
{
    private readonly PhoneTextInstaller $installer;

    public function __construct(\PDO $pdo)
    {
        $this->installer = new PhoneTextInstaller($pdo);
    }

    /**
     * @return array{success:bool, steps:list<string>}
     */
    public function install(): array
    {
        return $this->installer->install();
    }

    /**
     * @return array{success:bool, steps:list<string>}
     */
    public function uninstall(): array
    {
        return $this->installer->uninstall();
    }
}
