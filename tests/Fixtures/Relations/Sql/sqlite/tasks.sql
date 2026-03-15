CREATE TABLE tasks
(
    task_id    INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    user_id    INTEGER,
    title      TEXT NOT NULL,
    created_at TEXT,
    FOREIGN KEY (project_id) REFERENCES projects(project_id)
);
