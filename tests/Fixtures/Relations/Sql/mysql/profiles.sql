CREATE TABLE profiles
(
    profile_id INT PRIMARY KEY,
    nickname   VARCHAR(255) NOT NULL,
    created_at VARCHAR(30),
    updated_at VARCHAR(30),
    FOREIGN KEY (profile_id) REFERENCES users (user_id)
);
