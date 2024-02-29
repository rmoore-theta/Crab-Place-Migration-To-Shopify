<?php

namespace App\Controllers;

use App\Helpers\MigrationStatus;
use App\Helpers\CustomerMigration;
use App\Helpers\ShopifyImport;
use App\Helpers\OrderMigration;
use App\DB\CrabDatabaseConnection;
use PDO;
use PDOException;

class Main
{
    /**
     * Uncomment out the function(s) that you want to run, before running the script again.
     */
    public function run()
    {
        // $ms = MigrationStatus::getSingleton();
        // $ms->showErrors();
        // $error = '"is invalid"';
        // $erroredCustomers = $ms->getErrorCustomers($error);
        // echo "\nCreating a list of bad customerIds\n";
        // foreach($erroredCustomers as $v) {
        //     echo "{$v[0]}, ";
        // }
        
        // $this->printBadOrders();
        
        // $this->createMetaFields();
        // $this->fillLocalDBOrders();
        
        // $this->migrationLoop();
        // $this->migrationLoopOrders();
        // $this->loopOrderUpdate();
        // $this->investigateBalance();

        // $this->migrationLoopDefaultCustomerAddress();

        // After putting results in a new table, compare with the custome migration table...
        // Check and get current vip status foreach customer
        // $this->migrationVipLoop();

        // Gets all the active vip records 
        // add tag VIPInfo transactionId and PaymentDate
        // $this->migrationLoop();//Migrate the users. !!This should be called after fillLocalDB has ran and been deactvated!!
       
        // $this->printBadEmails();
        
        // $this->fillLocalDBUpdates(); // Updating with new customers
        // $this->fillLocalDB();//This should be called first
    }

    private function createMetaFields()
    {
        $metaField["metafield"]["namespace"] = "migrations";
        $metaField["metafield"]["key"] = "migrated";
        $metaField["metafield"]["type"] = "single_line_text_field";
        $metaField["metafield"]["value"] = "Shopify";
        $si = new ShopifyImport();
        $si->addMetaField($metaField);
    }

