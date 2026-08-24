<?php

declare(strict_types=1);

namespace App\Service;

use App\Handler\Event\ReplyAction;
use PDO;
use Psr\Log\LoggerInterface;

class InactivityService
{
    // Ngưỡng gửi cảnh báo: user im 22h55 chưa đến 23h55
    private const WARN_MINUTES = 22 * 60 + 55;
    // Ngưỡng kết thúc: user im >= 23h55
    private const END_MINUTES  = 23 * 60 + 55;

    public function __construct(
        private readonly PDO $db,
        private readonly MatchService $match,
        private readonly ReplyAction $action,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(): void
    {
        $this->processWaitRoom();
        $this->processChatRoom();
    }

    // -------------------------------------------------------------------------

    private function processWaitRoom(): void
    {
        // Kết thúc trước, cảnh báo sau — tránh warn rồi lại end trong cùng một lần chạy
        foreach ($this->findStaleWaitRoomUsers() as $row) {
            try {
                $this->match->removeFromWaitRoom($row['psid']);
                $this->action->waitRoomInactivityTerminated($row['psid']);
                $this->logger->info('Inactivity: removed from wait room', ['psid' => $row['psid']]);
            } catch (\Throwable $e) {
                $this->logger->error('Inactivity: failed to end wait room', [
                    'psid'  => $row['psid'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($this->findWarnWaitRoomUsers() as $row) {
            try {
                $this->action->waitRoomInactivityWarning($row['psid']);
                $this->markWarned($row['psid']);
                $this->logger->info('Inactivity: warned wait room user', ['psid' => $row['psid']]);
            } catch (\Throwable $e) {
                $this->logger->error('Inactivity: failed to warn wait room', [
                    'psid'  => $row['psid'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function processChatRoom(): void
    {
        foreach ($this->findStaleChatRooms() as $room) {
            try {
                $psid1   = $room['psid1'];
                $psid2   = $room['psid2'];
                $p1Stale = $room['p1_stale'];
                $p2Stale = $room['p2_stale'];

                $this->match->endChat($psid1);

                if ($p1Stale && $p2Stale) {
                    $this->action->inactivityTerminated($psid1);
                    $this->action->inactivityTerminated($psid2);
                } elseif ($p1Stale) {
                    $this->action->inactivityTerminated($psid1);
                    $this->action->partnerInactive($psid2);
                } else {
                    $this->action->partnerInactive($psid1);
                    $this->action->inactivityTerminated($psid2);
                }

                $this->logger->info('Inactivity: ended chat room', [
                    'psid1' => $psid1, 'p1_stale' => $p1Stale,
                    'psid2' => $psid2, 'p2_stale' => $p2Stale,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Inactivity: failed to end chat room', [
                    'room'  => $room,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($this->findWarnChatRoomUsers() as $row) {
            try {
                $this->action->inactivityWarning($row['psid']);
                $this->markWarned($row['psid']);
                $this->logger->info('Inactivity: warned chat room user', ['psid' => $row['psid']]);
            } catch (\Throwable $e) {
                $this->logger->error('Inactivity: failed to warn chat room', [
                    'psid'  => $row['psid'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    /** Wait room users im >= END_MINUTES */
    private function findStaleWaitRoomUsers(): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.psid
             FROM users u
             JOIN wait_room wr ON wr.psid = u.psid
             WHERE u.last_messaged_at IS NOT NULL
               AND u.last_messaged_at < NOW() - INTERVAL :mins MINUTE'
        );
        $stmt->bindValue(':mins', self::END_MINUTES, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Wait room users im >= WARN_MINUTES, < END_MINUTES, chưa warned */
    private function findWarnWaitRoomUsers(): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.psid
             FROM users u
             JOIN wait_room wr ON wr.psid = u.psid
             WHERE u.last_messaged_at IS NOT NULL
               AND u.last_messaged_at < NOW() - INTERVAL :warn MINUTE
               AND u.last_messaged_at >= NOW() - INTERVAL :end MINUTE
               AND (u.session_warned_at IS NULL OR u.session_warned_at < u.last_messaged_at)'
        );
        $stmt->bindValue(':warn', self::WARN_MINUTES, PDO::PARAM_INT);
        $stmt->bindValue(':end',  self::END_MINUTES,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Chat rooms có ít nhất 1 user im >= END_MINUTES */
    private function findStaleChatRooms(): array
    {
        $stmt = $this->db->prepare(
            'SELECT cr.psid1, cr.psid2,
                    (u1.last_messaged_at IS NOT NULL AND u1.last_messaged_at < NOW() - INTERVAL :mins MINUTE) AS p1_stale,
                    (u2.last_messaged_at IS NOT NULL AND u2.last_messaged_at < NOW() - INTERVAL :mins MINUTE) AS p2_stale
             FROM chat_room cr
             JOIN users u1 ON u1.psid = cr.psid1
             JOIN users u2 ON u2.psid = cr.psid2
             WHERE (u1.last_messaged_at IS NOT NULL AND u1.last_messaged_at < NOW() - INTERVAL :mins MINUTE)
                OR (u2.last_messaged_at IS NOT NULL AND u2.last_messaged_at < NOW() - INTERVAL :mins MINUTE)'
        );
        $stmt->bindValue(':mins', self::END_MINUTES, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Chat room users im >= WARN_MINUTES, < END_MINUTES, chưa warned */
    private function findWarnChatRoomUsers(): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.psid
             FROM users u
             JOIN chat_room cr ON (cr.psid1 = u.psid OR cr.psid2 = u.psid)
             WHERE u.last_messaged_at IS NOT NULL
               AND u.last_messaged_at < NOW() - INTERVAL :warn MINUTE
               AND u.last_messaged_at >= NOW() - INTERVAL :end MINUTE
               AND (u.session_warned_at IS NULL OR u.session_warned_at < u.last_messaged_at)'
        );
        $stmt->bindValue(':warn', self::WARN_MINUTES, PDO::PARAM_INT);
        $stmt->bindValue(':end',  self::END_MINUTES,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function markWarned(string $psid): void
    {
        $this->db->prepare(
            'UPDATE users SET session_warned_at = NOW() WHERE psid = ?'
        )->execute([$psid]);
    }
}
