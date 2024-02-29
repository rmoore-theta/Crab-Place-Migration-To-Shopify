<?php

namespace App\Helpers;

use App\App;
use Exception;

class ShopifyClient
{
    public $base;
    public $headers;
    public $adminApiToken;


    public function __construct($index = null)
    {
        if ($index) {
            $config = $this->getConfig($index);
            $this->base = $config[$index]['base'];
            $this->adminApiToken = $config[$index]["cpadmintoken"];
        }
    }

    public function getConfig($store)
    {
        $config = App::$App->config('shopify');
        if (!isset($config[$store])) {
            throw new Exception($store . ' wasn\'t found in the config.');
        }
        return $config;
    }

    /**
     * Make an API call
     *
     * @param string $method
     * @param string $uri
     * @param array $data post data
     * @return array jsonResponse
     */
    public function call($method, $uri, $data=[])
    {
        while (true) {
            $uri = $this->base.$uri;

            $ch = curl_init($uri);

            switch($method) {
                case 'POST':
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    break;
                case 'PUT':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    break;
                case 'DELETE':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                    break;
            }

            $token = $this->adminApiToken;
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json','X-Shopify-Access-Token: '.$token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
            $response = curl_exec($ch);

            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $rawHeader = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
            $this->headers = $this->rawHeaderToArray($rawHeader);
            
            return json_decode($body, true);
        }
    }


    public function rawHeaderToArray($rawHeader)
    {
        $headers = [];

        foreach (explode("\r\n", $rawHeader) as $line) {
            if (strpos($line, ":") !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[strtolower($key)] = trim($value);
            } else {
                if (stripos($line, 'http/') !== false) {
                    $headers['http_code'] = trim($line);
                }
            }
        }

        return $headers;
    }
}
