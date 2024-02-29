<?php

namespace App\Helpers;

use App\DB\CrabDatabaseConnection;

use PDO;
use PDOException;

/**
 * Based on the DB setup and this migration, this is how things will have to happen.
 * First get the customer record SELECT * FROM Customers WHERE contactid = '$id'
 * Get any addresses SELECT * FROM Addresses WHERE Addresses.CustomerID = '$id'
 * Get any orders SELECT * FROM Orders WHERE Orders.ocustomerid = '$id'
 *
 */
class CustomerMigration
{
    private $id;
    private $crabConnection;
    private $customerAddressList = array();
    private $defaultAddress = array();
    private $defaultAddressHash;

    public function __construct($customerId)
    {
        $this->id = $customerId;
        $this->crabConnection = CrabDatabaseConnection::getConnection();
    }

    public function getCustomer()
    {
        $sql = "SELECT * FROM Crab_Place_Legacy.dbo.customers WHERE contactid = '{$this->id}'";
        try {
            $results = $this->crabConnection->query($sql, PDO::FETCH_NAMED);
            $row = $results->fetchAll();
            return $row[0];
        } catch (PDOException $e) {
            echo "PDOException error message: {$e->getMessage()}\nCode:{$e->getCode()}\n";
        }
    }

    private function getCustomerAddresses()
    {
        $sql = "SELECT * FROM Crab_Place_Legacy.dbo.Addresses WHERE Addresses.CustomerID = '{$this->id}' ORDER BY AddressID DESC";
        try {
            $results = $this->crabConnection->query($sql, PDO::FETCH_NAMED);
            return $results->fetchAll();
        } catch (PDOException $e) {
            echo "PDOException error message: {$e->getMessage()}\nCode:{$e->getCode()}\n";
        }
    }

    /**
     * Was going to extract addresses from orders, but it seems redudnat since I have addresses table already, so skipping for now...
     */
    // private function getCustomerOrders()
    // {
    //     $sql = "SELECT * FROM Crab_Place_Legacy.dbo.orders WHERE Orders.ocustomerid = '{$this->id}'";
    //     try {
    //         $results = $this->crabConnection->query($sql, PDO::FETCH_NAMED);
    //         return $results->fetchAll();
    //     } catch (PDOException $e) {
    //         echo "PDOException error message: {$e->getMessage()}\nCode:{$e->getCode()}\n";
    //     }
    // }

    public function fetchCustomerData()
    {
        $customer = $this->getCustomer();
        if(empty($customer)) {
            $this->crabConnection = CrabDatabaseConnection::resetConnection();
            return;
        }
        $this->extractCustomerAddress($customer);
        $this->extractCustomerShippingAddress($customer);
        $this->extractCustomerAddressFromAddresses();

        $changeStatus = false;
        $response = array();

        $formatedRecord = array();
        
        $formatedRecord['first_name'] = isset($customer['firstname']) ? preg_replace('/[^\00-\255]+/u', '', htmlspecialchars(trim($customer['firstname']))) : '';
        $formatedRecord['last_name'] = isset($customer['lastname']) ? preg_replace('/[^\00-\255]+/u', '', htmlspecialchars(trim($customer['lastname']))) : '';
        $formatedRecord['email'] = isset($customer['email']) ? trim($customer['email']) : '';
        $formatedRecord['accepts_marketing'] = ($customer['RemoveMailList'] == 1) ? false : true;
        $formatedRecord['phone'] = isset($customer['phone']) ? trim($customer['phone']) : '';
        $formatedRecord['verified_email'] = true;
        //@TODO: implement the password changed status
        $password = (isset($customer['password'])) ? trim($customer['password']) : '';
        if(strlen($password) < 5) {
            $password .= 'extra';
            $changeStatus = true;
            // $response['password_reset'] = true;// would likely trigger an email that is time based and will confuse the user
        }elseif(strlen($password) > 40){
            echo "\nPassword to long for Shopify!\n";
            $changeStatus = true;
            $password = 'MigratedPasswordFromCP';
        }
        $formatedRecord['password'] = $password;
        $formatedRecord['password_confirmation'] = $password;
        $formatedRecord['send_email_welcome'] = false;
        if(!empty($this->customerAddressList)) {
            $formatedRecord['addresses'] = array($this->customerAddressList);
        }
        if(!empty($this->defaultAddress)) {
            $formatedRecord['default_address'] = $this->defaultAddress;
        }
        $response["customer"] = $formatedRecord;
        return $response;
        // return [$response, $changeStatus];
    }

    public function getAddresses()
    {
        return $this->customerAddressList;
    }

    public function getDefaultAddresses()
    {
        return [$this->defaultAddress,$this->defaultAddressHash];
    }

