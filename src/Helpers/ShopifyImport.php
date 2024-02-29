<?php

namespace App\Helpers;

use App\App;
// use DB\Config;
use Shopify\Rest\Admin2023_04\Customer;
use Shopify\Utils;

// use ShopifyClient;

class ShopifyImport
{
    // @TODO: should set $this->sc = new ShopifyClient("migration");
    public function createCustomer($customer)
    {
        $sc = new ShopifyClient("migration");

        $response = $sc->call('POST', "/customers.json", $customer);
        // $response = $this->getUrlContent($url, 'POST', $header, $customerJSON, true);//, true
        // $success = $this->checkForValidResponse($response);
        return $response;
    }

    public function createCustomerAddress($address, $customerId)
    {
        $sc = new ShopifyClient("migration");
        $response = $sc->call('POST', "/customers/$customerId/addresses.json", $address);
        return $response;
    }

    public function findCustomerByEmail($email)
    {
        $sc = new ShopifyClient("migration");
        $response = $sc->call('GET', "/customers/search.json?query=email:$email");
        return $response;
    }

    /**
     * getUrlContent is what I was using before Haku complained and wanted me to switch to ShopifyClient when asking for help with connecting to Shopify.
     */
    public function getUrlContent($url, $method = 'GET', $header = null, $fields = null, $responseHeader = false)
    {
        $url = trim($url);
        $ch = curl_init();// Initialize a connection with cURL (ch = cURL handle, or "channel")
        curl_setopt($ch, CURLOPT_URL, $url);// Set the URL
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);// Set the HTTP method
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        if ($header && is_array($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);// Depricating since this is really for other headers...
        }
        if ($method === 'POST' && $fields) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }
        if ($responseHeader) {
            curl_setopt($ch, CURLOPT_HEADER, 1);// Return the headers as part of response
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);// Return the response instead of printing it out
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);// follow redirects...

        // curl_setopt($ch, CURLOPT_VERBOSE, true);// used to view what was sent?

        $response = curl_exec($ch);// Send the request and store the result in $response
        curl_close($ch);// Close cURL resource to free up system resources
        return $response;
    }

    public function updateCustomer($customer)
    {
        $sc = new ShopifyClient("migration");
        $response = $sc->call('PUT', "/customers/{$customer['customer']['id']}.json", $customer);
        // $response = $this->getUrlContent($url, 'POST', $header, $customerJSON, true);//, true
        // $success = $this->checkForValidResponse($response);
        return $response;
    }

    public function addMetaField($metaField)
    {
        $sc = new ShopifyClient("migration");
        $response = $sc->call('GET', "/blogs/382285388/metafields.json");
        return $response;
    }

    public function createOrder($order)
    {
        $sc = new ShopifyClient("migration");

        $response = $sc->call('POST', "/orders.json", $order);
        // $response = $this->getUrlContent($url, 'POST', $header, $customerJSON, true);//, true
        // $success = $this->checkForValidResponse($response);
        return $response;
    }

    public function updateOrder($order)
    {
        $sc = new ShopifyClient("migration");

        $response = $sc->call('PUT', "/orders/{$order['order']['id']}.json", $order);
        return $response;
    }
    public function getOrder($orderId)
    {
        $sc = new ShopifyClient("migration");
        $response = $sc->call('GET', "/orders/{$orderId}.json");
        return $response;
    }

    public function getFulfillmentOrder($orderId)
    {
        $sc = new ShopifyClient("migration");
        $response = $sc->call('GET', "/orders/{$orderId}/fulfillment_orders.json");
        return $response;
    }
    public function getOrderFulfillment($orderId)
    {
        $sc = new ShopifyClient("migration");
        $response = $sc->call('GET', "/orders/{$orderId}/fulfillments.json");
        return $response;
    }
    public function fulfillOrder($object)
    {
        $sc = new ShopifyClient("migration");
        $response = $sc->call('POST', "/fulfillments.json", $object);
        return $response;
    }

    public function getAssignedFulfillmentOrders()
    {
        $sc = new ShopifyClient("migration");
        $response = $sc->call('GET', "/assigned_fulfillment_orders.json");
        return $response;
    }
    
}
