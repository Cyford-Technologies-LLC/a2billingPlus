<?php

declare(strict_types=1);

use A2BillingPlus\Module\Messaging\PhoneTextInstaller;
use A2BillingPlus\Module\Messaging\VoipTextingInstaller;
use PHPUnit\Framework\TestCase;

final class PhoneTextInstallerTest extends TestCase
{
    public function testVoipTextingInstallerShimDelegatesToPhoneTextInstallerContract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->exactly(3))->method('exec');

        $result = (new VoipTextingInstaller($pdo))->install();

        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['steps']);
    }

    public function testPhoneTextInstallerUninstallDropsOnlyMessageAndAssignmentTables(): void
    {
        $statements = [];
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->exactly(2))
            ->method('exec')
            ->willReturnCallback(static function (string $sql) use (&$statements): int|false {
                $statements[] = $sql;
                return 0;
            });

        $result = (new PhoneTextInstaller($pdo))->uninstall();

        $this->assertTrue($result['success']);
        $this->assertSame('DROP TABLE IF EXISTS cc_sms_message', $statements[0]);
        $this->assertSame('DROP TABLE IF EXISTS cc_did_assignment', $statements[1]);
    }
}
