<?php

declare(strict_types=1);

namespace App\Admin;

use PDO;

class StatsService
{
    public function __construct(private readonly PDO $db) {}

    public function overview(): array
    {
        return [
            'total_users'      => $this->scalar('SELECT COUNT(*) FROM users'),
            'users_today'      => $this->scalar("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()"),
            'waiting'          => $this->scalar('SELECT COUNT(*) FROM wait_room'),
            'chatting_pairs'   => $this->scalar('SELECT COUNT(*) FROM chat_room') / 2,
            'total_sessions'   => $this->scalar('SELECT COUNT(*) FROM chat_history'),
            'sessions_today'   => $this->scalar("SELECT COUNT(*) FROM chat_history WHERE DATE(started_at) = CURDATE()"),
            'sessions_7d'      => $this->scalar("SELECT COUNT(*) FROM chat_history WHERE started_at >= NOW() - INTERVAL 7 DAY"),
            'sessions_30d'     => $this->scalar("SELECT COUNT(*) FROM chat_history WHERE started_at >= NOW() - INTERVAL 30 DAY"),
            'avg_duration_min' => $this->avgDuration(),
        ];
    }

    public function recentSessions(int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                h.id,
                h.psid1, u1.name AS name1,
                h.psid2, u2.name AS name2,
                h.started_at, h.ended_at,
                TIMESTAMPDIFF(SECOND, h.started_at, h.ended_at) AS duration_sec
             FROM chat_history h
             LEFT JOIN users u1 ON u1.psid = h.psid1
             LEFT JOIN users u2 ON u2.psid = h.psid2
             ORDER BY h.started_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function dailySessionsChart(int $days = 14): array
    {
        $stmt = $this->db->prepare(
            'SELECT DATE(started_at) AS date, COUNT(*) AS count
             FROM chat_history
             WHERE started_at >= NOW() - INTERVAL ? DAY
             GROUP BY DATE(started_at)
             ORDER BY date ASC'
        );
        $stmt->bindValue(1, $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function topUsers(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.name, u.psid, u.points,
                    COUNT(h.id) AS session_count
             FROM users u
             LEFT JOIN chat_history h ON h.psid1 = u.psid OR h.psid2 = u.psid
             GROUP BY u.psid
             ORDER BY session_count DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function waitRoomUsers(): array
    {
        return $this->db->query(
            'SELECT wr.psid, u.name, wr.gender, wr.created_at,
                    u.last_messaged_at,
                    TIMESTAMPDIFF(SECOND, wr.created_at, NOW())       AS waiting_sec,
                    TIMESTAMPDIFF(SECOND, u.last_messaged_at, NOW())   AS idle_sec
             FROM wait_room wr
             LEFT JOIN users u ON u.psid = wr.psid
             ORDER BY wr.created_at ASC'
        )->fetchAll();
    }

    public function activeChatRooms(): array
    {
        return $this->db->query(
            'SELECT cr.id, cr.created_at,
                    TIMESTAMPDIFF(SECOND, cr.created_at, NOW()) AS duration_sec,
                    cr.psid1, u1.name AS name1, u1.last_messaged_at AS lm1,
                    TIMESTAMPDIFF(SECOND, u1.last_messaged_at, NOW()) AS idle1_sec,
                    cr.psid2, u2.name AS name2, u2.last_messaged_at AS lm2,
                    TIMESTAMPDIFF(SECOND, u2.last_messaged_at, NOW()) AS idle2_sec
             FROM chat_room cr
             LEFT JOIN users u1 ON u1.psid = cr.psid1
             LEFT JOIN users u2 ON u2.psid = cr.psid2
             ORDER BY cr.created_at DESC'
        )->fetchAll();
    }

    private function avgDuration(): float
    {
        $val = $this->scalar(
            'SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, ended_at))
             FROM chat_history WHERE ended_at IS NOT NULL'
        );
        return $val ? round((float) $val / 60, 1) : 0.0;
    }

    private function scalar(string $sql): mixed
    {
        return $this->db->query($sql)->fetchColumn();
    }
}
