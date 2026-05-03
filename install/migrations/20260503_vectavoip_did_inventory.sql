CREATE TABLE IF NOT EXISTS cc_vectavoip_did_inventory (
    id BIGINT NOT NULL AUTO_INCREMENT,
    did VARCHAR(64) NOT NULL,
    country VARCHAR(64) NOT NULL DEFAULT '',
    region VARCHAR(64) NOT NULL DEFAULT '',
    monthly_rate DECIMAL(15,5) NOT NULL DEFAULT 0.00000,
    setup_rate DECIMAL(15,5) NOT NULL DEFAULT 0.00000,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status VARCHAR(32) NOT NULL DEFAULT 'available',
    provider_reference VARCHAR(128) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_vectavoip_did_inventory_did (did),
    KEY idx_vectavoip_did_inventory_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
