ALTER TABLE cc_card
    MODIFY fax varchar(20) NOT NULL DEFAULT '',
    MODIFY redial varchar(50) NOT NULL DEFAULT '',
    MODIFY loginkey varchar(40) NOT NULL DEFAULT '',
    MODIFY tag varchar(50) NOT NULL DEFAULT '',
    MODIFY email_notification varchar(70) NOT NULL DEFAULT '',
    MODIFY company_name varchar(50) NOT NULL DEFAULT '',
    MODIFY company_website varchar(60) NOT NULL DEFAULT '',
    MODIFY traffic_target varchar(300) NOT NULL DEFAULT '';
