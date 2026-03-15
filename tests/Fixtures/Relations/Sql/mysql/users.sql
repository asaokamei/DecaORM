DROP TABLE IF EXISTS users;
CREATE TABLE users
(
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_name  VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NOT NULL,
    created_at VARCHAR(30),
    updated_at VARCHAR(30)
);
