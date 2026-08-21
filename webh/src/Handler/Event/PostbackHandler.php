<?php

declare(strict_types=1);

namespace App\Handler\Event;

use App\Service\MessengerService;
use Psr\Log\LoggerInterface;

class PostbackHandler
{
    public function __construct(
        private readonly MessengerService $messenger,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(string $senderId, array $postback): void
    {
        $payload = $postback['payload'] ?? '';
        $this->logger->info('Postback received', ['sender' => $senderId, 'payload' => $payload]);

        match ($payload) {
            'GET_STARTED' => $this->onGetStarted($senderId),
            'SERVICE_A'   => $this->messenger->sendText($senderId, "Bạn đã chọn Dịch vụ A. Chúng tôi sẽ liên hệ tư vấn chi tiết!"),
            'SERVICE_B'   => $this->messenger->sendText($senderId, "Bạn đã chọn Dịch vụ B. Chúng tôi sẽ liên hệ tư vấn chi tiết!"),
            'CONTACT'     => $this->onContact($senderId),
            default       => $this->messenger->sendText($senderId, "Cảm ơn bạn! Chúng tôi sẽ hỗ trợ bạn sớm nhất."),
        };
    }

    private function onGetStarted(string $senderId): void
    {
        $this->messenger->sendText(
            $senderId,
            "Chào mừng bạn! 🎉\n\nChúng tôi rất vui được phục vụ bạn. Hãy để lại tin nhắn và chúng tôi sẽ hỗ trợ bạn ngay!"
        );
    }

    private function onContact(string $senderId): void
    {
        $this->messenger->sendText(
            $senderId,
            "📞 Thông tin liên hệ:\n\n" .
            "☎️ Hotline: 1900 xxxx\n" .
            "📧 Email: contact@example.com\n" .
            "🏢 Địa chỉ: 123 Đường ABC, TP.HCM\n\n" .
            "Hoặc để lại tin nhắn, chúng tôi sẽ liên hệ lại!"
        );
    }
}
