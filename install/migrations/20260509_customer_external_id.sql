ALTER TABLE cc_card ADD COLUMN external_id VARCHAR(128) DEFAULT NULL;
CREATE UNIQUE INDEX uq_cc_card_external_id ON cc_card (external_id);
