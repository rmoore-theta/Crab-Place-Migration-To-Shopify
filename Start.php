<?php
require_once 'vendor/autoload.php';
new \App\App(__DIR__);
(new \App\Controllers\Main())->run();