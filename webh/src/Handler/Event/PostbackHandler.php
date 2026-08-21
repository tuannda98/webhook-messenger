<?php

declare(strict_types=1);

namespace App\Handler\Event;

use App\Service\ConversationService;
use App\Service\MessengerService;
use App\Service\UserService;
use Psr\Log\LoggerInterface;

class PostbackHandler
{
    public function __construct(
        private readonly MessengerService $messenger,
        private readonly ReplyAction $action,
        private readonly ConversationService $conversation,
        private readonly UserService $userService,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(string $psid, array $postback): void
    {
        $payload = $postback['payload'] ?? '';
        $this->logger->info('Postback received', ['psid' => $psid, 'payload' => $payload]);

        ['user' => $user] = $this->userService->getOrCreate($psid);

        match ($payload) {
            'GET_STARTED' => $this->onGetStarted($psid, $user),
            'CMD_START'   => $this->onStart($psid, $user),
            'CMD_END'     => $this->conversation->endChat($psid),
            'CMD_HELP'    => $this->action->help($psid),
            default       => $this->logger->info('Unhandled postback', ['payload' => $payload]),
        };
    }

    private function onGetStarted(string $psid, array $user): void
    {
        $this->action->welcome($psid, $user['first_name'] ?? '');
    }

    private function onStart(string $psid, array $user): void
    {
        // fix #7: không còn duplicate state guard — đã nằm trong ConversationService
        $this->messenger->markSeen($psid);
        $this->messenger->typingOn($psid);
        $this->conversation->startMatching($psid, $user['gender'] ?? 'unknown');
    }
}
