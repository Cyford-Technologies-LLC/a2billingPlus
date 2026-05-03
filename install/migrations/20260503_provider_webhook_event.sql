CREATE TABLE IF NOT EXISTS cc_provider_webhook_event (
    id BIGINT NOT NULL AUTO_INCREMENT,
    provider VARCHAR(64) NOT NULL,
    event_type VARCHAR(128) NOT NULL,
    event_id VARCHAR(128) NOT NULL,
    payload_json TEXT NOT NULL,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    message VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_provider_webhook_event (provider, event_id),
    KEY idx_provider_webhook_event_type_created (provider, event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
