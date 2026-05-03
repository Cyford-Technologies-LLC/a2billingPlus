CREATE TABLE IF NOT EXISTS cc_sms_message (
    id BIGINT NOT NULL AUTO_INCREMENT,
    customer_id BIGINT NOT NULL,
    from_number VARCHAR(32) NOT NULL DEFAULT '',
    to_number VARCHAR(32) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    direction ENUM('outbound','inbound') NOT NULL DEFAULT 'outbound',
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    gateway_message_id VARCHAR(128) NOT NULL DEFAULT '',
    error_message VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sms_message_customer_id (customer_id),
    KEY idx_sms_message_from_number (from_number),
    KEY idx_sms_message_to_number (to_number),
    KEY idx_sms_message_status (status),
    KEY idx_sms_message_direction (direction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
