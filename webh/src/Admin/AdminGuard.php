<?php

declare(strict_types=1);

namespace App\Admin;

use App\Config\Config;
use PDO;

class AdminGuard
{
    private const MAX_ATTEMPTS    = 5;
    private const LOCKOUT_MINUTES = 15;

    public function __construct(private readonly PDO $db) {}

    /**
     * Chạy toàn bộ kiểm tra bảo vệ. Gọi trước mọi logic admin.
     * Tự abort (http response + exit) nếu vi phạm bất kỳ rule nào.
     */
    public function protect(): void
    {
        $this->sendSecurityHeaders();
        $this->enforceHttps();
        $this->checkIpWhitelist();
    }

    /**
     * Kiểm tra rate limit khi user submit login form.
     * Trả về thông báo lỗi nếu đang bị khóa, null nếu được phép thử.
     */
    public function checkLoginRateLimit(): ?string
    {
        $ip   = $this->clientIp();
        $stmt = $this->db->prepare('SELECT attempts, blocked_until FROM admin_login_attempts WHERE ip = ?');
        $stmt->execute([$ip]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        if ($row['blocked_until'] !== null && strtotime($row['blocked_until']) > time()) {
            $remaining = (int) ceil((strtotime($row['blocked_until']) - time()) / 60);
            return "Quá nhiều lần thử sai. Thử lại sau {$remaining} phút.";
        }

        return null;
    }

    /**
     * Ghi nhận một lần đăng nhập thất bại.
     * Tự động block IP nếu vượt MAX_ATTEMPTS.
     */
    public function recordFailedAttempt(): void
    {
        $ip = $this->clientIp();

        $this->db->prepare(
            'INSERT INTO admin_login_attempts (ip, attempts, blocked_until)
             VALUES (?, 1, NULL)
             ON DUPLICATE KEY UPDATE
                attempts = attempts + 1,
                blocked_until = IF(
                    attempts + 1 >= ?,
                    DATE_ADD(NOW(), INTERVAL ? MINUTE),
                    NULL
                )'
        )->execute([$ip, self::MAX_ATTEMPTS, self::LOCKOUT_MINUTES]);
    }

    /**
     * Xóa bộ đếm sau khi đăng nhập thành công.
     */
    public function clearAttempts(): void
    {
        $this->db
            ->prepare('DELETE FROM admin_login_attempts WHERE ip = ?')
            ->execute([$this->clientIp()]);
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function enforceHttps(): void
    {
        if (Config::string('APP_ENV') !== 'production') {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

        if (!$isHttps) {
            $appUrl = rtrim(Config::string('APP_URL'), '/');
            $uri    = $_SERVER['REQUEST_URI'] ?? '/admin.php';
            header("Location: {$appUrl}{$uri}", true, 301);
            exit;
        }
    }

    private function checkIpWhitelist(): void
    {
        $whitelist = Config::string('ADMIN_ALLOWED_IPS');

        if ($whitelist === '') {
            return; // không giới hạn
        }

        $allowed = array_map('trim', explode(',', $whitelist));
        $ip      = $this->clientIp();

        if (!in_array($ip, $allowed, true)) {
            http_response_code(403);
            echo '403 Forbidden';
            exit;
        }
    }

    private function sendSecurityHeaders(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'none'");

        if (Config::string('APP_ENV') === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    private function clientIp(): string
    {
        // Tin tưởng X-Forwarded-For chỉ khi đứng sau reverse proxy
        if (Config::string('TRUST_PROXY') === '1') {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($forwarded !== '') {
                return trim(explode(',', $forwarded)[0]);
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
