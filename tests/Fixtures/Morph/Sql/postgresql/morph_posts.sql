DROP TABLE IF EXISTS morph_posts CASCADE;
CREATE TABLE morph_posts
(
    post_id SERIAL PRIMARY KEY,
    title   TEXT NOT NULL
);
