-- Migration 001: Thêm emoji vào các message mặc định trong system_config
-- Chạy: mysql -u <user> -p <db> < migrations/001_update_system_config_messages.sql

INSERT INTO system_config (`key`, `value`) VALUES
    ('welcome_message',          '👋 Xin chào {name}!\n\nChào mừng bạn đến với chat ngẫu nhiên.\nGõ /start để tìm người trò chuyện!'),
    ('waiting_message',          '🔍 Đang tìm người chat cho bạn...\n\nGõ /end để huỷ tìm kiếm.'),
    ('matched_message',          '🎉 Đã kết nối! Hãy bắt đầu trò chuyện.\n\nGõ /end để kết thúc.'),
    ('disconnected_message',     '👋 Bạn đã kết thúc cuộc trò chuyện.\n\nGõ /start để tìm người mới.'),
    ('partner_left_message',     '😢 Người kia đã rời cuộc trò chuyện.\n\nGõ /start để tìm người mới.'),
    ('left_wait_room_message',   '✅ Đã huỷ tìm kiếm.\n\nGõ /start để tìm người mới.'),
    ('not_in_chat_message',      '❌ Bạn chưa trong cuộc trò chuyện nào.'),
    ('already_chatting_message', '⚠️ Bạn đang trong cuộc trò chuyện. Gõ /end trước.'),
    ('already_waiting_message',  '⏳ Bạn đang chờ ghép cặp. Gõ /end để huỷ.'),
    ('prompt_start_message',     '💬 Gõ /start để tìm người chat ngẫu nhiên!'),
    ('help_message',             '📖 Hướng dẫn:\n\n/start — Tìm người chat ngẫu nhiên\n/end   — Kết thúc / Huỷ tìm kiếm\n/help  — Xem hướng dẫn')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
