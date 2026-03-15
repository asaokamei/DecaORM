DROP TABLE IF EXISTS profiles CASCADE;
CREATE TABLE profiles
(
    profile_id INTEGER PRIMARY KEY,
    nickname   TEXT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES users (user_id)
);
