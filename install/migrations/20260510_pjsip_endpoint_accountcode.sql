ALTER TABLE ps_endpoints
    ADD COLUMN IF NOT EXISTS accountcode VARCHAR(80) NOT NULL DEFAULT ''
    AFTER auth;

UPDATE ps_endpoints e
INNER JOIN cc_a2bp_pjsip_endpoint_map m ON m.endpoint_id = e.id
INNER JOIN cc_card c ON c.id = m.owner_id
SET e.accountcode = c.username
WHERE m.endpoint_type = 'customer_device'
  AND COALESCE(e.accountcode, '') = '';
