DROP TABLE IF EXISTS profiles;
CREATE TABLE profiles
(
    profile_id INTEGER PRIMARY KEY,
    nickname   TEXT NOT NULL,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY (profile_id) REFERENCES users (user_id)
);
