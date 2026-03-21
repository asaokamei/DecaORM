DROP TABLE IF EXISTS morph_videos CASCADE;
CREATE TABLE morph_videos
(
    video_id SERIAL PRIMARY KEY,
    title    TEXT NOT NULL
);
