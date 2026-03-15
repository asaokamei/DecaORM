DROP TABLE IF EXISTS posts;
CREATE TABLE posts
(
    post_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    title      VARCHAR(255) NOT NULL,
    content    TEXT NOT NULL,
    created_at VARCHAR(30),
    updated_at VARCHAR(30),
    FOREIGN KEY (user_id) REFERENCES users (user_id)
);
