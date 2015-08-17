<?php

define('__APP__', __DIR__);
require __DIR__ . '/vendor/autoload.php';

use Framework\Comet\Comet;
date_default_timezone_set('UTC');

include __APP__ . "/comet.php";


Comet::getInstance()->run();
