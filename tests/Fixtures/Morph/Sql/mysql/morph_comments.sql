SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS morph_comments;
SET FOREIGN_KEY_CHECKS = 1;
CREATE TABLE morph_comments
(
    comment_id       INT AUTO_INCREMENT PRIMARY KEY,
    body             TEXT NOT NULL,
    commentable_id   INT,
    commentable_type VARCHAR(64)
);
