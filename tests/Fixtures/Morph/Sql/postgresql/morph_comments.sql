DROP TABLE IF EXISTS morph_comments CASCADE;
CREATE TABLE morph_comments
(
    comment_id       SERIAL PRIMARY KEY,
    body             TEXT NOT NULL,
    commentable_id   INT,
    commentable_type VARCHAR(64)
);
