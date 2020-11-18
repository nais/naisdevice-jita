START TRANSACTION ISOLATION LEVEL SERIALIZABLE READ WRITE;

ALTER TABLE requests
ADD COLUMN created_ts timestamp with time zone NULL,
ADD COLUMN expires_ts timestamp with time zone NULL;

UPDATE requests
SET
    created_ts = TO_TIMESTAMP(created),
    expires_ts = TO_TIMESTAMP(expires);

ALTER TABLE requests
DROP COLUMN created,
DROP COLUMN expires;

ALTER TABLE requests
RENAME created_ts TO created;

ALTER TABLE requests
RENAME expires_ts TO expires;

CREATE INDEX expires ON requests (expires);

ALTER TABLE requests
ALTER COLUMN created SET NOT NULL,
ALTER COLUMN expires SET NOT NULL;

INSERT INTO migrations(version, created)
VALUES (2, NOW());

COMMIT;
