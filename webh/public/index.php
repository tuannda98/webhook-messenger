<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Handler\Event\MessageHandler;
use App\Handler\Event\PostbackHandler;
use App\Handler\WebhookHandler;
use App\Service\LoggerFactory;
use App\Service\MessengerService;
use App\Service\SignatureVerifier;
use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();
$dotenv->required(['FB_APP_SECRET', 'FB_VERIFY_TOKEN', 'FB_PAGE_ACCESS_TOKEN']);

// Wire up dependencies
$logger    = LoggerFactory::create();
$messenger = new MessengerService();
$handler   = new WebhookHandler(
    verifier:        new SignatureVerifier(),
    messageHandler:  new MessageHandler($messenger, $logger),
    postbackHandler: new PostbackHandler($messenger, $logger),
    logger:          $logger,
);

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// GET: Facebook webhook verification challenge
if ($method === 'GET') {
    $handler->handleVerification($_GET);
    exit;
}

// POST: Incoming webhook events
if ($method === 'POST') {
    $rawBody  = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

    $handler->handleWebhook($rawBody, $signature);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed']);
