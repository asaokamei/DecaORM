DROP TABLE IF EXISTS invalid_projects;
CREATE TABLE invalid_projects
(
    project_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL
);
