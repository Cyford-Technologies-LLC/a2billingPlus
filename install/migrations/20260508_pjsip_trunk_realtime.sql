CREATE TABLE IF NOT EXISTS ps_endpoint_id_ips (
    id VARCHAR(80) NOT NULL,
    endpoint VARCHAR(80) NOT NULL DEFAULT '',
    `match` VARCHAR(255) NOT NULL DEFAULT '',
    srv_lookups VARCHAR(3) NOT NULL DEFAULT 'yes',
    match_header VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ps_registrations (
    id VARCHAR(80) NOT NULL,
    transport VARCHAR(80) NOT NULL DEFAULT 'transport-udp',
    outbound_auth VARCHAR(80) NOT NULL DEFAULT '',
    server_uri VARCHAR(255) NOT NULL DEFAULT '',
    client_uri VARCHAR(255) NOT NULL DEFAULT '',
    contact_user VARCHAR(80) NOT NULL DEFAULT '',
    endpoint VARCHAR(80) NOT NULL DEFAULT '',
    expiration INT NOT NULL DEFAULT 3600,
    retry_interval INT NOT NULL DEFAULT 60,
    forbidden_retry_interval INT NOT NULL DEFAULT 600,
    fatal_retry_interval INT NOT NULL DEFAULT 600,
    max_retries INT NOT NULL DEFAULT 10000,
    outbound_proxy VARCHAR(255) NOT NULL DEFAULT '',
    support_path VARCHAR(3) NOT NULL DEFAULT 'no',
    line VARCHAR(3) NOT NULL DEFAULT 'no',
    auth_rejection_permanent VARCHAR(3) NOT NULL DEFAULT 'no',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
