UPDATE cc_card SET traffic = 0 WHERE traffic IS NULL;

ALTER TABLE cc_card
    MODIFY traffic bigint(20) NOT NULL DEFAULT 0;
