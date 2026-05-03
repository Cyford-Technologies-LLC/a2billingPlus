CREATE TABLE IF NOT EXISTS cc_did_assignment (
    id BIGINT NOT NULL AUTO_INCREMENT,
    customer_id BIGINT NOT NULL,
    did VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    sms_enabled TINYINT(1) NOT NULL DEFAULT 1,
    voice_enabled TINYINT(1) NOT NULL DEFAULT 1,
    provider_reference VARCHAR(128) NOT NULL DEFAULT '',
    assigned_at DATETIME NOT NULL,
    released_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_did_assignment_did_status (did, status),
    KEY idx_did_assignment_customer_id (customer_id),
    KEY idx_did_assignment_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
