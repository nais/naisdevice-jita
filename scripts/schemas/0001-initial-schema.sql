START TRANSACTION ISOLATION LEVEL SERIALIZABLE READ WRITE;

CREATE TABLE migrations (
    version int PRIMARY KEY          NOT NULL,
    created timestamp with time zone NOT NULL
);

CREATE TABLE requests (
    id      serial PRIMARY KEY NOT NULL,
    created int                NOT NULL,
    gateway varchar            NOT NULL,
    user_id varchar            NOT NULL,
    reason  text               NOT NULL,
    expires int                NOT NULL
);

CREATE INDEX user_id ON requests (user_id);
CREATE INDEX gateway ON requests (gateway);
CREATE INDEX expires ON requests (expires);

INSERT INTO migrations(version, created)
VALUES (1, NOW());

COMMIT;
