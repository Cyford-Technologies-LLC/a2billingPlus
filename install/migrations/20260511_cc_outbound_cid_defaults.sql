SET @A2BP_OLD_SQL_MODE = @@SESSION.sql_mode;
SET SESSION sql_mode = REPLACE(REPLACE(REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_DATE,', ''), ',NO_ZERO_DATE', ''), 'NO_ZERO_IN_DATE,', ''), ',NO_ZERO_IN_DATE', '');

UPDATE cc_outbound_cid_group
SET creationdate = CURRENT_TIMESTAMP
WHERE creationdate = '0000-00-00 00:00:00';

UPDATE cc_outbound_cid_list
SET creationdate = CURRENT_TIMESTAMP
WHERE creationdate = '0000-00-00 00:00:00';

ALTER TABLE cc_outbound_cid_group
    MODIFY creationdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY group_name VARCHAR(70) NOT NULL DEFAULT '';

ALTER TABLE cc_outbound_cid_list
    MODIFY outbound_cid_group INT(11) NOT NULL DEFAULT 0,
    MODIFY cid CHAR(100) DEFAULT NULL,
    MODIFY activated INT(11) NOT NULL DEFAULT 0,
    MODIFY creationdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

SET SESSION sql_mode = @A2BP_OLD_SQL_MODE;
