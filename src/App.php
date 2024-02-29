<?php

namespace App;

class App
{
    public const VERSION = '1.1';
    public $ApplicationPath;
    public $Config;
    public static App $App; // instance of this class for reference

    public function __construct($Path)
    {
        static::$App = $this;
        $this->init($Path);
    }

    public function init($Path)
    {
        $this->ApplicationPath = $Path;

        $this->Config = $this->config('app');

        $this->registerErrorHandler();
    }

    public function config($Label)
    {
        $Config = $this->ApplicationPath . '/config/' . $Label . '.php';
        if (!file_exists($Config)) {
            throw new \Exception($Config . ' not found in '.static::class.'::config()');
        }
        return require $Config;
    }

    public function registerErrorHandler()
    {
        error_reporting(0);
    }
}
