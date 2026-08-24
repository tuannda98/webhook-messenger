CREATE TABLE IF NOT EXISTS word_filters (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    from_word  VARCHAR(200) NOT NULL,
    to_word    VARCHAR(200) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_from_word (from_word)
);

-- Media guard defaults (cho phép tất cả)
INSERT INTO system_config (`key`, `value`) VALUES
    ('allow_attachment_image', '1'),
    ('allow_attachment_video', '1'),
    ('allow_attachment_audio', '1'),
    ('allow_attachment_file',  '1')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
