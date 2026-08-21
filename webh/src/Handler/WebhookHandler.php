<?php

declare(strict_types=1);

namespace App\Handler;

use App\Config\Config;
use App\Exception\SignatureVerificationException;
use App\Handler\Event\MessageHandler;
use App\Handler\Event\PostbackHandler;
use App\Service\SignatureVerifier;
use Psr\Log\LoggerInterface;

class WebhookHandler
{
    public function __construct(
        private readonly SignatureVerifier $verifier,
        private readonly MessageHandler $messageHandler,
        private readonly PostbackHandler $postbackHandler,
        private readonly LoggerInterface $logger,
    ) {}

    public function handleVerification(array $query): void
    {
        if (
            ($query['hub_mode'] ?? '') === 'subscribe'
            && ($query['hub_verify_token'] ?? '') === Config::fbVerifyToken()
        ) {
            echo $query['hub_challenge'] ?? '';
            exit(0);
        }

        http_response_code(403);
        echo json_encode(['error' => 'Verification failed']);
        exit(0);
    }

    public function handleWebhook(string $rawBody, string $signature): void
    {
        try {
            $this->verifier->verify($rawBody, $signature);
        } catch (SignatureVerificationException $e) {
            $this->logger->warning('Signature verification failed', ['error' => $e->getMessage()]);
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $payload = json_decode($rawBody, true);

        if (!$payload || ($payload['object'] ?? '') !== 'page') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid payload']);
            return;
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                $this->dispatchEvent($event);
            }
        }

        echo json_encode(['status' => 'ok']);
    }

    private function dispatchEvent(array $event): void
    {
        $senderId = $event['sender']['id'] ?? '';

        if (!$senderId) {
            return;
        }

        try {
            if (isset($event['message'])) {
                $this->messageHandler->handle($senderId, $event['message']);
            } elseif (isset($event['postback'])) {
                $this->postbackHandler->handle($senderId, $event['postback']);
            } else {
                $this->logger->debug('Unhandled event type', ['event' => array_keys($event)]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Event dispatch error', [
                'sender' => $senderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