    private function migrationLoopOrders()
    {
        $ms = MigrationStatus::getSingleton();
        $om = new OrderMigration();
        $si = new ShopifyImport();

        $nextOrders = $ms->getNextOrder();// used for original processing
        // $nextOrders = $ms->getNextOrderFix();// for fixing orders
        $orderIds = '';
        foreach($nextOrders as $k=>$v){
            $orderIds .= "'{$v[0]}',";
        }
        $orderIds = substr($orderIds, 0, -1);
        $completedOrders = $om->getCompletedOrdersToMigrate($orderIds);
        echo "\nstarting the process\n";

        $today = '2024-01-17';
        foreach($completedOrders as $compOrder){
            $ocustomerid = $compOrder['oCustomerId'];
            $orderId = $compOrder['orderId'];
            $orderDb = $om->getOrder($orderId);
            $formatedOrder = $om->shopifyOrderItemsFormat($orderId);
            $shopifyCustomerId = $ms->getCustomerByCPId($ocustomerid);
            // Set shopifyCustomerId to order
            if(is_numeric($shopifyCustomerId)){
                $formatedOrder["order"]["customer"] = array("id"=>$shopifyCustomerId);
            }else{
                echo "\ncp customer Id:$ocustomerid had no shopifyCustomerId:$shopifyCustomerId \n";
            }
            // Set metafields
            $formatedOrder["order"]["metafields"] = array(
                array("key"=>"migration_date","value"=>$today,"namespace"=>"migration"),
                array("key"=>"ship_date","value"=>substr($orderDb['shipdate'],0,10),"namespace"=>"checkout_buddy"),
            );
            if(isset($orderDb['deliverydate']) && !empty($orderDb['deliverydate'])){
                // this line improperly assumed $orderDb['deliverydate'] would be set, but it is not always...
                $formatedOrder["order"]["metafields"][] = array("key"=>"delivery_date","value"=>substr($orderDb['deliverydate'],0,10),"namespace"=>"checkout_buddy");
            }
            // Set the transaction array for the order, might need to pull in more details...
            $formatedOrder["order"]["transactions"][] = array("kind"=>'sale', "status"=>'success', "amount"=>$orderDb["orderamount"]);
            if(isset($orderDb['otax']) && !empty($orderDb['otax'])) {
                $formatedOrder["order"]["total_tax"] = $orderDb['otax'];
            }
            $formatedOrder["order"]["financial_status"] = "paid";
            // Check if coupon or gift certificate used and apply them to the order
            if(isset($compOrder['coupon']) && !empty($compOrder['coupon']) && isset($compOrder['couponDiscount']) && !empty($compOrder['couponDiscount'])) {
                $formatedOrder["order"]["discount_codes"][] = array("code"=>$compOrder['coupon'], "amount"=>$compOrder['couponDiscount'],"type"=>'');
            }
            if(isset($compOrder['giftCertificate']) && !empty($compOrder['giftCertificate']) && isset($compOrder['giftAmountUsed']) && !empty($compOrder['giftAmountUsed'])) {
                $formatedOrder["order"]["discount_codes"][] = array("code"=>$compOrder['giftCertificate'], "amount"=>$compOrder['giftAmountUsed'],"type"=>'');
            }
            // Check if an email is attached to the order itself(pretty sure this is for sending to someone else?)
            if(isset($orderDb['oemail']) && !empty($orderDb['oemail'])) {
                $formatedOrder["order"]["email"] = $orderDb['oemail'];// comment out if bad
            }
            $bill = $om->formatBillingAddress($orderDb);
            if(!empty($bill)){
                $formatedOrder["order"]['billing_address'] = $bill;
            }
            $ship = $om->formatShippingAddress($orderDb);
            if(!empty($ship)){
                $formatedOrder["order"]['shipping_address'] = $ship;
            }

            // $formatedOrder["order"]["fulfillment_status"] = "fulfilled";// If they are all completed, then they should be fulfilled?
            // var_dump($formatedOrder);die;
            $shopifyOrder = $si->createOrder($formatedOrder);
            // "Required parameter missing or invalid"
            if(isset($shopifyOrder["order"]) && isset($shopifyOrder["order"]["id"])){
                $statusMessage = 'migrated';
                $ms->updateOrderLocally($orderId, $shopifyOrder["order"]["id"], $statusMessage);
            }else{
                $shopifyOrderJson = json_encode($shopifyOrder, JSON_UNESCAPED_SLASHES);
                $ms->updateOrderLocally($orderId, 'error', $shopifyOrderJson);
            }
        }
        // ORDER ITEMS CROSS REFERENCE WITH SHOPIFY PRODUCTS & PRODUCT MAPPINGS
        // The above label is for the orders that were fully placed and ready to migrate https://github.com/Dark-Knight-1313/Crab-Place-Repo/blob/main/Crab-Place-DB/Dev-DB/Products/Shopify-Import/Product-Mappings.sql
        // Should probably migrate the orders based on the valid orders first, to simplify the logic in gaining the data from the order/orderitems
        // Find out the most orderItems belonging to a single order that was placed... start with the fully placed orders and group orderitems by orderid
    }

    private function loopOrderUpdate()
    {
        $ms = MigrationStatus::getSingleton();
        $orderIds = $ms->getNextOrderUpdate();
        foreach($orderIds as $ids){
            $this->orderLoopIterationFulfullment($ids);
        }
    }
    private function orderLoopIterationFulfullment($orderIds)
    {
        $si = new ShopifyImport();
        $cpOrder = $orderIds[0];
        $orderId = $orderIds[1];
        $formated = [];
        $fulfillmentOrder = $si->getFulfillmentOrder($orderId);
        if(isset($fulfillmentOrder["fulfillment_orders"])){
            foreach($fulfillmentOrder["fulfillment_orders"] as $fo){
                $formated["fulfillment"]["line_items_by_fulfillment_order"][] = ["fulfillment_order_id"=>$fo["id"]];
            }
        }
        $shopifyOrder = $si->fulfillOrder($formated);
        if(isset($shopifyOrder["fulfillment"]) && isset($shopifyOrder["fulfillment"]["id"])){
            $notes = $shopifyOrder["fulfillment"]["id"];
        }else{
            $notes = json_encode($shopifyOrder, JSON_UNESCAPED_SLASHES);
        }
        $ms = MigrationStatus::getSingleton();
        $ms->updateOrderNotesLocally($cpOrder, $notes);
    }

