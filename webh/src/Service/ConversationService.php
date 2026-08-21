<?php

declare(strict_types=1);

namespace App\Service;

use App\Handler\Event\ReplyAction;
use Psr\Log\LoggerInterface;

class ConversationService
{
    public function __construct(
        private readonly MatchService $match,
        private readonly ReplyAction $action,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * fix #7: state guard chuyển vào đây, cả hai handler không còn duplicate logic này.
     */
    public function startMatching(string $psid, string $gender = 'unknown'): void
    {
        $state = $this->match->getState($psid);

        if ($state === UserState::Chatting) {
            $this->action->alreadyChatting($psid);
            return;
        }

        if ($state === UserState::Waiting) {
            $this->action->alreadyWaiting($psid);
            return;
        }

        // fix #1: dùng atomic operation thay vì findPair + pairPeople riêng lẻ
        $partner = $this->match->atomicFindAndPair($psid, $gender);

        if ($partner !== null) {
            // fix #5: nếu notify thất bại, rollback DB bằng endChat
            try {
                $this->action->matched($psid);
                $this->action->matched($partner);
            } catch (\Throwable $e) {
                $this->match->endChat($psid);
                $this->logger->error('Notify failed after pairing — rolled back', [
                    'psid'    => $psid,
                    'partner' => $partner,
                    'error'   => $e->getMessage(),
                ]);
                throw $e;
            }

            $this->logger->info('Users paired', ['psid1' => $psid, 'psid2' => $partner]);
            return;
        }

        $this->match->addToWaitRoom($psid, $gender);
        $this->action->waiting($psid);
        $this->logger->info('User added to wait room', ['psid' => $psid]);
    }

    public function endChat(string $psid): void
    {
        $state = $this->match->getState($psid);

        if ($state === UserState::Chatting) {
            $partner = $this->match->endChat($psid);
            $this->action->disconnected($psid);
            if ($partner) {
                $this->action->partnerLeft($partner);
            }
            $this->logger->info('Chat ended', ['psid' => $psid, 'partner' => $partner]);
            return;
        }

        if ($state === UserState::Waiting) {
            $this->match->removeFromWaitRoom($psid);
            $this->action->leftWaitRoom($psid);
            return;
        }

        $this->action->notInChat($psid);
    }

    public function forwardMessage(string $psid, array $message): void
    {
        $partner = $this->match->getPartner($psid);

        if (!$partner) {
            return;
        }

        $this->action->forwardMessage($partner, $message);
    }
}
