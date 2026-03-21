DROP TABLE IF EXISTS morph_thumbnails;
CREATE TABLE morph_thumbnails
(
    thumbnail_id INTEGER PRIMARY KEY AUTOINCREMENT,
    url           TEXT NOT NULL,
    thumbnailable_id   INTEGER,
    thumbnailable_type TEXT
);
