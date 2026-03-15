SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS user_role;
SET FOREIGN_KEY_CHECKS = 1;
CREATE TABLE user_role
(
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);
