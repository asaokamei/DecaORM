CREATE TABLE tasks
(
    task_id    SERIAL PRIMARY KEY,
    project_id INTEGER NOT NULL,
    user_id    INTEGER,
    title      TEXT NOT NULL,
    created_at TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id)
);
