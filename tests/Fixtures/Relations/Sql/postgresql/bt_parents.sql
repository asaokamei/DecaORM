DROP TABLE IF EXISTS bt_parents CASCADE;
CREATE TABLE bt_parents
(
    id      SERIAL PRIMARY KEY,
    data_id TEXT NOT NULL,
    status  TEXT NOT NULL
);

