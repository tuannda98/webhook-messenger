<?php
// Bật hiển thị và bắt lỗi runtime
error_reporting(E_ALL);
ini_set('display_errors', 0);
// ==============================================================================
// CẤU HÌNH THÔNG TIN BOT
// ==============================================================================
$verify_token = "VERIFY_TOKEN_HAUI_CHATBOT_TUANNDA"; // Đặt chuỗi bí mật bất kỳ trùng với ô Verify Token trên Meta Dashboard
$page_access_token = "EAAXkFOhcicABSaJkGv45MPh2IFogH9MeMYQfS974y54zJ1ZBVPPZC60iNcgpeDMD7vWZB6tmiQD9OFD3o57lJTcqZCaZBPZBWZAUTeZBpnhFZB0DKrbPaHGFJOmVD7JDfryligL3jZAOn8dEpeZCCiZAkyZAz6kaxIYoYZABqZAbTulZBUD3aZCrgZAZBORKdv3lmSPkcRdAz4FZBO5BrLBjNUwo1IQJmiGDzQZDZD"; // Lấy từ mục Messenger > API Setup / Access Tokens

define('LOG_FILE', __DIR__ . '/webhook.log');

function writeLog($tag, $data) {
    $time = date('Y-m-d H:i:s');
    $content = is_array($data) || is_object($data) ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $data;
    $logEntry = "[{$time}] [{$tag}]\n{$content}\n" . str_repeat("-", 50) . "\n";
    @file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

// Bắt lỗi crash/fatal của PHP
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        writeLog('PHP_FATAL_ERROR', $error);
    }
});

// ==============================================================================
// 2. CHẾ ĐỘ TEST TRỰC TIẾP TRÊN TRÌNH DUYỆT (GET ?test=1)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test'])) {
    $test_sender = "27697724709847964";
    $res = sendFacebookMessage($test_sender, "Tin nhắn thử nghiệm từ hệ thống!", $page_access_token);
    echo "<h3>Kết quả kiểm tra:</h3><pre>" . htmlspecialchars(print_r($res, true)) . "</pre>";
    exit;
}

// ==============================================================================
// 3. XÁC THỰC WEBHOOK VỚI META (GET REQUEST)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hub_mode         = $_GET['hub_mode'] ?? '';
    $hub_verify_token = $_GET['hub_verify_token'] ?? '';
    $hub_challenge    = $_GET['hub_challenge'] ?? '';

    if ($hub_mode === 'subscribe' && $hub_verify_token === $verify_token) {
        http_response_code(200);
        echo $hub_challenge;
        exit;
    }
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// ==============================================================================
// 4. TIẾP NHẬN & PHẢN HỒI TIN NHẮN (POST REQUEST)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    writeLog('POST_WEBHOOK_PAYLOAD', $data ?? $raw_input);

    if (!empty($data['entry']) && is_array($data['entry'])) {
        foreach ($data['entry'] as $entry) {
            $events = $entry['messaging'] ?? [];

            foreach ($events as $event) {
                // Bỏ qua tin nhắn do Page tự gửi ra
                if (!empty($event['message']['is_echo'])) {
                    continue;
                }

                $sender_id = $event['sender']['id'] ?? null;
                $user_text = trim($event['message']['text'] ?? '');

                if ($sender_id && $user_text !== '') {
                    $reply_text = getBotReply($user_text);
                    sendFacebookMessage($sender_id, $reply_text, $page_access_token);
                }
            }
        }
    }

    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}

// ==============================================================================
// 5. KỊCH BẢN PHẢN HỒI TIN NHẮN
// ==============================================================================
function getBotReply($user_text) {
    $lower = strtolower($user_text);

    // Kịch bản chào hỏi
    if (strpos($lower, 'chao') !== false || strpos($lower, 'chào') !== false || strpos($lower, 'hello') !== false || strpos($lower, 'hi') !== false) {
        return "Xin chào! Cảm ơn bạn đã liên hệ HaUI Chatbot. Chúng tôi có thể hỗ trợ gì cho bạn hôm nay?";
    }
    // Kịch bản hỗ trợ
    if (strpos($lower, 'ho tro') !== false || strpos($lower, 'hỗ trợ') !== false || strpos($lower, 'help') !== false) {
        return "Hệ thống hỗ trợ tự động đã tiếp nhận câu hỏi của bạn. Vui lòng để lại nội dung chi tiết, chúng tôi sẽ xử lý ngay.";
    }
    // Kịch bản xóa dữ liệu (Meta Compliance)
    if ($lower === '#delete' || $lower === '#xoa_du_lieu' || strpos($lower, 'xóa') !== false) {
        return "Yêu cầu xóa dữ liệu của bạn đã được ghi nhận. Toàn bộ lịch sử trò chuyện và thông tin định danh của bạn đã được xóa hoàn toàn khỏi hệ thống.";
    }

    // Phản hồi mặc định
    return "HaUI Chatbot đã nhận được tin nhắn: \"" . $user_text . "\". Hệ thống tự động đang xử lý yêu cầu của bạn!";
}

// ==============================================================================
// 6. GỌI GRAPH SEND API
// ==============================================================================
function sendFacebookMessage($recipient_id, $text, $token) {
    if (!function_exists('curl_init')) {
        writeLog('CURL_ERROR', 'Server chưa cài extension php-curl');
        return ['error' => 'cURL not installed'];
    }

    $url = "https://graph.facebook.com/v19.0/me/messages?access_token=" . trim($token);
    $payload = [
        'recipient' => ['id' => $recipient_id],
        'messaging_type' => 'RESPONSE',
        'message' => ['text' => $text]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 10
    ]);
    
    $result     = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $logData = [
        'http_code' => $http_code,
        'payload'   => $payload,
        'response'  => json_decode($result, true) ?? $result,
        'curl_err'  => $curl_error ?: null
    ];

    writeLog('SEND_API_RESULT', $logData);
    return $logData;
}