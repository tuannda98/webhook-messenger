<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Handler\Event\MessageHandler;
use App\Handler\Event\PostbackHandler;
use App\Handler\Event\ReplyAction;
use App\Handler\WebhookHandler;
use App\Service\ConversationService;
use App\Service\Database;
use App\Service\LoggerFactory;
use App\Service\MatchService;
use App\Service\MessengerService;
use App\Service\SignatureVerifier;
use App\Service\SystemConfig;
use App\Service\UserService;
use App\Service\WordFilterService;
use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();
$dotenv->required(['FB_APP_SECRET', 'FB_VERIFY_TOKEN', 'FB_PAGE_ACCESS_TOKEN', 'DB_DSN', 'DB_USER', 'DB_PASS']);

// Wire up dependencies
$logger       = LoggerFactory::create();
$messenger    = new MessengerService($logger);
$db           = Database::connection();

$sysConfig    = new SystemConfig($db);
$wordFilter   = new WordFilterService($db);
$action       = new ReplyAction($messenger, $sysConfig, $wordFilter);
$match        = new MatchService($db, $sysConfig);
$userService  = new UserService($db, $messenger, $logger);
$conversation = new ConversationService($match, $action, $logger);

$handler = new WebhookHandler(
    verifier:        new SignatureVerifier(),
    messageHandler:  new MessageHandler($action, $conversation, $match, $userService, $logger),
    postbackHandler: new PostbackHandler($action, $conversation, $userService, $logger),
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
    $rawBody   = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

    $handler->handleWebhook($rawBody, $signature);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed']);
