-- PJSIP realtime: ps_contacts table
-- Asterisk writes SIP registration contact URIs here.
-- Without this table, endpoint contacts cannot be bound and calls have no audio.
CREATE TABLE IF NOT EXISTS ps_contacts (
    id                  VARCHAR(255) NOT NULL,
    uri                 VARCHAR(255) NOT NULL,
    expiration_time     BIGINT UNSIGNED,
    qualify_frequency   INT UNSIGNED DEFAULT 0,
    qualify_timeout     FLOAT DEFAULT 3.0,
    reg_server          VARCHAR(255),
    authenticate_qualify ENUM('yes','no') DEFAULT 'no',
    outbound_proxy      VARCHAR(255),
    path                TEXT,
    user_agent          VARCHAR(255),
    endpoint            VARCHAR(255),
    prune_on_boot       ENUM('yes','no') DEFAULT 'no',
    via_addr            VARCHAR(40),
    via_port            INT,
    call_id             VARCHAR(255),
    PRIMARY KEY (id),
    KEY endpoint_idx (endpoint),
    KEY expiration_time_idx (expiration_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
