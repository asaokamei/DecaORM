DROP TABLE IF EXISTS invalid_projects;
CREATE TABLE invalid_projects
(
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL
);
