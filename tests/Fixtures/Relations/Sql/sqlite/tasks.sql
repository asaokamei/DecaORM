DROP TABLE IF EXISTS tasks;
CREATE TABLE tasks
(
    task_id    INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    parent_id  INTEGER,
    user_id    INTEGER,
    title      TEXT NOT NULL,
    created_at TEXT,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (parent_id) REFERENCES tasks(task_id)
);
