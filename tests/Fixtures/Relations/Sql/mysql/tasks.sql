SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS tasks;
SET FOREIGN_KEY_CHECKS = 1;
CREATE TABLE tasks
(
    task_id    INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    parent_id  INT NULL,
    user_id    INT,
    title      VARCHAR(255) NOT NULL,
    created_at VARCHAR(30),
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (parent_id) REFERENCES tasks(task_id)
);
