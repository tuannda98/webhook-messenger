<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\Config;
use PDO;

class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new PDO(
                dsn:      Config::string('DB_DSN'),
                username: Config::string('DB_USER'),
                password: Config::string('DB_PASS'),
                options:  [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ],
            );
        }

        return self::$instance;
    }
}
