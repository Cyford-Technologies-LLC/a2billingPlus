<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider;

final class ProviderImportLogRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function ensureTable(): void
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS cc_provider_import_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    provider TEXT NOT NULL,
                    rate_deck TEXT NOT NULL,
                    target_ratecard_id INTEGER NOT NULL,
                    dry_run INTEGER NOT NULL DEFAULT 1,
                    success INTEGER NOT NULL DEFAULT 0,
                    imported_rows INTEGER NOT NULL DEFAULT 0,
                    skipped_rows INTEGER NOT NULL DEFAULT 0,
                    message TEXT NOT NULL,
                    created_at TEXT NOT NULL
                )'
            );
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_provider_import_log (
                id BIGINT NOT NULL AUTO_INCREMENT,
                provider VARCHAR(64) NOT NULL,
                rate_deck VARCHAR(128) NOT NULL,
                target_ratecard_id INT NOT NULL,
                dry_run TINYINT(1) NOT NULL DEFAULT 1,
                success TINYINT(1) NOT NULL DEFAULT 0,
                imported_rows INT NOT NULL DEFAULT 0,
                skipped_rows INT NOT NULL DEFAULT 0,
                message VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_provider_import_log_provider_created (provider, created_at),
                KEY idx_provider_import_log_ratecard (target_ratecard_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function record(
        string $provider,
        string $rateDeck,
        int $targetRatecardId,
        bool $dryRun,
        bool $success,
        int $importedRows,
        int $skippedRows,
        string $message
    ): void {
        $this->ensureTable();

        $statement = $this->pdo->prepare(
            'INSERT INTO cc_provider_import_log (
                provider, rate_deck, target_ratecard_id, dry_run, success,
                imported_rows, skipped_rows, message, created_at
            ) VALUES (
                :provider, :rate_deck, :target_ratecard_id, :dry_run, :success,
                :imported_rows, :skipped_rows, :message, :created_at
            )'
        );

        $statement->execute([
            'provider' => $provider,
            'rate_deck' => $rateDeck,
            'target_ratecard_id' => $targetRatecardId,
            'dry_run' => $dryRun ? 1 : 0,
            'success' => $success ? 1 : 0,
            'imported_rows' => $importedRows,
            'skipped_rows' => $skippedRows,
            'message' => mb_substr($message, 0, 255),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 10): array
    {
        $this->ensureTable();

        $statement = $this->pdo->prepare(
            'SELECT provider, rate_deck, target_ratecard_id, dry_run, success,
                    imported_rows, skipped_rows, message, created_at
             FROM cc_provider_import_log
             ORDER BY id DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', max(1, min(50, $limit)), \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }
}
