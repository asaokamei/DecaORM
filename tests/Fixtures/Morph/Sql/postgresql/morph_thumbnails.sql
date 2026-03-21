DROP TABLE IF EXISTS morph_thumbnails CASCADE;
CREATE TABLE morph_thumbnails
(
    thumbnail_id       SERIAL PRIMARY KEY,
    url                TEXT NOT NULL,
    thumbnailable_id   INT,
    thumbnailable_type VARCHAR(64)
);
