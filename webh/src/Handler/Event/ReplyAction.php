<?php

declare(strict_types=1);

namespace App\Handler\Event;

use App\Service\MessengerService;
use App\Service\SystemConfig;
use App\Service\WordFilterService;

class ReplyAction
{
    public function __construct(
        private readonly MessengerService $messenger,
        private readonly SystemConfig $config,
        private readonly WordFilterService $wordFilter,
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
    // Inactivity notifications
    // -------------------------------------------------------------------------

    public function inactivityWarning(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get(
                'inactivity_warning_message',
                "⚠️ Bạn không hoạt động gần 23 giờ.\n\nCuộc trò chuyện sẽ tự kết thúc sau 1 giờ nếu không có tin nhắn mới."
            )
        );
    }

    public function inactivityTerminated(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get(
                'inactivity_terminated_message',
                "⏰ Cuộc trò chuyện đã kết thúc do không hoạt động.\n\nGõ /start để tìm người mới."
            )
        );
    }

    public function partnerInactive(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get(
                'partner_inactive_message',
                "👤 Người kia không còn hoạt động. Cuộc trò chuyện đã kết thúc.\n\nGõ /start để tìm người mới."
            )
        );
    }

    public function waitRoomInactivityWarning(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get(
                'wait_room_inactivity_warning_message',
                "⚠️ Bạn không hoạt động gần 23 giờ.\n\nBạn sẽ bị xóa khỏi hàng chờ sau 1 giờ nếu không có tin nhắn mới."
            )
        );
    }

    public function waitRoomInactivityTerminated(string $psid): void
    {
        $this->messenger->sendText(
            $psid,
            $this->config->get(
                'wait_room_inactivity_terminated_message',
                "⏰ Đã xóa khỏi hàng chờ do không hoạt động.\n\nGõ /start để tìm người mới."
            )
        );
    }

    // -------------------------------------------------------------------------
    // Forward message between users
    // -------------------------------------------------------------------------

    public function forwardMessage(string $toPsid, array $message, ?string $fromPsid = null): void
    {
        if (isset($message['attachments'])) {
            foreach ($message['attachments'] as $attachment) {
                $type = $attachment['type'] ?? '';
                $url  = $attachment['payload']['url'] ?? null;

                if ($url === null || $url === '') {
                    continue;
                }

                $configKey = match ($type) {
                    'image' => 'allow_attachment_image',
                    'video' => 'allow_attachment_video',
                    'audio' => 'allow_attachment_audio',
                    'file'  => 'allow_attachment_file',
                    default => null,
                };

                if ($configKey !== null && !$this->config->bool($configKey, true)) {
                    if ($fromPsid !== null) {
                        $this->mediaBlocked($fromPsid, $type);
                    }
                    continue;
                }

                $this->messenger->sendAttachment($toPsid, $type, $url);
            }
        }

        $text = $message['text'] ?? '';
        if ($text !== '') {
            $this->messenger->sendText($toPsid, $this->wordFilter->apply($text));
        }
    }

    private function mediaBlocked(string $psid, string $type): void
    {
        $label = match ($type) {
            'image' => 'ảnh',
            'video' => 'video',
            'audio' => 'âm thanh',
            'file'  => 'file',
            default => 'nội dung này',
        };

        $this->messenger->sendText(
            $psid,
            $this->config->get(
                "media_blocked_{$type}_message",
                "⚠️ Không thể gửi {$label} trong cuộc trò chuyện này."
            )
        );
    }
}
