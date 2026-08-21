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
        $raw     = $postback['payload'] ?? '';
        $payload = $this->normalizePayload($raw);
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

    /**
     * Chuẩn hóa payload: nếu là JSON (ManyChat, v.v.) thì extract action thành
     * plain string tương đương để match được các case chuẩn.
     */
    private function normalizePayload(string $raw): string
    {
        if (!str_starts_with(ltrim($raw), '{')) {
            return $raw;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $raw;
        }

        $action = strtolower($decoded['action'] ?? '');

        return match ($action) {
            'get_started' => 'GET_STARTED',
            'start'       => 'CMD_START',
            'end'         => 'CMD_END',
            'help'        => 'CMD_HELP',
            default       => $raw,
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
