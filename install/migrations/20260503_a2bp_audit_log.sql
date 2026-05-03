CREATE TABLE IF NOT EXISTS cc_a2bp_audit_log (
    id BIGINT NOT NULL AUTO_INCREMENT,
    actor VARCHAR(128) NOT NULL,
    action VARCHAR(128) NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id VARCHAR(128) NOT NULL,
    metadata_json JSON NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_a2bp_audit_entity_created (entity_type, entity_id, created_at),
    KEY idx_a2bp_audit_actor_created (actor, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
