DROP TABLE IF EXISTS tasks CASCADE;
CREATE TABLE tasks
(
    task_id    SERIAL PRIMARY KEY,
    project_id INTEGER NOT NULL,
    parent_id  INTEGER,
    user_id    INTEGER,
    title      TEXT NOT NULL,
    created_at TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (parent_id) REFERENCES tasks(task_id)
);
