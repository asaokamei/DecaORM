SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS comments;
SET FOREIGN_KEY_CHECKS = 1;
CREATE TABLE comments
(
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id    INT NOT NULL,
    body       TEXT NOT NULL,
    created_at VARCHAR(30),
    updated_at VARCHAR(30),
    FOREIGN KEY (post_id) REFERENCES posts (post_id)
);
