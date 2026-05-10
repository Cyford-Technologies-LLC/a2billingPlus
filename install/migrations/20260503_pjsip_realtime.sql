CREATE TABLE IF NOT EXISTS ps_auths (
    id VARCHAR(80) NOT NULL,
    auth_type VARCHAR(20) NOT NULL DEFAULT 'userpass',
    username VARCHAR(80) NOT NULL DEFAULT '',
    password VARCHAR(120) NOT NULL DEFAULT '',
    realm VARCHAR(255) DEFAULT NULL,
    md5_cred VARCHAR(40) DEFAULT NULL,
    nonce_lifetime INT DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ps_aors (
    id VARCHAR(80) NOT NULL,
    max_contacts INT NOT NULL DEFAULT 1,
    remove_existing VARCHAR(3) NOT NULL DEFAULT 'yes',
    contact VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ps_endpoints (
    id VARCHAR(80) NOT NULL,
    transport VARCHAR(80) NOT NULL DEFAULT 'transport-udp',
    aors VARCHAR(80) NOT NULL DEFAULT '',
    auth VARCHAR(80) NOT NULL DEFAULT '',
    context VARCHAR(80) NOT NULL DEFAULT 'a2billing',
    identify_by VARCHAR(80) NOT NULL DEFAULT 'username,ip',
    disallow VARCHAR(100) NOT NULL DEFAULT 'all',
    allow VARCHAR(100) NOT NULL DEFAULT 'ulaw,alaw',
    direct_media VARCHAR(3) NOT NULL DEFAULT 'no',
    rtp_symmetric VARCHAR(3) NOT NULL DEFAULT 'yes',
    force_rport VARCHAR(3) NOT NULL DEFAULT 'yes',
    rewrite_contact VARCHAR(3) NOT NULL DEFAULT 'yes',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS cc_a2bp_pjsip_endpoint_map (
    endpoint_id VARCHAR(80) NOT NULL,
    endpoint_type VARCHAR(32) NOT NULL,
    owner_id BIGINT NOT NULL DEFAULT 0,
    label VARCHAR(120) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (endpoint_id),
    KEY idx_a2bp_pjsip_owner (endpoint_type, owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
