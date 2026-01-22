CREATE TABLE comments (
    comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INT NOT NULL REFERENCES posts(post_id),
    comment TEXT NOT NULL,
    FOREIGN KEY (post_id) REFERENCES post (post_id)
)