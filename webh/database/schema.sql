-- Phòng chờ ghép cặp
CREATE TABLE IF NOT EXISTS wait_room (
    psid       VARCHAR(50) NOT NULL PRIMARY KEY,
    gender     ENUM('male', 'female', 'unknown') NOT NULL DEFAULT 'unknown',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Phòng chat đang hoạt động (mỗi user chỉ được trong 1 phòng)
CREATE TABLE IF NOT EXISTS chat_room (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    psid1      VARCHAR(50) NOT NULL UNIQUE,
    psid2      VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Lịch sử toàn bộ các lần ghép đôi
CREATE TABLE IF NOT EXISTS chat_history (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    psid1      VARCHAR(50)  NOT NULL,
    psid2      VARCHAR(50)  NOT NULL,
    started_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at   DATETIME     NULL,
    INDEX idx_psid1 (psid1),
    INDEX idx_psid2 (psid2)
);

-- Thông tin user (fetch từ Facebook Graph API)
CREATE TABLE IF NOT EXISTS users (
    psid        VARCHAR(50)  NOT NULL PRIMARY KEY,
    name        VARCHAR(255) NOT NULL DEFAULT '',
    first_name  VARCHAR(100) NOT NULL DEFAULT '',
    profile_pic TEXT,
    gender      ENUM('male', 'female', 'unknown') NOT NULL DEFAULT 'unknown',
    points      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_messaged_at    DATETIME NULL,
    session_warned_at   DATETIME NULL,
    INDEX idx_last_messaged_at (last_messaged_at)
);

-- Theo dõi số lần đăng nhập admin thất bại (brute-force protection)
CREATE TABLE IF NOT EXISTS admin_login_attempts (
    ip            VARCHAR(45)      NOT NULL PRIMARY KEY,
    attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    blocked_until DATETIME         NULL,
    last_attempt  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Bộ lọc từ ngữ (from_word → to_word)
CREATE TABLE IF NOT EXISTS word_filters (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    from_word  VARCHAR(200) NOT NULL,
    to_word    VARCHAR(200) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_from_word (from_word)
);

-- Cấu hình hệ thống (admin toggle)
CREATE TABLE IF NOT EXISTS system_config (
    `key`       VARCHAR(100) NOT NULL PRIMARY KEY,
    `value`     TEXT         NOT NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Giá trị mặc định
INSERT INTO system_config (`key`, `value`) VALUES
    -- Matching
    ('match_by_gender',       '1'),
    ('exclude_recent_count',  '1'),
    -- Messages: dùng {name} làm placeholder cho tên user
    ('welcome_message',       '👋 Xin chào {name}!\n\nChào mừng bạn đến với chat ngẫu nhiên.\nGõ /start để tìm người trò chuyện!'),
    ('waiting_message',       '🔍 Đang tìm người chat cho bạn...\n\nGõ /end để huỷ tìm kiếm.'),
    ('matched_message',       '🎉 Đã kết nối! Hãy bắt đầu trò chuyện.\n\nGõ /end để kết thúc.'),
    ('disconnected_message',  '👋 Bạn đã kết thúc cuộc trò chuyện.\n\nGõ /start để tìm người mới.'),
    ('partner_left_message',  '😢 Người kia đã rời cuộc trò chuyện.\n\nGõ /start để tìm người mới.'),
    ('left_wait_room_message','✅ Đã huỷ tìm kiếm.\n\nGõ /start để tìm người mới.'),
    ('not_in_chat_message',   '❌ Bạn chưa trong cuộc trò chuyện nào.'),
    ('already_chatting_message', '⚠️ Bạn đang trong cuộc trò chuyện. Gõ /end trước.'),
    ('already_waiting_message',  '⏳ Bạn đang chờ ghép cặp. Gõ /end để huỷ.'),
    ('prompt_start_message',  '💬 Gõ /start để tìm người chat ngẫu nhiên!'),
    ('help_message',          '📖 Hướng dẫn:\n\n/start — Tìm người chat ngẫu nhiên\n/end   — Kết thúc / Huỷ tìm kiếm\n/help  — Xem hướng dẫn'),
    ('default_display_name',  'bạn')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
