<?php

declare(strict_types=1);

namespace App\Handler\Event;

use App\Service\ConversationService;
use App\Service\MatchService;
use App\Service\MessengerService;
use App\Service\UserService;
use App\Service\UserState;
use Psr\Log\LoggerInterface;

class MessageHandler
{
    private const COMMANDS = ['/start', '/end', '/help'];

    public function __construct(
        private readonly MessengerService $messenger,
        private readonly ReplyAction $action,
        private readonly ConversationService $conversation,
        private readonly MatchService $match,
        private readonly UserService $userService,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(string $psid, array $message): void
    {
        if ($message['is_echo'] ?? false) {
            return;
        }

        ['user' => $user, 'is_new' => $isNew] = $this->userService->getOrCreate($psid);

        $command = $this->extractCommand($message);
        $state   = $this->match->getState($psid);

        $this->logger->info('Message received', [
            'psid'    => $psid,
            'state'   => $state->value,
            'command' => $command,
        ]);

        if ($command !== null) {
            $this->handleCommand($psid, $command, $user);
            return;
        }

        $this->handleByState($psid, $message, $state);
    }

    // -------------------------------------------------------------------------

    private function handleCommand(string $psid, string $command, array $user): void
    {
        match ($command) {
            // fix #7: state guard đã nằm trong ConversationService::startMatching
            '/start' => $this->onStart($psid, $user),
            '/end'   => $this->conversation->endChat($psid),
            '/help'  => $this->action->help($psid),
            default  => null,
        };
    }

    private function onStart(string $psid, array $user): void
    {
        $this->messenger->markSeen($psid);
        $this->messenger->typingOn($psid);
        $this->conversation->startMatching($psid, $user['gender'] ?? 'unknown');
    }

    private function handleByState(string $psid, array $message, UserState $state): void
    {
        match ($state) {
            UserState::Chatting => $this->onChatMessage($psid, $message),
            UserState::Waiting  => null,
            UserState::Idle     => $this->action->promptStart($psid),
        };
    }

    private function onChatMessage(string $psid, array $message): void
    {
        $this->messenger->markSeen($psid);
        $this->conversation->forwardMessage($psid, $message);
    }

    private function extractCommand(array $message): ?string
    {
        $payload = $message['quick_reply']['payload'] ?? '';
        if (str_starts_with($payload, 'CMD_')) {
            return '/' . strtolower(substr($payload, 4));
        }

        $text = strtolower(trim($message['text'] ?? ''));

        return in_array($text, self::COMMANDS, true) ? $text : null;
    }
}
