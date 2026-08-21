<?php

declare(strict_types=1);

namespace App\Config;

class Config
{
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $value = $_ENV[$key] ?? getenv($key) ?: $default;
        self::$cache[$key] = $value;

        return $value;
    }

    public static function string(string $key, string $default = ''): string
    {
        return (string) self::get($key, $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);
        if (is_bool($value)) return $value;
        return in_array(strtolower((string) $value), ['true', '1', 'yes', 'on'], true);
    }

    // Facebook-specific helpers
    public static function fbAppId(): string       { return self::string('FB_APP_ID'); }
    public static function fbAppSecret(): string   { return self::string('FB_APP_SECRET'); }
    public static function fbVerifyToken(): string { return self::string('FB_VERIFY_TOKEN'); }
    public static function fbPageToken(): string   { return self::string('FB_PAGE_ACCESS_TOKEN'); }
    public static function fbApiVersion(): string  { return self::string('FB_GRAPH_API_VERSION', 'v21.0'); }
    public static function fbApiUrl(): string      { return self::string('FB_GRAPH_API_URL', 'https://graph.facebook.com'); }
}
