DROP TABLE IF EXISTS bt_children CASCADE;
CREATE TABLE bt_children
(
    id      SERIAL PRIMARY KEY,
    data_id TEXT NOT NULL
);

