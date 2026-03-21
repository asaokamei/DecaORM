SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS morph_thumbnails;
SET FOREIGN_KEY_CHECKS = 1;
CREATE TABLE morph_thumbnails
(
    thumbnail_id       INT AUTO_INCREMENT PRIMARY KEY,
    url                VARCHAR(512) NOT NULL,
    thumbnailable_id   INT,
    thumbnailable_type VARCHAR(64)
);