    private function extractCustomerAddress($customer)
    {
        $tempAddress = [];
        if(isset($customer['address']) && !empty($customer['address'])) {
            $tempAddress['address1'] = trim($customer['address']);
        }
        if(isset($customer['address2']) && !empty($customer['address2'])) {
            $tempAddress['address2'] = trim($customer['address2']);
        }
        if(isset($customer['city']) && !empty($customer['city'])) {
            $tempAddress['city'] = trim($customer['city']);
        }
        if(isset($customer['company']) && !empty($customer['company'])) {
            $tempAddress['company'] = trim($customer['company']);
        }
        if(isset($customer['firstname']) && !empty($customer['firstname'])) {
            $tempAddress['first_name'] = trim($customer['firstname']);
        }
        if(isset($customer['lastname']) && !empty($customer['lastname'])) {
            $tempAddress['last_name'] = trim($customer['lastname']);
        }
        if(isset($customer['phone']) && !empty($customer['phone'])) {
            $tempAddress['phone'] = trim($customer['phone']);
        }
        if(isset($customer['state']) && !empty($customer['state'])) {
            $tempAddress['province_code'] = trim($customer['state']);
        }
        if(isset($customer['postcode']) && !empty($customer['postcode'])) {
            $tempAddress['zip'] = trim($customer['postcode']);
        }
        if(!empty($tempAddress) && isset($tempAddress['address1'])) {
            $tempAddress['country'] = 'United States';
            $hash = md5(json_encode($tempAddress, JSON_UNESCAPED_SLASHES));
            $this->defaultAddress = $tempAddress;
            $this->defaultAddressHash = $hash;
            $this->customerAddressList[$hash] = $tempAddress;
        }
    }

    private function extractCustomerShippingAddress($customer)
    {
        $tempAddress = [];
        if(isset($customer['shipaddress']) && !empty($customer['shipaddress'])) {
            $tempAddress['address1'] = trim($customer['shipaddress']);
        }
        if(isset($customer['shipaddress2']) && !empty($customer['shipaddress2'])) {
            $tempAddress['address2'] = trim($customer['shipaddress2']);
        }
        if(isset($customer['shipcity']) && !empty($customer['shipcity'])) {
            $tempAddress['city'] = trim($customer['shipcity']);
        }
        if(isset($customer['shipcompany']) && !empty($customer['shipcompany'])) {
            $tempAddress['company'] = trim($customer['shipcompany']);
        }
        if(isset($customer['shipfirstname']) && !empty($customer['shipfirstname'])) {
            $tempAddress['first_name'] = trim($customer['shipfirstname']);
        }
        if(isset($customer['shiplastname']) && !empty($customer['shiplastname'])) {
            $tempAddress['last_name'] = trim($customer['shiplastname']);
        }
        if(isset($customer['shipphone']) && !empty($customer['shipphone'])) {
            $tempAddress['phone'] = trim($customer['shipphone']);
        }
        if(isset($customer['shipstate']) && !empty($customer['shipstate'])) {
            $tempAddress['province'] = trim($customer['shipstate']);
        }
        if(isset($customer['shipzip']) && !empty($customer['shipzip'])) {
            $tempAddress['zip'] = trim($customer['shipzip']);
        }
        if(!empty($tempAddress) && isset($tempAddress['address1'])) {
            $tempAddress['country'] = 'United States';
            $tempAddress['country_code'] = 'US';
            $hash = md5(json_encode($tempAddress, JSON_UNESCAPED_SLASHES));
            $this->customerAddressList[$hash] = $tempAddress;
        }
    }

    private function extractCustomerAddressFromAddresses()
    {
        $addresses = $this->getCustomerAddresses();
        if(!empty($addresses)) {
            foreach($addresses as $customer) {
                $tempAddress = [];
                if(isset($customer['Address']) && !empty($customer['Address'])) {
                    $tempAddress['address1'] = trim($customer['Address']);
                }
                if(isset($customer['Address2']) && !empty($customer['Address2'])) {
                    $tempAddress['address2'] = trim($customer['Address2']);
                }
                if(isset($customer['City']) && !empty($customer['City'])) {
                    $tempAddress['city'] = trim($customer['City']);
                }
                if(isset($customer['Company']) && !empty($customer['Company'])) {
                    $tempAddress['company'] = trim($customer['Company']);
                }
                if(isset($customer['FirstName']) && !empty($customer['FirstName'])) {
                    $tempAddress['first_name'] = trim($customer['FirstName']);
                }
                if(isset($customer['LastName']) && !empty($customer['LastName'])) {
                    $tempAddress['last_name'] = trim($customer['LastName']);
                }
                if(isset($customer['Phone']) && !empty($customer['Phone'])) {
                    $tempAddress['phone'] = trim($customer['Phone']);
                }
                if(isset($customer['State']) && !empty($customer['State'])) {
                    $tempAddress['province'] = trim($customer['State']);
                }
                if(isset($customer['Zip']) && !empty($customer['Zip'])) {
                    $tempAddress['zip'] = trim($customer['Zip']);
                }
                if(!empty($tempAddress) && isset($tempAddress['address1'])) {
                    $tempAddress['country'] = 'United States';
                    $tempAddress['country_code'] = 'US';
                    $hash = md5(json_encode($tempAddress, JSON_UNESCAPED_SLASHES));
                    $this->customerAddressList[$hash] = $tempAddress;
                }
            }
        }
    }

    public function getVipCustomers()
    {
        $sql = "SELECT * FROM GroundShippingMembers WHERE EndDate = '3/31/2024'";
        try {
            $results = $this->crabConnection->query($sql, PDO::FETCH_NAMED);
            return $results->fetchAll();
        } catch (PDOException $e) {
            echo "PDOException error message: {$e->getMessage()}\nCode:{$e->getCode()}\n";
        }
    }
}