    private function investigateBalance()
    {
        $om = new OrderMigration();
        $si = new ShopifyImport();
        // $orderId = 5702460866877;//shopifyOId
        $orderId = 5719503536445;
        // $cpOrderId = '780404';
        // $cpOrder = $om->getOrder($cpOrderId);
        $sOrder = $si->getOrder($orderId);
        // var_dump($cpOrder, "\n<<<Crab Place Order!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!Shopify Order response>>>\n", $sOrder);
        var_dump($sOrder);
    }

    private function migrationLoopDefaultCustomerAddress()
    {
        $ms = MigrationStatus::getSingleton();
        $i = 0;
        do {
            $customerToMigrate = $ms->getNextCustomerNotes();
            if(!$customerToMigrate || !isset($customerToMigrate['cp_customer_id'])) {
                echo "no customer found\n";
                $i = 0;
            } else {
                $this->setDefaultAddress($customerToMigrate['cp_customer_id'], $customerToMigrate['shopify_customer_id']);
                $i = 1;
            }
        } while ($i > 0);
    }

    private function setDefaultAddress($cpCustomerId, $shopifyCustomerId)
    {
        $notes = 'defaultAddressAdded';
        $ms = MigrationStatus::getSingleton();
        $cm = new CustomerMigration($cpCustomerId);
        $formatedCustomerObject = $cm->fetchCustomerData();

        $si = new ShopifyImport();
        $addressResponse = $this->setupDefaultCustomerAddresses($cm, $si, $shopifyCustomerId);
        if(isset($addressResponse["customer_address"]) && isset($addressResponse["customer_address"]["id"])){
            $notes = $addressResponse["customer_address"]["id"];
        }
        else{
            $notes = 'no address';
        }
        echo "$cpCustomerId: $notes,";
        $ms->updateCustomerNotesLocally($cpCustomerId, $notes);
    }

    private function setupDefaultCustomerAddresses($cm, $si, $shopifyCustomerId)
    {
        [$defaultAddress, $dHash] = $cm->getDefaultAddresses();
        if(!is_null($dHash)) {
            $address['address'] = $defaultAddress;
            $address['address']['customer_id'] = $shopifyCustomerId;
            $address['address']['default'] = true;
            return $si->createCustomerAddress($address, $shopifyCustomerId);
        }
        return null;
    }

    private function migrationVipLoop()
    {
        $ms = MigrationStatus::getSingleton();
        $cm = new CustomerMigration(0);
        $vipRecord = $cm->getVipCustomers();
        $si = new ShopifyImport();
        foreach($vipRecord as $vip){
            $shopifyId = $ms->getCustomerByCPId($vip['CustomerID']);
            if(strlen($shopifyId) > 14){
                echo "customerId:{$vip['CustomerID']}, shopifyId:$shopifyId\n";
            }
            $customer = ["customer"];
            $customer["customer"]["id"] = $shopifyId;
            $renewal = '3/31/2024';
            $customer["customer"]["tags"]="VIP, VIP End Date: $renewal";
            $response = $si->updateCustomer($customer);
        }
    }


