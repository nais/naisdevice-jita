START TRANSACTION ISOLATION LEVEL SERIALIZABLE READ WRITE;

ALTER TABLE requests
ADD COLUMN revoked timestamp with time zone NULL;

CREATE INDEX revoked ON requests (revoked);

INSERT INTO migrations(version, created)
VALUES (3, NOW());

COMMIT;
