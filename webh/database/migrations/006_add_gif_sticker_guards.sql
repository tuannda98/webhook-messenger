INSERT INTO system_config (`key`, `value`) VALUES
    ('allow_attachment_gif',     '1'),
    ('allow_attachment_sticker', '1')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