    /** Used to migrate customers over initially,
     * includeds customer + addresses & default,
     * nothing order related is here
    */
    private function migrationLoop()
    {
        $ms = MigrationStatus::getSingleton();
        $i = 0;
        do {
            $customerToMigrate = $ms->getNextCustomer();
            if(!$customerToMigrate || !isset($customerToMigrate['cp_customer_id'])) {
                echo "no customer found\n";
                $i = 0;
            } else {
                $this->setupCustomer($customerToMigrate['cp_customer_id']);
                $i = 1;
            }
        } while ($i > 0);
    }

    private function setupCustomer($cpCustomerId)
    {
        $status = 'start';
        $ms = MigrationStatus::getSingleton();
        $cm = new CustomerMigration($cpCustomerId);
        $formatedCustomerObject = $cm->fetchCustomerData();
        //@TODO: implement the password changed status
        // [$formatedCustomerObject, $passwordChanged] = $cm->fetchCustomerData();
        // if($passwordChanged){
        //     $status .= ' changed password';
        // }

        // This should be moved into a create user function
        $si = new ShopifyImport();
        $newCustomer = $si->createCustomer($formatedCustomerObject);
        if(isset($newCustomer["customer"]) && isset($newCustomer["customer"]["id"])) {
            $shopifyCustomerId = $newCustomer["customer"]["id"];
            $status .= ' success';
            $this->setupCustomerAddresses($cm, $si, $shopifyCustomerId);
        } else {
             $status .= $shopifyCustomerId = json_encode($newCustomer, JSON_UNESCAPED_SLASHES);
            // phone is already used or invalid, remove it from the customer object  & $status = 'phone is invalid/used';
            if(isset($newCustomer["errors"]["phone"])){
                unset($formatedCustomerObject["customer"]['phone']);
                $status .= ' removed phone';
                $newCustomer = $si->createCustomer($formatedCustomerObject);
                if(isset($newCustomer["customer"]) && isset($newCustomer["customer"]["id"])) {
                    $shopifyCustomerId = $newCustomer["customer"]["id"];
                    $status .= ' success';
                    $this->setupCustomerAddresses($cm, $si, $shopifyCustomerId);
                }
            }
            if((isset($newCustomer["errors"]["email"]) && $newCustomer["errors"]["email"] === "has already been taken") || (isset($newCustomer["errors"]["email"][0]) && $newCustomer["errors"]["email"][0] === "has already been taken")) {
                $email = $formatedCustomerObject["customer"]['email'];
                $foundCustomer = $si->findCustomerByEmail($email);
                if(isset($foundCustomer["customers"]) && isset($foundCustomer["customers"][0]["id"])) {
                    $shopifyCustomerId = $foundCustomer["customers"][0]["id"];
                    $status .= ' email found';
                }
            }
        }
        // if ($passwordChanged === true){$status .=" password changed"}
        echo " CPcustomerId:$cpCustomerId & shopifyCustomerId:$shopifyCustomerId";
        $success = $ms->updateCustomerLocally($cpCustomerId, $shopifyCustomerId, $status);
    }

    private function setupCustomerAddresses($cm, $si, $shopifyCustomerId)
    {
        [$defaultAddress, $dHash] = $cm->getDefaultAddresses();
        if(!is_null($dHash)) {
            $address['address'] = $defaultAddress;
            $address['address']['customer_id'] = $shopifyCustomerId;
            $address['address']['default'] = true;
            $si->createCustomerAddress($address, $shopifyCustomerId);
        }
        foreach($cm->getAddresses() as $hash => $addressO) {
            if($hash === $dHash) {
                continue;
            }
            $address['address'] = $addressO;
            $address['address']['customer_id'] = $shopifyCustomerId;
            $si->createCustomerAddress($address, $shopifyCustomerId);
        }
        // end adding address individually
    }

    private function fillLocalDB()
    {
        echo "\nPopulating the localDB with customer ids\n";
        $crabConnection = CrabDatabaseConnection::getConnection();
        $ms = new MigrationStatus();

        $sql = "SELECT contactid FROM Customers";
        try {
            $results = $crabConnection->query($sql, PDO::FETCH_NAMED);
            foreach($results->fetchAll() as $row) {
                $ms->insertCustomerLocally($row['contactid']);
            }
        } catch (PDOException $e) {
            echo "PDOException error message: {$e->getMessage()}\n\n";
        }
    }

