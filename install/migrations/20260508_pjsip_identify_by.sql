ALTER TABLE ps_endpoints
    ADD COLUMN identify_by VARCHAR(80) NOT NULL DEFAULT 'username,ip'
    AFTER context;

UPDATE ps_endpoints
SET identify_by = 'auth_username,username'
WHERE id LIKE 'cust-%';

UPDATE ps_endpoints
SET identify_by = 'username,ip'
WHERE id LIKE 'trunk-%' AND (identify_by = '' OR identify_by IS NULL OR identify_by = 'username,ip');
