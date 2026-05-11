UPDATE ps_endpoints e
INNER JOIN ps_aors a ON a.id = e.aors
LEFT JOIN cc_a2bp_pjsip_endpoint_map m ON m.endpoint_id = e.id
SET e.from_domain = SUBSTRING_INDEX(
    SUBSTRING_INDEX(
        SUBSTRING_INDEX(
            REPLACE(REPLACE(SUBSTRING_INDEX(a.contact, '?', 1), 'sips:', ''), 'sip:', ''),
            '/',
            1
        ),
        '@',
        -1
    ),
    ':',
    1
)
WHERE (e.id LIKE 'trunk-%' OR m.endpoint_type = 'trunk')
  AND COALESCE(e.from_domain, '') = ''
  AND COALESCE(a.contact, '') <> '';
