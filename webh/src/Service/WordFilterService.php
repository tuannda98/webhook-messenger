<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

class WordFilterService
{
    private ?array $rules = null;

    public function __construct(private readonly PDO $db) {}

    public function apply(string $text): string
    {
        $rules = $this->rules();
        if (empty($rules)) {
            return $text;
        }

        $froms = array_column($rules, 'from_word');
        $tos   = array_column($rules, 'to_word');

        return str_ireplace($froms, $tos, $text);
    }

    public function all(): array
    {
        return $this->db->query(
            'SELECT id, from_word, to_word, created_at FROM word_filters ORDER BY from_word ASC'
        )->fetchAll();
    }

    public function add(string $from, string $to): void
    {
        $this->db->prepare(
            'INSERT INTO word_filters (from_word, to_word) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE to_word = VALUES(to_word)'
        )->execute([trim($from), trim($to)]);
        $this->rules = null;
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM word_filters WHERE id = ?')->execute([$id]);
        $this->rules = null;
    }

    private function rules(): array
    {
        if ($this->rules === null) {
            $this->rules = $this->db->query(
                'SELECT from_word, to_word FROM word_filters'
            )->fetchAll();
        }
        return $this->rules;
    }
}
