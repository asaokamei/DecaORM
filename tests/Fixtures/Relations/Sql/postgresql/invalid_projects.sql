DROP TABLE IF EXISTS invalid_projects CASCADE;
CREATE TABLE invalid_projects
(
    project_id SERIAL PRIMARY KEY,
    name       TEXT NOT NULL
);
