<?php

declare(strict_types=1);

use A2BillingPlus\Module\Provider\ProviderImportLogRepository;
use PHPUnit\Framework\TestCase;

final class ProviderImportLogRepositoryTest extends TestCase
{
    public function testRecordsAndReadsRecentImportLogs(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $repository = new ProviderImportLogRepository($pdo);
        $repository->record('vectavoip', 'retail', 7, true, true, 3, 0, 'Dry run completed.');

        $recent = $repository->recent();

        $this->assertCount(1, $recent);
        $this->assertSame('vectavoip', $recent[0]['provider']);
        $this->assertSame('retail', $recent[0]['rate_deck']);
        $this->assertSame(7, (int)$recent[0]['target_ratecard_id']);
        $this->assertSame(1, (int)$recent[0]['dry_run']);
        $this->assertSame(3, (int)$recent[0]['imported_rows']);
    }
}
