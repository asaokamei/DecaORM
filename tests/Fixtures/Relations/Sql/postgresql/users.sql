CREATE TABLE users
(
    user_id    SERIAL PRIMARY KEY,
    user_name  TEXT NOT NULL,
    email      TEXT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
