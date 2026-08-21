<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use Psr\Log\LoggerInterface;

class UserService
{
    public function __construct(
        private readonly PDO $db,
        private readonly MessengerService $messenger,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Trả về ['user' => [...], 'is_new' => bool].
     * is_new = true khi user chưa từng tương tác (lần đầu tiên).
     */
    public function getOrCreate(string $psid): array
    {
        $user = $this->find($psid);

        if ($user !== null) {
            return ['user' => $user, 'is_new' => false];
        }

        return ['user' => $this->fetchAndSave($psid), 'is_new' => true];
    }

    public function find(string $psid): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE psid = ?');
        $stmt->execute([$psid]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function addPoints(string $psid, int $points): void
    {
        $this->db->prepare(
            'UPDATE users SET points = points + ? WHERE psid = ?'
        )->execute([$points, $psid]);
    }

    // -------------------------------------------------------------------------

    private function fetchAndSave(string $psid): array
    {
        try {
            $profile = $this->messenger->getUserProfile($psid, [
                'name', 'first_name', 'last_name', 'profile_pic',
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch FB profile', [
                'psid'  => $psid,
                'error' => $e->getMessage(),
            ]);
            $profile = [];
        }

        $name       = $profile['name']        ?? '';
        $firstName  = $profile['first_name']  ?? '';
        $profilePic = $profile['profile_pic'] ?? null;
        $gender     = $this->normalizeGender($profile['gender'] ?? '');

        $this->db->prepare(
            'INSERT INTO users (psid, name, first_name, profile_pic, gender)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                name        = VALUES(name),
                first_name  = VALUES(first_name),
                profile_pic = VALUES(profile_pic),
                gender      = VALUES(gender)'
        )->execute([$psid, $name, $firstName, $profilePic, $gender]);

        $this->logger->info('User profile saved', ['psid' => $psid, 'name' => $name]);

        return [
            'psid'        => $psid,
            'name'        => $name,
            'first_name'  => $firstName,
            'profile_pic' => $profilePic,
            'gender'      => $gender,
            'points'      => 0,
        ];
    }

    private function normalizeGender(string $gender): string
    {
        return match (strtolower($gender)) {
            'male'   => 'male',
            'female' => 'female',
            default  => 'unknown',
        };
    }
}
