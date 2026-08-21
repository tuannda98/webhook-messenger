<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\Config;
use App\Exception\MessengerApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class MessengerService
{
    private Client $http;
    private string $baseUrl;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 30]);
        $this->baseUrl = sprintf(
            '%s/%s',
            rtrim(Config::fbApiUrl(), '/'),
            Config::fbApiVersion()
        );
    }

    public function sendText(string $recipientId, string $text): array
    {
        return $this->sendMessage($recipientId, ['text' => $text]);
    }

    public function sendQuickReplies(string $recipientId, string $text, array $quickReplies): array
    {
        return $this->sendMessage($recipientId, [
            'text' => $text,
            'quick_replies' => $quickReplies,
        ]);
    }

    public function sendTemplate(string $recipientId, array $templatePayload): array
    {
        return $this->sendMessage($recipientId, [
            'attachment' => [
                'type' => 'template',
                'payload' => $templatePayload,
            ],
        ]);
    }

    public function sendButtonTemplate(string $recipientId, string $text, array $buttons): array
    {
        return $this->sendTemplate($recipientId, [
            'template_type' => 'button',
            'text' => $text,
            'buttons' => $buttons,
        ]);
    }

    public function markSeen(string $recipientId): void
    {
        $this->sendAction($recipientId, 'mark_seen');
    }

    public function typingOn(string $recipientId): void
    {
        $this->sendAction($recipientId, 'typing_on');
    }

    public function typingOff(string $recipientId): void
    {
        $this->sendAction($recipientId, 'typing_off');
    }

    public function getUserProfile(string $userId, array $fields = ['name', 'first_name', 'last_name', 'profile_pic']): array
    {
        try {
            $response = $this->http->get("{$this->baseUrl}/{$userId}", [
                'query' => [
                    'fields' => implode(',', $fields),
                    'access_token' => Config::fbPageToken(),
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new MessengerApiException("Failed to get user profile: {$e->getMessage()}", 0, $e);
        }
    }

    private function sendMessage(string $recipientId, array $message): array
    {
        try {
            $response = $this->http->post("{$this->baseUrl}/me/messages", [
                'query' => ['access_token' => Config::fbPageToken()],
                'json' => [
                    'recipient' => ['id' => $recipientId],
                    'message' => $message,
                    'messaging_type' => 'RESPONSE',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new MessengerApiException("Failed to send message: {$e->getMessage()}", 0, $e);
        }
    }

    private function sendAction(string $recipientId, string $action): void
    {
        try {
            $this->http->post("{$this->baseUrl}/me/messages", [
                'query' => ['access_token' => Config::fbPageToken()],
                'json' => [
                    'recipient' => ['id' => $recipientId],
                    'sender_action' => $action,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new MessengerApiException("Failed to send action: {$e->getMessage()}", 0, $e);
        }
    }
}
