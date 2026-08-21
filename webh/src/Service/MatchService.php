<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

class MatchService
{
    public function __construct(
        private readonly PDO $db,
        private readonly SystemConfig $config,
    ) {}

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------

    public function getState(string $psid): UserState
    {
        if ($this->isInChatRoom($psid)) return UserState::Chatting;
        if ($this->isInWaitRoom($psid))  return UserState::Waiting;
        return UserState::Idle;
    }

    // -------------------------------------------------------------------------
    // Wait room
    // -------------------------------------------------------------------------

    public function addToWaitRoom(string $psid, string $gender = 'unknown'): void
    {
        $this->db->prepare(
            'INSERT INTO wait_room (psid, gender) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE gender = VALUES(gender), created_at = NOW()'
        )->execute([$psid, $gender]);
    }

    public function removeFromWaitRoom(string $psid): void
    {
        $this->db->prepare('DELETE FROM wait_room WHERE psid = ?')->execute([$psid]);
    }

    // -------------------------------------------------------------------------
    // Atomic find + pair (fix #1 race condition)
    // -------------------------------------------------------------------------

    /**
     * Tìm và ghép cặp trong một transaction duy nhất với SELECT FOR UPDATE.
     * Trả về psid partner nếu ghép thành công, null nếu wait_room trống.
     */
    public function atomicFindAndPair(string $psid, string $ownGender = 'unknown'): ?string
    {
        $this->db->beginTransaction();

        try {
            $partner = $this->findPairLocked($psid, $ownGender);

            if ($partner === null) {
                $this->db->commit();
                return null;
            }

            $this->removeFromWaitRoom($psid);
            $this->removeFromWaitRoom($partner);
            $this->db->prepare('INSERT INTO chat_room (psid1, psid2) VALUES (?, ?)')->execute([$psid, $partner]);
            $this->db->prepare('INSERT INTO chat_history (psid1, psid2) VALUES (?, ?)')->execute([$psid, $partner]);

            $this->db->commit();
            return $partner;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Chat room
    // -------------------------------------------------------------------------

    public function getPartner(string $psid): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT psid1, psid2 FROM chat_room WHERE psid1 = ? OR psid2 = ? LIMIT 1'
        );
        $stmt->execute([$psid, $psid]);
        $row = $stmt->fetch();

        if (!$row) return null;

        return $row['psid1'] === $psid ? $row['psid2'] : $row['psid1'];
    }

    public function endChat(string $psid): ?string
    {
        $partner = $this->getPartner($psid);

        $this->db->prepare(
            'DELETE FROM chat_room WHERE psid1 = ? OR psid2 = ?'
        )->execute([$psid, $psid]);

        // fix #2: dùng $psid để tìm history, không dùng $partner (có thể null khi race)
        $this->db->prepare(
            'UPDATE chat_history SET ended_at = NOW()
             WHERE ended_at IS NULL AND (psid1 = ? OR psid2 = ?)
             ORDER BY started_at DESC LIMIT 1'
        )->execute([$psid, $psid]);

        return $partner;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Tìm partner trong transaction, dùng FOR UPDATE để lock row (fix #1).
     * Bỏ fallback step 3 (fix #3): nếu queue chỉ có recent partners thì user chờ thêm.
     */
    private function findPairLocked(string $psid, string $ownGender): ?string
    {
        $matchByGender  = $this->config->bool('match_by_gender', true);
        $excludeCount   = (int) $this->config->get('exclude_recent_count', '1');
        $recentPartners = $this->getRecentPartners($psid, $excludeCount);

        $oppositeGender = match ($ownGender) {
            'male'   => 'female',
            'female' => 'male',
            default  => null,
        };

        // Bước 1: khác giới, tránh recent
        if ($matchByGender && $oppositeGender !== null) {
            $row = $this->queryWaitRoom($psid, $recentPartners, $oppositeGender, true);
            if ($row) return $row['psid'];
        }

        // Bước 2: random, tránh recent
        $row = $this->queryWaitRoom($psid, $recentPartners, null, true);
        if ($row) return $row['psid'];

        return null;
    }

    private function queryWaitRoom(string $psid, array $exclude, ?string $gender, bool $forUpdate = false): array|false
    {
        $exclude[]    = $psid;
        $placeholders = implode(',', array_fill(0, count($exclude), '?'));
        $lock         = $forUpdate ? ' FOR UPDATE' : '';

        if ($gender !== null) {
            $stmt = $this->db->prepare(
                "SELECT psid FROM wait_room
                 WHERE psid NOT IN ({$placeholders}) AND gender = ?
                 ORDER BY created_at ASC LIMIT 1{$lock}"
            );
            $stmt->execute([...$exclude, $gender]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT psid FROM wait_room
                 WHERE psid NOT IN ({$placeholders})
                 ORDER BY created_at ASC LIMIT 1{$lock}"
            );
            $stmt->execute($exclude);
        }

        return $stmt->fetch();
    }

    private function getRecentPartners(string $psid, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        // fix #6: bind $limit as integer, không để PDO cast thành string
        $stmt = $this->db->prepare(
            'SELECT CASE WHEN psid1 = ? THEN psid2 ELSE psid1 END AS partner
             FROM chat_history
             WHERE psid1 = ? OR psid2 = ?
             ORDER BY started_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $psid);
        $stmt->bindValue(2, $psid);
        $stmt->bindValue(3, $psid);
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_column($stmt->fetchAll(), 'partner');
    }

    private function isInWaitRoom(string $psid): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM wait_room WHERE psid = ? LIMIT 1');
        $stmt->execute([$psid]);
        return (bool) $stmt->fetch();
    }

    private function isInChatRoom(string $psid): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM chat_room WHERE psid1 = ? OR psid2 = ? LIMIT 1'
        );
        $stmt->execute([$psid, $psid]);
        return (bool) $stmt->fetch();
    }
}
