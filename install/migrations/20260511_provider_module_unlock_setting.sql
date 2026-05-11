INSERT INTO cc_config
    (config_title, config_key, config_value, config_description, config_valuetype, config_listvalues, config_group_title)
SELECT
    'Provider Modules Unlocked',
    'provider_modules_unlocked',
    '0',
    'Persistently unlock non-VectaVoIP provider modules after the provider unlock token has been validated once.',
    0,
    '0,1',
    'webui'
WHERE NOT EXISTS (
    SELECT 1 FROM cc_config WHERE config_key = 'provider_modules_unlocked'
);
