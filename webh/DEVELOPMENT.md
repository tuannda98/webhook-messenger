# Facebook Webhook PHP 8.3 — Tài liệu Deploy & Phát triển

## Mục lục

1. [Tổng quan kiến trúc](#1-tổng-quan-kiến-trúc)
2. [Yêu cầu hệ thống](#2-yêu-cầu-hệ-thống)
3. [Cài đặt môi trường phát triển](#3-cài-đặt-môi-trường-phát-triển)
4. [Cấu hình Facebook App](#4-cấu-hình-facebook-app)
5. [Deploy lên Production](#5-deploy-lên-production)
6. [Hướng dẫn phát triển](#6-hướng-dẫn-phát-triển)
7. [Testing](#7-testing)
8. [Troubleshooting](#8-troubleshooting)

---

## 1. Tổng quan kiến trúc

```
HTTP Request
     │
     ▼
public/index.php          ← Entry point, bootstrap .env + DI
     │
     ▼
WebhookHandler
├── GET  → handleVerification()   ← Xác thực webhook với Facebook
└── POST → handleWebhook()
          │
          ├── SignatureVerifier   ← Xác thực HMAC-SHA256
          │
          └── dispatchEvent()
               ├── MessageHandler   ← Tin nhắn text, quick reply, attachment
               └── PostbackHandler  ← Nút bấm, Get Started
                         │
                         ▼
                  MessengerService  ← Gọi Graph API gửi phản hồi
```

### Cấu trúc thư mục

```
webhook-fb/
├── public/
│   ├── index.php           Entry point (document root trỏ vào đây)
│   └── .htaccess           Rewrite rule cho Apache
├── src/
│   ├── Config/
│   │   └── Config.php      Đọc biến môi trường từ .env
│   ├── Exception/
│   │   ├── WebhookException.php
│   │   ├── SignatureVerificationException.php
│   │   └── MessengerApiException.php
│   ├── Handler/
│   │   ├── WebhookHandler.php        Điều phối sự kiện
│   │   └── Event/
│   │       ├── MessageHandler.php    Xử lý tin nhắn
│   │       └── PostbackHandler.php   Xử lý postback
│   └── Service/
│       ├── MessengerService.php      Gửi tin nhắn qua Graph API
│       ├── SignatureVerifier.php     Xác thực chữ ký
│       └── LoggerFactory.php        Khởi tạo logger
├── tests/
├── logs/                   Được tạo tự động
├── composer.json
├── phpunit.xml
├── .env.example
└── .gitignore
```

---

## 2. Yêu cầu hệ thống

| Thành phần | Phiên bản tối thiểu |
|---|---|
| PHP | 8.3+ |
| Composer | 2.x |
| Web server | Apache 2.4+ hoặc Nginx 1.20+ |
| SSL/TLS | Bắt buộc (Facebook yêu cầu HTTPS) |
| Extension PHP | `curl`, `json`, `mbstring`, `openssl` |

Kiểm tra extension:

```bash
php -m | grep -E "curl|json|mbstring|openssl"
```

---

## 3. Cài đặt môi trường phát triển

### Bước 1 — Clone và cài dependencies

```bash
git clone <repo-url> webhook-fb
cd webhook-fb
composer install
```

### Bước 2 — Tạo file .env

```bash
cp .env.example .env
```

Điền các giá trị vào `.env`:

```dotenv
# Facebook App (lấy từ developers.facebook.com)
FB_APP_ID=123456789012345
FB_APP_SECRET=abc123def456...
FB_VERIFY_TOKEN=my_secret_verify_string_2024
FB_PAGE_ACCESS_TOKEN=EAAxxxxxxx...

# Graph API
FB_GRAPH_API_VERSION=v21.0
FB_GRAPH_API_URL=https://graph.facebook.com

# Application
APP_ENV=development
APP_DEBUG=true
LOG_LEVEL=debug
LOG_PATH=logs/app.log
```

### Bước 3 — Tạo thư mục logs

```bash
mkdir -p logs
chmod 775 logs
```

### Bước 4 — Chạy server local (PHP built-in)

```bash
php -S localhost:8080 -t public/
```

Webhook cần HTTPS nên dùng **ngrok** để expose ra ngoài:

```bash
ngrok http 8080
```

Ngrok sẽ cho URL dạng `https://xxxx.ngrok.io` — dùng URL này để cấu hình webhook trên Facebook.

---

## 4. Cấu hình Facebook App

### 4.1 Tạo Facebook App

1. Truy cập [developers.facebook.com](https://developers.facebook.com)
2. **My Apps → Create App → Business**
3. Điền tên app và email liên hệ
4. Vào **Settings → Basic** → sao chép `App ID` và `App Secret` vào `.env`

### 4.2 Thêm sản phẩm Messenger

1. Vào **Dashboard → Add Product → Messenger → Set Up**
2. **Access Tokens → Add or Remove Pages** → chọn fanpage của bạn
3. Copy `Page Access Token` vào `FB_PAGE_ACCESS_TOKEN` trong `.env`

### 4.3 Cấu hình Webhook

1. Trong Messenger Settings, cuộn đến **Webhooks → Add Callback URL**
2. Nhập:
   - **Callback URL**: `https://your-domain.com/` (hoặc ngrok URL khi dev)
   - **Verify Token**: giá trị bạn đã đặt trong `FB_VERIFY_TOKEN`
3. Nhấn **Verify and Save**
4. Sau khi verify thành công, tick chọn các subscription fields:
   - ✅ `messages`
   - ✅ `messaging_postbacks`
   - ✅ `messaging_optins` (nếu cần)

### 4.4 Cấu hình nút Get Started

Chạy lệnh curl này một lần để kích hoạt nút "Get Started" trên Messenger:

```bash
curl -X POST "https://graph.facebook.com/v21.0/me/messenger_profile" \
  -H "Content-Type: application/json" \
  -d '{
    "get_started": {"payload": "GET_STARTED"},
    "greeting": [
      {
        "locale": "default",
        "text": "Xin chào! Chào mừng bạn đến với {{page_first_name}}."
      },
      {
        "locale": "vi_VN",
        "text": "Xin chào {{user_first_name}}! Chúng tôi có thể giúp gì cho bạn?"
      }
    ]
  }' \
  "https://graph.facebook.com/v21.0/me/messenger_profile?access_token=YOUR_PAGE_ACCESS_TOKEN"
```

---

## 5. Deploy lên Production

### 5.1 Cấu hình Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    root /var/www/webhook-fb/public;
    index index.php;

    # Không cho truy cập các file nhạy cảm
    location ~ /\.(env|git) {
        deny all;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

# Redirect HTTP → HTTPS
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$host$request_uri;
}
```

### 5.2 Cấu hình Apache

```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/webhook-fb/public

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/your-domain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem

    <Directory /var/www/webhook-fb/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Bảo vệ file nhạy cảm
    <FilesMatch "^\.env">
        Require all denied
    </FilesMatch>
</VirtualHost>
```

### 5.3 Thiết lập môi trường production

```bash
# Upload code (bỏ qua vendor/ và .env)
rsync -avz --exclude='vendor/' --exclude='.env' --exclude='logs/' \
  ./ user@server:/var/www/webhook-fb/

# Trên server
cd /var/www/webhook-fb
composer install --no-dev --optimize-autoloader
mkdir -p logs && chmod 775 logs
chown -R www-data:www-data logs/
```

Tạo `.env` trên server (không commit `.env` lên git):

```bash
cp .env.example .env
nano .env  # Điền các giá trị production
```

Cập nhật `.env` cho production:

```dotenv
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
LOG_PATH=logs/app.log
```

### 5.4 SSL với Let's Encrypt

```bash
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com
```

### 5.5 Checklist production

- [ ] `APP_DEBUG=false` trong `.env`
- [ ] File `.env` không nằm trong public/ và không commit lên git
- [ ] Thư mục `logs/` có quyền ghi cho web server
- [ ] SSL/HTTPS hoạt động (Facebook bắt buộc)
- [ ] `composer install --no-dev --optimize-autoloader` đã chạy
- [ ] Webhook đã được verify trên Facebook Developer Console
- [ ] Page Access Token còn hạn (hoặc dùng System User Token không hết hạn)

---

## 6. Hướng dẫn phát triển

### 6.1 Thêm xử lý tin nhắn mới

Mở [`src/Handler/Event/MessageHandler.php`](src/Handler/Event/MessageHandler.php) và thêm case vào `handleText()`:

```php
private function handleText(string $senderId, string $text): void
{
    $lower = mb_strtolower($text, 'UTF-8');

    match (true) {
        in_array($lower, ['hi', 'hello', 'xin chào', 'chào']) => $this->sendGreeting($senderId),
        str_contains($lower, 'giá') || str_contains($lower, 'báo giá') => $this->sendPricing($senderId), // Thêm mới
        str_contains($lower, 'giờ làm việc') => $this->sendWorkingHours($senderId),
        str_contains($lower, 'menu') => $this->sendMenu($senderId),
        default => $this->sendDefaultReply($senderId, $text),
    };
}

// Thêm method xử lý
private function sendPricing(string $senderId): void
{
    $this->messenger->sendText($senderId, "Vui lòng liên hệ hotline 1900xxxx để nhận báo giá chi tiết!");
}
```

### 6.2 Thêm postback mới

Mở [`src/Handler/Event/PostbackHandler.php`](src/Handler/Event/PostbackHandler.php):

```php
public function handle(string $senderId, array $postback): void
{
    $payload = $postback['payload'] ?? '';

    match ($payload) {
        'GET_STARTED'  => $this->onGetStarted($senderId),
        'SERVICE_A'    => $this->messenger->sendText($senderId, "Dịch vụ A..."),
        'PRICING'      => $this->onPricing($senderId),   // Thêm mới
        default        => $this->messenger->sendText($senderId, "Cảm ơn bạn!"),
    };
}

private function onPricing(string $senderId): void
{
    $this->messenger->sendButtonTemplate(
        $senderId,
        "Chọn gói dịch vụ:",
        [
            ['type' => 'postback', 'title' => 'Gói Cơ bản', 'payload' => 'PLAN_BASIC'],
            ['type' => 'postback', 'title' => 'Gói Pro',    'payload' => 'PLAN_PRO'],
        ]
    );
}
```

### 6.3 Các phương thức MessengerService

| Phương thức | Mô tả |
|---|---|
| `sendText($recipientId, $text)` | Gửi tin nhắn văn bản |
| `sendQuickReplies($recipientId, $text, $quickReplies)` | Gửi tin nhắn kèm nút quick reply |
| `sendButtonTemplate($recipientId, $text, $buttons)` | Gửi template với nút bấm (tối đa 3 nút) |
| `sendTemplate($recipientId, $templatePayload)` | Gửi template tùy chỉnh |
| `markSeen($recipientId)` | Đánh dấu đã đọc |
| `typingOn($recipientId)` | Hiển thị đang nhập |
| `typingOff($recipientId)` | Tắt hiển thị đang nhập |
| `getUserProfile($userId, $fields)` | Lấy thông tin người dùng |

**Ví dụ gửi Carousel (Generic Template):**

```php
$this->messenger->sendTemplate($senderId, [
    'template_type' => 'generic',
    'elements' => [
        [
            'title'     => 'Sản phẩm A',
            'subtitle'  => 'Mô tả sản phẩm A',
            'image_url' => 'https://example.com/product-a.jpg',
            'buttons'   => [
                ['type' => 'postback', 'title' => 'Mua ngay', 'payload' => 'BUY_A'],
                ['type' => 'web_url',  'title' => 'Xem chi tiết', 'url' => 'https://example.com/a'],
            ],
        ],
        [
            'title'    => 'Sản phẩm B',
            'subtitle' => 'Mô tả sản phẩm B',
            'buttons'  => [
                ['type' => 'postback', 'title' => 'Mua ngay', 'payload' => 'BUY_B'],
            ],
        ],
    ],
]);
```

### 6.4 Thêm loại sự kiện mới

Nếu muốn xử lý thêm các loại sự kiện (ví dụ: `read`, `delivery`, `referral`), mở [`src/Handler/WebhookHandler.php`](src/Handler/WebhookHandler.php), thêm handler mới:

```php
// Trong WebhookHandler — thêm dependency vào constructor
public function __construct(
    private readonly SignatureVerifier $verifier,
    private readonly MessageHandler $messageHandler,
    private readonly PostbackHandler $postbackHandler,
    private readonly ReferralHandler $referralHandler, // Thêm
    private readonly LoggerInterface $logger,
) {}

// Trong dispatchEvent() — thêm case
private function dispatchEvent(array $event): void
{
    $senderId = $event['sender']['id'] ?? '';

    match (true) {
        isset($event['message'])  => $this->messageHandler->handle($senderId, $event['message']),
        isset($event['postback']) => $this->postbackHandler->handle($senderId, $event['postback']),
        isset($event['referral']) => $this->referralHandler->handle($senderId, $event['referral']), // Thêm
        default => $this->logger->debug('Unhandled event', ['keys' => array_keys($event)]),
    };
}
```

Tạo file [`src/Handler/Event/ReferralHandler.php`](src/Handler/Event/ReferralHandler.php) theo cấu trúc tương tự `PostbackHandler`.

### 6.5 Tích hợp database

Nếu cần lưu trữ dữ liệu, thêm PDO vào `public/index.php`:

```php
// Thêm vào .env
// DB_HOST=localhost
// DB_NAME=webhook_fb
// DB_USER=root
// DB_PASS=secret

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', Config::string('DB_HOST'), Config::string('DB_NAME')),
    Config::string('DB_USER'),
    Config::string('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Truyền $pdo vào handler cần dùng
$messageHandler = new MessageHandler($messenger, $logger, $pdo);
```

---

## 7. Testing

### Chạy test suite

```bash
./vendor/bin/phpunit
```

### Chạy test cụ thể

```bash
./vendor/bin/phpunit tests/SignatureVerifierTest.php
./vendor/bin/phpunit --filter testValidSignature
```

### Viết test mới

Tạo file trong `tests/`, ví dụ `tests/MessageHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests;

use App\Handler\Event\MessageHandler;
use App\Service\MessengerService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class MessageHandlerTest extends TestCase
{
    private MessengerService&MockObject $messenger;
    private MessageHandler $handler;

    protected function setUp(): void
    {
        $this->messenger = $this->createMock(MessengerService::class);
        $this->handler   = new MessageHandler($this->messenger, new NullLogger());
    }

    public function testEchoMessageIsIgnored(): void
    {
        $this->messenger->expects($this->never())->method('sendText');

        $this->handler->handle('123', ['is_echo' => true, 'text' => 'hello']);
    }

    public function testGreetingTriggersWelcomeMessage(): void
    {
        $this->messenger->expects($this->atLeastOnce())->method('sendText');
        $this->messenger->method('markSeen')->willReturn(null);
        $this->messenger->method('typingOn')->willReturn(null);

        $this->handler->handle('123', ['text' => 'xin chào']);
    }
}
```

### Test webhook thủ công với curl

**Giả lập tin nhắn đến:**

```bash
# Tạo payload
PAYLOAD='{"object":"page","entry":[{"messaging":[{"sender":{"id":"123456"},"message":{"text":"xin chào"}}]}]}'

# Tạo signature
SECRET="your_app_secret"
SIG=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print "sha256="$2}')

# Gửi request
curl -X POST http://localhost:8080/ \
  -H "Content-Type: application/json" \
  -H "X-Hub-Signature-256: $SIG" \
  -d "$PAYLOAD"
```

**Giả lập verify webhook:**

```bash
curl "http://localhost:8080/?hub.mode=subscribe&hub.verify_token=YOUR_VERIFY_TOKEN&hub.challenge=CHALLENGE_STRING"
# Kỳ vọng output: CHALLENGE_STRING
```

---

## 8. Troubleshooting

### Lỗi "Verification failed" khi Facebook verify webhook

**Nguyên nhân:** `FB_VERIFY_TOKEN` trong `.env` không khớp với token nhập trên Facebook.

```bash
# Kiểm tra giá trị hiện tại
grep FB_VERIFY_TOKEN .env
```

Đảm bảo token không có khoảng trắng thừa, không có ký tự đặc biệt.

---

### Lỗi "Signature mismatch" (HTTP 401)

**Nguyên nhân 1:** `FB_APP_SECRET` sai.

```bash
# Lấy app secret từ Facebook Developer Console
# Settings → Basic → App Secret → Show
```

**Nguyên nhân 2:** Body request bị biến đổi trước khi verify (ví dụ: middleware nào đó đọc và ghi lại body). Đảm bảo `file_get_contents('php://input')` đọc raw body trực tiếp.

---

### Tin nhắn không gửi được (MessengerApiException)

**Kiểm tra Page Access Token:**

```bash
curl "https://graph.facebook.com/v21.0/me?access_token=YOUR_PAGE_ACCESS_TOKEN"
```

Nếu nhận được thông tin page → token hợp lệ. Nếu nhận lỗi → token hết hạn hoặc sai.

**Page Access Token hết hạn:** Tạo System User Token không hết hạn trong **Business Manager → System Users**.

---

### Facebook không gửi webhook đến server

1. Server phải trả về HTTP 200 trong vòng **20 giây** — nếu xử lý lâu hơn, dùng queue (RabbitMQ, Redis).
2. URL webhook phải là HTTPS với cert hợp lệ (không chấp nhận self-signed).
3. Kiểm tra log của Nginx/Apache xem request có đến không.

```bash
# Xem log Nginx realtime
tail -f /var/log/nginx/access.log | grep "POST /"
```

---

### Lỗi 403 khi truy cập webhook

Apache chưa bật `mod_rewrite` hoặc `AllowOverride All` chưa được cấu hình.

```bash
a2enmod rewrite
systemctl restart apache2
```

---

### Xem log ứng dụng

```bash
tail -f logs/app.log
```

Khi `APP_DEBUG=true`, log cũng được in ra stdout. Tăng chi tiết bằng cách đặt `LOG_LEVEL=debug`.

---

*Tài liệu này tương ứng với cấu trúc code tại thời điểm khởi tạo project. Cập nhật tài liệu khi thêm handler hoặc thay đổi kiến trúc.*
