#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Handler\Event\ReplyAction;
use App\Service\Database;
use App\Service\InactivityService;
use App\Service\LoggerFactory;
use App\Service\MatchService;
use App\Service\MessengerService;
use App\Service\SystemConfig;
use App\Service\WordFilterService;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();
$dotenv->required(['FB_APP_SECRET', 'FB_VERIFY_TOKEN', 'FB_PAGE_ACCESS_TOKEN', 'DB_DSN', 'DB_USER', 'DB_PASS']);

$logger    = LoggerFactory::create();
$db        = Database::connection();
$sysConfig  = new SystemConfig($db);
$wordFilter = new WordFilterService($db);
$messenger  = new MessengerService($logger);
$action     = new ReplyAction($messenger, $sysConfig, $wordFilter);
$match     = new MatchService($db, $sysConfig);

$service = new InactivityService($db, $match, $action, $logger);

$logger->info('Inactivity cleanup started');
$service->run();
$logger->info('Inactivity cleanup finished');
