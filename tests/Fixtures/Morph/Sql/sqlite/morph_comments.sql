DROP TABLE IF EXISTS morph_comments;
CREATE TABLE morph_comments
(
    comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    body         TEXT NOT NULL,
    commentable_id   INTEGER,
    commentable_type TEXT
);
