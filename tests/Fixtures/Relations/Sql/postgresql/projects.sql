DROP TABLE IF EXISTS projects CASCADE;
CREATE TABLE projects
(
    project_id SERIAL PRIMARY KEY,
    name       TEXT NOT NULL
);
