DROP TABLE IF EXISTS roles;
CREATE TABLE roles
(
    role_id    SERIAL PRIMARY KEY,
    role_name  TEXT NOT NULL
);
