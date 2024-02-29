<?php

namespace App\DB;

class Config
{
    private $configs;
    public function __construct()
    {
        $file = 'config.json';
        if (file_exists($file)) {
            $fileContent = file_get_contents($file);
            $this->configs = json_decode($fileContent, true);
        } else {
            throw new \Exception("No config.json file found in root directory! Try renaming config-sample.json and setting the values for your environment");
        }
    }

    public function get($configKey)
    {
        if (isset($this->configs[$configKey])) {
            return $this->configs[$configKey];
        } else {
            throw new \Exception("Config not found for key $configKey in config.json file found in root directory!");
        }
    }
}
