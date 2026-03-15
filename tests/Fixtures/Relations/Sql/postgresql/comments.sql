DROP TABLE IF EXISTS comments CASCADE;
CREATE TABLE comments
(
    comment_id SERIAL PRIMARY KEY,
    post_id    INTEGER NOT NULL,
    body       TEXT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts (post_id)
);
