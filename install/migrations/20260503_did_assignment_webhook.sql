-- Add webhook_url to cc_did_assignment so A2BillingPlus knows where to call back
-- when an inbound SMS arrives for a DID.
SET @table_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'cc_did_assignment'
);

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'cc_did_assignment'
      AND column_name = 'webhook_url'
);

SET @ddl := IF(
    @table_exists > 0 AND @column_exists = 0,
    'ALTER TABLE cc_did_assignment ADD COLUMN webhook_url VARCHAR(512) NOT NULL DEFAULT ''''',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