    private function printBadEmails()
    {
        echo "\nprinting out all the crab place emails that shopify said were not valid\n";
        $crabConnection = CrabDatabaseConnection::getConnection();
        $ms = new MigrationStatus();

        $erroredCustomers = $ms->getErrorCustomers('error');
        foreach($erroredCustomers as $v) {
            // echo "{$v[0]}, ";
            try {
                $sql = "SELECT contactid, email, phone FROM customers WHERE contactid = '{$v[0]}';";
                $results = $crabConnection->query($sql, PDO::FETCH_NAMED);
                foreach($results->fetchAll() as $row) {
                    echo "{$row['contactid']}, {$row['email']}, {$row['phone']}\n";
                }
            } catch (PDOException $e) {
                echo "PDOException error message: {$e->getMessage()}\n\n";
            }
        }
        
    }

    private function fillLocalDBUpdates()
    {
        echo "\nPopulating the localDB with customer ids that were not migrated yet\n";
        $crabConnection = CrabDatabaseConnection::getConnection();
        $ms = new MigrationStatus();

        $sql = "SELECT contactid FROM Customers";
        try {
            $results = $crabConnection->query($sql, PDO::FETCH_NAMED);
            foreach($results->fetchAll() as $row) {
                echo ", {$row['contactid']}";
                $ms->insertCustomerLocally($row['contactid']);
            }
        } catch (PDOException $e) {
            echo "PDOException error message: {$e->getMessage()}\n\n";
        }
    }

    private function fillLocalDBOrders()
    {
        echo "\nPopulating the localDB with customer ids\n";
        $crabConnection = CrabDatabaseConnection::getConnection();
        $ms = new MigrationStatus();

        $sql = "SELECT orderId FROM Crab_Place_Dev.dbo.CompletedOrders";
        try {
            $results = $crabConnection->query($sql, PDO::FETCH_NAMED);
            foreach($results->fetchAll() as $row) {
                $ms->insertOrderLocally($row['orderId']);
            }
        } catch (PDOException $e) {
            echo "PDOException error message: {$e->getMessage()}\n\n";
        }
    }

    private function printBadOrders()
    {
        echo "\nprinting out all the invalid order errors\n";
        $crabConnection = CrabDatabaseConnection::getConnection();
        $ms = new MigrationStatus();

        echo "\nNow show Order errors\n";
        $ms->showOrderErrors();
        $erroredOrders = $ms->getErrorOrdersIds('error');
        echo "\nCreating a list of bad orderIds\n";
        foreach($erroredOrders as $v) {
            echo "{$v[0]}, ";
        }
        echo "\nNow show OrderIds plus error message\n";
        $erroredCustomers = $ms->getErrorOrders('"customer"');
        $badCPOrderIds = '';
        foreach($erroredCustomers as $v) {
            echo "orderId:{$v[0]}, message:{$v[2]}\n";
            $badCPOrderIds .= "{$v[0]},";
        }
        $badCPOrderIds = substr($badCPOrderIds, 0, -1);
        echo "\n($badCPOrderIds)\n";
        // foreach($erroredCustomers as $v) {
        //     echo "orderId:{$v[0]}, message:{$v[2]}\n";
        //     // try {
        //     //     $sql = "SELECT contactid, email, phone FROM customers WHERE contactid = '{$v[0]}';";
        //     //     $results = $crabConnection->query($sql, PDO::FETCH_NAMED);
        //     //     foreach($results->fetchAll() as $row) {
        //     //         echo "{$row['contactid']}, {$row['email']}, {$row['phone']}\n";
        //     //     }
        //     // } catch (PDOException $e) {
        //     //     echo "PDOException error message: {$e->getMessage()}\n\n";
        //     // }
        // }
    }
}
