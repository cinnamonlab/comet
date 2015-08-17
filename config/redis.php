<?php

return array(
    'cluster' => false,
    'servers' => array(
        'default' => array(
            'host'     => 'localhost',
            'port'     => 6379,
//            'password' => 'password',
            'database' => 0,
        )
    ),
    'url' => "tcp://127.0.0.1:6379" // if password; tcp://127.0.0.1:6379?auth=password
);