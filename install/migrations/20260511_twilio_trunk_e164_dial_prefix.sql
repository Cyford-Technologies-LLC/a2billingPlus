UPDATE cc_trunk
SET trunkprefix = '+'
WHERE COALESCE(trunkprefix, '') = ''
  AND (
      addparameter = 'twilio_trunk'
      OR addparameter LIKE 'twilio_trunk:%'
      OR addparameter LIKE 'twilio_elastic_trunk:%'
      OR addparameter LIKE 'twilio_elastic_host:%'
      OR addparameter LIKE 'twilio_sip_domain:%'
      OR addparameter LIKE 'twilio_byoc_trunk:%'
      OR addparameter LIKE 'twilio_byoc_host:%'
  );
