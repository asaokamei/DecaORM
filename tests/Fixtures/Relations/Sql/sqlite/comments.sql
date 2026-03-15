CREATE TABLE comments
(
    comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id    INTEGER NOT NULL,
    body       TEXT NOT NULL,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY (post_id) REFERENCES posts (post_id)
);
