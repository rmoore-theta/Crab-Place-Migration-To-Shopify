<?php

namespace App\Helpers;

use App\DB\DatabaseConnection;

class MigrationStatus
{
    private $connection;
    private static $singleton;
    public function __construct()
    {
        $this->connection = DatabaseConnection::getConnection();
    }

    public static function getSingleton()
    {
        if (self::$singleton == null) {
            self::$singleton = new MigrationStatus();
        }
        return self::$singleton;
    }

    public function getNextCustomer()
    {
        $sql = "SELECT * FROM cp_customer_migration_status WHERE migration_status IS NULL ORDER BY id LIMIT 1;";
        // $sql = "SELECT * FROM cp_customer_migration_status WHERE cp_customer_id = '643013';";

        try {
            $results = $this->connection->query($sql);
            return $results->fetch_assoc();
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
    }

    public function getNextCustomerNotes()
    {
        $sql = "SELECT * FROM cp_customer_migration_status WHERE notes IS NULL ORDER BY id LIMIT 1;";

        try {
            $results = $this->connection->query($sql);
            return $results->fetch_assoc();
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
    }

    public function insertCustomerLocally($id)
    {
        $sql = "INSERT IGNORE INTO `cp_customer_migration_status` (`cp_customer_id`) VALUES ('$id');";
        try {
            $results = $this->connection->query($sql);
            if(!$results) {
                echo "$sql\n";
            }
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
    }

    public function updateCustomerLocally($id, $shopify_customer_id, $statusMessage = 'success')
    {
        $sql = "UPDATE `cp_customer_migration_status` SET `shopify_customer_id` = '{$shopify_customer_id}', `migration_status` = '{$statusMessage}' WHERE `cp_customer_migration_status`.`cp_customer_id` = {$id};";
        try {
            $results = $this->connection->query($sql);
            if(!$results) {
                echo "$sql\n";
            } else {
                // var_dump('updateCustomerLocally $results:', $results, $sql);
                return true;
            }
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
        return false;
    }

    public function getErrorCustomers($error)
    {
        // $sql = "SELECT `cp_customer_id` FROM `cp_customer_migration_status` WHERE `shopify_customer_id` = '$error';";
        $sql = "SELECT `cp_customer_id` FROM `cp_customer_migration_status` WHERE `shopify_customer_id` LIKE '%$error%';";
        // $sql = "SELECT * FROM cp_customer_migration_status WHERE cp_customer_id = '643013';";

        try {
            $results = $this->connection->query($sql);
            return $results->fetch_all();
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
    }

    public function showErrors()
    {
        $sql = "SELECT DISTINCT(`shopify_customer_id`), count(`shopify_customer_id`) FROM `cp_customer_migration_status` WHERE `shopify_customer_id` LIKE '%error%' GROUP BY `shopify_customer_id` ORDER BY count(`shopify_customer_id`);";
        // $sql = "SELECT DISTINCT(`migration_status`), count(`migration_status`) FROM `cp_customer_migration_status` WHERE `migration_status` LIKE '%error%' GROUP BY `migration_status` ORDER BY count(`migration_status`);";

        try {
            $results = $this->connection->query($sql);
            $this->printCountDescription($results);
            // return $results->fetch_all();
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
    }

    public function showOrderErrors()
    {
        $sql = "SELECT DISTINCT(`migration_status`), count(`migration_status`) FROM `cp_order_migration_status` WHERE `migration_status` LIKE '%error%' GROUP BY `migration_status` ORDER BY count(`migration_status`);";
        try {
            $results = $this->connection->query($sql);
            $this->printCountDescription($results);
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
    }


    public function printCountDescription($results)
    {
        foreach($results as $k => $v) {
            var_dump($v);
            // echo "\n\n";
        }
    }

    public function getCustomerByCPId($id)
    {
        $sql = "SELECT shopify_customer_id FROM cp_customer_migration_status WHERE cp_customer_id = '$id';";

        try {
            $results = $this->connection->query($sql);
            return $results->fetch_column();
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
    }

    public function updateShopifyTags($customerId, $tags)
    {

    }

    public function updateCustomerNotesLocally($id, $notes)
    {
        $sql = "UPDATE `cp_customer_migration_status` SET `notes` = '{$notes}' WHERE `cp_customer_migration_status`.`cp_customer_id` = {$id};";
        try {
            $results = $this->connection->query($sql);
            if(!$results) {
                echo "$sql\n";
            } else {
                // var_dump('updateCustomerLocally $results:', $results, $sql);
                return true;
            }
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
        return false;
    }

    public function insertOrderLocally($cp_order_id)
    {
        $sql = "INSERT INTO `cp_order_migration_status` (`cp_order_id`) VALUES ('$cp_order_id');";
        try {
            $results = $this->connection->query($sql);
            if(!$results) {
                echo "$sql\n";
            }
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
    }

    public function updateOrderLocally($id, $shopify_order_id, $statusMessage = 'success')
    {
        $sql = "UPDATE `cp_order_migration_status` SET `shopify_order_id` = '{$shopify_order_id}', `migration_status` = '{$statusMessage}' WHERE `cp_order_migration_status`.`cp_order_id` = {$id};";
        try {
            $results = $this->connection->query($sql);
            if(!$results) {
                echo "$sql\n";
            } else {
                // var_dump('updateCustomerLocally $results:', $results, $sql);
                return true;
            }
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
        return false;
    }

    public function updateOrderNotesLocally($id, $notes)
    {
        $sql = "UPDATE `cp_order_migration_status` SET `notes` = '{$notes}' WHERE `cp_order_migration_status`.`cp_order_id` = {$id};";
        try {
            $results = $this->connection->query($sql);
            if(!$results) {
                echo "$sql\n";
            } else {
                // var_dump('updateCustomerLocally $results:', $results, $sql);
                return true;
            }
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
        return false;
    }

    public function getNextOrder()
    {
        $sql = "SELECT cp_order_id FROM cp_order_migration_status WHERE migration_status IS NULL ORDER BY id;";

        try {
            $results = $this->connection->query($sql);
            return $results->fetch_all();
            // return $results->fetch_assoc();
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
    }
    public function getNextOrderFix()
    {
        $sql = "SELECT cp_order_id FROM cp_order_migration_status WHERE migration_status  LIKE '%error%'  ORDER BY id;";

        try {
            $results = $this->connection->query($sql);
            return $results->fetch_all();
            // return $results->fetch_assoc();
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
    }
    public function getErrorOrders($error)
    {
        $sql = "SELECT * FROM `cp_order_migration_status` WHERE `migration_status` LIKE '%$error%';";
        try {
            $results = $this->connection->query($sql);
            return $results->fetch_all();
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
    }
    public function getErrorOrdersIds($error)
    {
        $sql = "SELECT `cp_order_id` FROM `cp_order_migration_status` WHERE `shopify_order_id` LIKE '%$error%';";
        try {
            $results = $this->connection->query($sql);
            return $results->fetch_all();
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
    }

    public function getNextOrderUpdate()
    {
        $sql = "SELECT cp_order_id, shopify_order_id FROM cp_order_migration_status WHERE `notes` IS NULL AND `shopify_order_id` != 'error' ORDER BY id LIMIT 10000;";

        try {
            $results = $this->connection->query($sql);
            return $results->fetch_all();
            // return $results->fetch_assoc();
        } catch (\mysqli_sql_exception $e) {
            echo "\nMysql error code: {$e->getCode()}, message: {$e->getMessage()}\n\n";
        }
    }
}
