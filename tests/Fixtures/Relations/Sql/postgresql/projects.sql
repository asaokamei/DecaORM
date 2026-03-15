DROP TABLE IF EXISTS projects;
CREATE TABLE projects
(
    project_id SERIAL PRIMARY KEY,
    name       TEXT NOT NULL
);
