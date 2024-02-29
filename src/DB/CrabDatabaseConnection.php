<?php

namespace App\DB;

use PDO;

class CrabDatabaseConnection
{
    private static $db;
    private $connection;

    private function __construct()
    {
        $config= \App\App::$App->config('app');
        $connectionString = "dblib:host={$config['cphost']}:{$config['cpport']};dbname={$config['cpdbname']}";
        // echo "\n ",date("Y, n, j, H:i:s a")," CP conection string: $connectionString user:{$config['cpusername']}\n";// pass:{$config['cppassword']}
        $this->connection = new PDO($connectionString, $config['cpusername'], $config['cppassword']);
    }

    public static function getConnection()
    {
        if(self::$db == null) {
            self::$db = new CrabDatabaseConnection();
        }
        return self::$db->connection;
    }

    public static function resetConnection()
    {
        self::$db = null;
        return self::getConnection();
    }
}
