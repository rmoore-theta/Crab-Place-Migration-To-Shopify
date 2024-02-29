<?php

namespace App\Helpers;

use App\DB\CrabDatabaseConnection;

use PDO;
use PDOException;

class OrderMigration
{
    private $crabConnection;

    public function __construct()
    {
        $this->crabConnection = CrabDatabaseConnection::getConnection();
    }

    public function getCompletedOrdersToMigrate($orderIds)
    {
        $sql = "SELECT * FROM Crab_Place_Dev.dbo.CompletedOrders WHERE orderId IN ($orderIds)";//otax, orderid 
        try {
            $results = $this->crabConnection->query($sql, PDO::FETCH_NAMED);
            return  $results->fetchAll();
        } catch (PDOException $e) {
            echo "PDOException error message: {$e->getMessage()}\nCode:{$e->getCode()}\n";
        }
    }

    public function getOrderItems($orderId)
    {
        $sql = "SELECT catalogid, numitems, itemname, unitprice, unitweight, features, partnumber, extprice, extweight, cost FROM Crab_Place_Legacy.dbo.oitems WHERE orderid = '$orderId'";
        try {
            $results = $this->crabConnection->query($sql, PDO::FETCH_NAMED);
            return  $results->fetchAll();
        } catch (PDOException $e) {
            echo "PDOException error message: {$e->getMessage()}\nCode:{$e->getCode()}\n";
        }
    }

    public function getOrder($orderId)
    {
        $sql = "SELECT * FROM Crab_Place_Legacy.dbo.orders WHERE orderid = '$orderId'";//otax, orderid 
        try {
            $results = $this->crabConnection->query($sql, PDO::FETCH_NAMED);
            return  $results->fetch();
        } catch (PDOException $e) {
            echo "PDOException error message: {$e->getMessage()}\nCode:{$e->getCode()}\n";
        }
    }

    public function shopifyOrderItemsFormat($orderId)
    {
        $orderLines = $this->getOrderItems($orderId);
        $order = ["order"];
        foreach($orderLines as $k => $line){
            $lineItem = [];
            $lineItem["title"] = $line['itemname'];
            $lineItem["price"] = $line['unitprice'];
            $lineItem["grams"] = $line['unitweight'];
            $lineItem["quantity"] = $line['numitems'];
            $order["order"]["line_items"][$k] = $lineItem;
        }
        return $order;
    }

    public function formatBillingAddress($order)
    {
        $formatted = [];
        if(isset($order['ofirstname']) && !empty($order['ofirstname'])) {
            $formatted["first_name"] = $order['ofirstname'];
        }
        if(isset($order['olastname']) && !empty($order['olastname'])) {
            $formatted["last_name"] = $order['olastname'];
        }
        if(isset($order['ophone']) && !empty($order['ophone'])) {
            $formatted["phone"] = $order['ophone'];
        }
        if(isset($order['ostate']) && !empty($order['ostate'])) {
            $formatted["province"] = $order['ostate'];
        }
        if(isset($order['opostcode']) && !empty($order['opostcode'])) {
            $formatted["zip"] = $order['opostcode'];
        }
        if(isset($order['ocity']) && !empty($order['ocity'])) {
            $formatted["city"] = $order['ocity'];
        }
        if(isset($order['oaddress']) && !empty($order['oaddress'])) {
            $formatted["address1"] = $order['oaddress'];
        }
        if(isset($order['oaddress2']) && !empty($order['oaddress2'])) {
            $formatted["address2"] = $order['oaddress2'];
        }
        if(isset($formatted['address1']) && !empty($formatted['address1'])) {
            $formatted["country"] = 'United States';
        }

        return $formatted;
    }

    public function formatShippingAddress($order)
    {
        $formatted = [];
        if(isset($order['oshipname']) && !empty($order['oshipname'])) {
            $formatted["first_name"] = $order['oshipname'];
        }
        if(isset($order['oshiplastname']) && !empty($order['oshiplastname'])) {
            $formatted["last_name"] = $order['oshiplastname'];
        }
        if(isset($order['oshipphone']) && !empty($order['oshipphone'])) {
            $formatted["phone"] = $order['oshipphone'];
        }
        if(isset($order['oshipstate']) && !empty($order['oshipstate'])) {
            $formatted["province"] = $order['oshipstate'];
        }
        if(isset($order['oshipzip']) && !empty($order['oshipzip'])) {
            $formatted["zip"] = $order['oshipzip'];
        }
        if(isset($order['oshiptown']) && !empty($order['oshiptown'])) {
            $formatted["city"] = $order['oshiptown'];
        }
        if(isset($order['oshipaddress']) && !empty($order['oshipaddress'])) {
            $formatted["address1"] = $order['oshipaddress'];
        }
        if(isset($order['oshipaddress2']) && !empty($order['oshipaddress2'])) {
            $formatted["address2"] = $order['oshipaddress2'];
        }
        if(isset($formatted['address1']) && !empty($formatted['address1'])) {
            $formatted["country"] = 'United States';
        }

        return $formatted; 
    }
}