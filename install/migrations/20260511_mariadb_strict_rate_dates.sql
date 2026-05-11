SET @A2BP_OLD_SQL_MODE = @@SESSION.sql_mode;
SET SESSION sql_mode = REPLACE(REPLACE(REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_DATE,', ''), ',NO_ZERO_DATE', ''), 'NO_ZERO_IN_DATE,', ''), ',NO_ZERO_IN_DATE', '');

UPDATE cc_tariffplan
SET startingdate = CURRENT_TIMESTAMP
WHERE startingdate = '0000-00-00 00:00:00';

UPDATE cc_tariffplan
SET expirationdate = '2037-12-31 23:59:59'
WHERE expirationdate = '0000-00-00 00:00:00';

UPDATE cc_ratecard
SET stopdate = '2037-12-31 23:59:59'
WHERE stopdate = '0000-00-00 00:00:00';

ALTER TABLE cc_tariffplan
    MODIFY startingdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY expirationdate TIMESTAMP NOT NULL DEFAULT '2037-12-31 23:59:59';

ALTER TABLE cc_ratecard
    MODIFY stopdate TIMESTAMP NOT NULL DEFAULT '2037-12-31 23:59:59';

SET SESSION sql_mode = @A2BP_OLD_SQL_MODE;
