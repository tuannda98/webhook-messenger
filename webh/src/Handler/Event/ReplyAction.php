<?php

declare(strict_types=1);

namespace App\Handler\Event;

use App\Service\MessengerService;
use App\Service\SystemConfig;

class ReplyAction
{
    public function __construct(
        private readonly MessengerService $messenger,
        private readonly SystemConfig $config,
    ) {}

    // -------------------------------------------------------------------------
    // Matching flow
    // -------------------------------------------------------------------------

    public function waiting(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get('waiting_message', "Đang tìm người chat cho bạn...\n\nGõ /end để huỷ tìm kiếm.")
        );
    }

    public function matched(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get('matched_message', "Đã kết nối! Hãy bắt đầu trò chuyện.\n\nGõ /end để kết thúc.")
        );
    }

    public function disconnected(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get('disconnected_message', "Bạn đã kết thúc cuộc trò chuyện.\n\nGõ /start để tìm người mới.")
        );
    }

    public function partnerLeft(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get('partner_left_message', "Người kia đã rời cuộc trò chuyện.\n\nGõ /start để tìm người mới.")
        );
    }

    public function leftWaitRoom(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get('left_wait_room_message', "Đã huỷ tìm kiếm.\n\nGõ /start để tìm người mới.")
        );
    }

    public function notInChat(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get('not_in_chat_message', 'Bạn chưa trong cuộc trò chuyện nào.')
        );
    }

    // -------------------------------------------------------------------------
    // Idle state
    // -------------------------------------------------------------------------

    public function welcome(string $psid, string $firstName): void
    {
        $name     = $firstName ?: $this->config->get('default_display_name', 'bạn');
        $template = $this->config->get(
            'welcome_message',
            "Xin chào {name}! 👋\n\nChào mừng bạn đến với chat ngẫu nhiên.\nGõ /start để tìm người trò chuyện!"
        );
        $this->messenger->sendText($psid, str_replace('{name}', $name, $template));
    }

    public function help(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get(
                'help_message',
                "📖 Hướng dẫn:\n\n/start — Tìm người chat ngẫu nhiên\n/end   — Kết thúc / Huỷ tìm kiếm\n/help  — Xem hướng dẫn"
            )
        );
    }

    public function alreadyChatting(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get('already_chatting_message', 'Bạn đang trong cuộc trò chuyện. Gõ /end trước.')
        );
    }

    public function alreadyWaiting(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get('already_waiting_message', 'Bạn đang chờ ghép cặp. Gõ /end để huỷ.')
        );
    }

    public function promptStart(string $psid): void
    {
        $this->messenger->sendQuickReplies(
            $psid,
            $this->config->get('prompt_start_message', 'Gõ /start để tìm người chat ngẫu nhiên! 👋'),
            [
                ['content_type' => 'text', 'title' => '/start', 'payload' => 'CMD_START'],
                ['content_type' => 'text', 'title' => '/help',  'payload' => 'CMD_HELP'],
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Forward message between users
    // -------------------------------------------------------------------------

    public function forwardMessage(string $toPsid, array $message): void
    {
        if (isset($message['attachments'])) {
            foreach ($message['attachments'] as $attachment) {
                $url = $attachment['payload']['url'] ?? null;
                if ($url === null || $url === '') {
                    continue;
                }
                $this->messenger->sendAttachment($toPsid, $attachment['type'], $url);
            }
        }

        $text = $message['text'] ?? '';
        if ($text !== '') {
            $this->messenger->sendText($toPsid, $text);
        }
    }
}
