ALTER TABLE users
    ADD COLUMN last_messaged_at  DATETIME NULL AFTER updated_at,
    ADD COLUMN session_warned_at DATETIME NULL AFTER last_messaged_at,
    ADD INDEX idx_last_messaged_at (last_messaged_at);
