CREATE TABLE users
(
    user_id    INTEGER PRIMARY KEY AUTOINCREMENT,
    user_name  TEXT NOT NULL,
    email      TEXT NOT NULL,
    created_at TEXT,
    updated_at TEXT
)
