<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

class SystemConfig
{
    private array $cache = [];
    private bool $loaded = false;

    public function __construct(private readonly PDO $db) {}

    public function get(string $key, string $default = ''): string
    {
        $this->loadAll();

        return $this->cache[$key] ?? $default;
    }

    private function loadAll(): void
    {
        if ($this->loaded) {
            return;
        }

        $rows = $this->db->query('SELECT `key`, `value` FROM system_config')->fetchAll();
        foreach ($rows as $row) {
            $this->cache[$row['key']] = $row['value'];
        }

        $this->loaded = true;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $val = $this->get($key, $default ? '1' : '0');
        return in_array($val, ['1', 'true', 'yes'], true);
    }

    public function set(string $key, string $value): void
    {
        $this->db->prepare(
            'INSERT INTO system_config (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        )->execute([$key, $value]);

        $this->cache[$key] = $value;
    }
}
