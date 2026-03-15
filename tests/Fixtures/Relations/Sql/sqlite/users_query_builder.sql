CREATE TABLE users
(
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    email      TEXT NOT NULL,
    created_at TEXT,
    updated_at TEXT
);
