<?php

declare(strict_types=1);

namespace App\Handler\Event;

use App\Service\MessengerService;
use Psr\Log\LoggerInterface;

class MessageHandler
{
    public function __construct(
        private readonly MessengerService $messenger,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(string $senderId, array $message): void
    {
        // Ignore echo messages (messages sent by the page itself)
        if ($message['is_echo'] ?? false) {
            return;
        }

        $this->messenger->markSeen($senderId);
        $this->messenger->typingOn($senderId);

        if (isset($message['quick_reply'])) {
            $this->handleQuickReply($senderId, $message['quick_reply']['payload']);
            return;
        }

        if (isset($message['attachments'])) {
            $this->handleAttachment($senderId, $message['attachments']);
            return;
        }

        $text = trim($message['text'] ?? '');

        if ($text === '') {
            return;
        }

        $this->logger->info('Incoming message', ['sender' => $senderId, 'text' => $text]);

        $this->handleText($senderId, $text);
    }

    private function handleText(string $senderId, string $text): void
    {
        $lower = mb_strtolower($text, 'UTF-8');

        match (true) {
            in_array($lower, ['hi', 'hello', 'xin chào', 'chào']) => $this->sendGreeting($senderId),
            str_contains($lower, 'giờ làm việc') || str_contains($lower, 'giờ mở cửa') => $this->sendWorkingHours($senderId),
            str_contains($lower, 'menu') || str_contains($lower, 'dịch vụ') => $this->sendMenu($senderId),
            default => $this->sendDefaultReply($senderId, $text),
        };
    }

    private function handleQuickReply(string $senderId, string $payload): void
    {
        $this->logger->info('Quick reply', ['sender' => $senderId, 'payload' => $payload]);

        match ($payload) {
            'GET_STARTED' => $this->sendGreeting($senderId),
            'WORKING_HOURS' => $this->sendWorkingHours($senderId),
            'MENU' => $this->sendMenu($senderId),
            default => $this->messenger->sendText($senderId, "Cảm ơn bạn đã phản hồi! Chúng tôi sẽ liên hệ lại sớm."),
        };
    }

    private function handleAttachment(string $senderId, array $attachments): void
    {
        $type = $attachments[0]['type'] ?? 'unknown';
        $this->messenger->sendText($senderId, "Cảm ơn bạn đã gửi {$type}. Chúng tôi sẽ xem xét và phản hồi sớm!");
    }

    private function sendGreeting(string $senderId): void
    {
        $this->messenger->sendText(
            $senderId,
            "Xin chào! 👋 Chào mừng bạn đến với trang của chúng tôi.\n\nBạn cần hỗ trợ gì hôm nay?"
        );

        $this->messenger->sendQuickReplies($senderId, 'Chọn một trong các mục dưới:', [
            ['content_type' => 'text', 'title' => '🕐 Giờ làm việc', 'payload' => 'WORKING_HOURS'],
            ['content_type' => 'text', 'title' => '📋 Dịch vụ', 'payload' => 'MENU'],
            ['content_type' => 'text', 'title' => '📞 Liên hệ', 'payload' => 'CONTACT'],
        ]);
    }

    private function sendWorkingHours(string $senderId): void
    {
        $this->messenger->sendText(
            $senderId,
            "🕐 Giờ làm việc của chúng tôi:\n\n" .
            "📅 Thứ 2 - Thứ 6: 8:00 - 17:30\n" .
            "📅 Thứ 7: 8:00 - 12:00\n" .
            "📅 Chủ nhật: Nghỉ\n\n" .
            "Nếu cần hỗ trợ ngoài giờ, vui lòng để lại tin nhắn!"
        );
    }

    private function sendMenu(string $senderId): void
    {
        $this->messenger->sendButtonTemplate(
            $senderId,
            "📋 Các dịch vụ của chúng tôi:",
            [
                ['type' => 'postback', 'title' => 'Dịch vụ A', 'payload' => 'SERVICE_A'],
                ['type' => 'postback', 'title' => 'Dịch vụ B', 'payload' => 'SERVICE_B'],
                ['type' => 'postback', 'title' => 'Liên hệ tư vấn', 'payload' => 'CONTACT'],
            ]
        );
    }

    private function sendDefaultReply(string $senderId, string $text): void
    {
        $this->messenger->sendText(
            $senderId,
            "Cảm ơn bạn đã nhắn tin! Chúng tôi đã nhận được tin nhắn của bạn và sẽ phản hồi trong thời gian sớm nhất. 😊"
        );
    }
}
