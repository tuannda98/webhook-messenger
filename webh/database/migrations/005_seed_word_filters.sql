INSERT INTO word_filters (from_word, to_word) VALUES
    -- Chửi thề / Thô tục
    ('đụ',        '[***]'),
    ('đ.m',       '[***]'),
    ('đéo',       'không'),
    ('địt',       '[***]'),
    ('lồn',       '[***]'),
    ('cặc',       '[***]'),
    ('buồi',      '[***]'),
    ('mẹ kiếp',   'thôi nào'),
    ('vãi lồn',   'thật sự'),
    ('vl',        'thật'),
    ('vcl',       'thật quá'),
    ('đcm',       'ôi trời'),

    -- Xúc phạm / Miệt thị
    ('đồ chó',    'bạn ơi'),
    ('thằng chó', 'bạn ơi'),
    ('con chó',   'cún'),
    ('đồ ngu',    'bạn à'),
    ('thằng ngu', 'bạn à'),
    ('óc chó',    'suy nghĩ lại'),
    ('ngu vl',    'sai rồi'),
    ('mày',       'bạn'),
    ('tao',       'mình'),

    -- Kỳ thị (xoá từ)
    ('pê đê',     ''),
    ('bê đê',     ''),
    ('mọi',       '')

ON DUPLICATE KEY UPDATE to_word = VALUES(to_word);
