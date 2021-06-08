START TRANSACTION ISOLATION LEVEL SERIALIZABLE READ WRITE;

CREATE TABLE sessions (
    id            varchar  PRIMARY KEY NOT NULL,
    data          text,
    last_activity int                  NOT NULL
);

CREATE INDEX last_activity ON sessions (last_activity);

INSERT INTO migrations(version, created)
VALUES (4, NOW());

COMMIT;
