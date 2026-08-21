<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\Config;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class LoggerFactory
{
    public static function create(string $channel = 'webhook'): LoggerInterface
    {
        $logger = new Logger($channel);
        $level  = Level::fromName(Config::string('LOG_LEVEL', 'debug'));
        $path   = Config::string('LOG_PATH', 'logs/app.log');

        try {
            $logger->pushHandler(new StreamHandler($path, $level));
        } catch (\Throwable) {
            $logger->pushHandler(new NullHandler());
        }

        if (Config::bool('APP_DEBUG')) {
            try {
                $logger->pushHandler(new StreamHandler('php://stdout', $level));
            } catch (\Throwable) {}
        }

        return $logger;
    }
}
