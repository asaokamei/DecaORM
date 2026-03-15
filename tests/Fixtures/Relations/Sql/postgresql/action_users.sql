DROP TABLE IF EXISTS action_users CASCADE;
CREATE TABLE action_users
(
    user_id   SERIAL PRIMARY KEY,
    user_name TEXT
);
