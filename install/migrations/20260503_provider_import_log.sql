CREATE TABLE IF NOT EXISTS cc_provider_import_log (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
