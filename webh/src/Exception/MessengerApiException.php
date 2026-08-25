<?php

declare(strict_types=1);

namespace App\Exception;

class MessengerApiException extends WebhookException
{
    // FB rate-limit error codes (Platform + BUC)
    private const RATE_LIMIT_CODES = [4, 17, 32, 80006, 613];

    public function __construct(
        string $message,
        private readonly ?int $fbCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $fbCode ?? 0, $previous);
    }

    public function getFbCode(): ?int
    {
        return $this->fbCode;
    }

    public function isRateLimit(): bool
    {
        return $this->fbCode !== null
            && in_array($this->fbCode, self::RATE_LIMIT_CODES, true);
    }
}
