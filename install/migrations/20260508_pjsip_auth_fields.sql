ALTER TABLE ps_auths
    ADD COLUMN IF NOT EXISTS realm VARCHAR(255) DEFAULT NULL AFTER password,
    ADD COLUMN IF NOT EXISTS md5_cred VARCHAR(40) DEFAULT NULL AFTER realm,
    ADD COLUMN IF NOT EXISTS nonce_lifetime INT DEFAULT NULL AFTER md5_cred;

UPDATE ps_auths
SET realm = COALESCE(NULLIF(realm, ''), 'sip.vectavoip.com')
WHERE username <> '';

UPDATE ps_auths
SET auth_type = 'md5',
    md5_cred = MD5(CONCAT(username, ':', realm, ':', password))
WHERE username <> '' AND password <> '';
