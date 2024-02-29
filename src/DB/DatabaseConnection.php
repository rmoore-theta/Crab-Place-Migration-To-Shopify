<?php

namespace App\DB;

class DatabaseConnection
{
    private static $db;
    private $connection;
    private $servername;
    private $username;
    private $password;
    private $dbname;

    public function __construct()
    {
        $config= \App\App::$App->config('app');
        $this->servername = $config['host'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->dbname = $config['dbname'];
        $this->connection = new \mysqli($this->servername, $this->username, $this->password, $this->dbname);
    }

    public static function getConnection()
    {
        if (self::$db == null) {
            self::$db = new DatabaseConnection();
        }
        return self::$db->connection;
    }

    public function getDBName()
    {
        return $this->dbname;
    }
}
