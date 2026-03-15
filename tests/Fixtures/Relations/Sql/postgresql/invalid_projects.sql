DROP TABLE IF EXISTS invalid_projects;
CREATE TABLE invalid_projects
(
    project_id SERIAL PRIMARY KEY,
    name       TEXT NOT NULL
);
