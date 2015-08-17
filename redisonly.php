<?php

define('__APP__', __DIR__);
require __DIR__ . '/vendor/autoload.php';

$loop = new React\EventLoop\StreamSelectLoop();
$client = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop);

$client->connect(function ($client) use ($loop) {
    echo "Connected to Redis, now listening for incoming messages...\n";

    $logger = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop);

    $client->pubSubLoop('nrk:channel', function ($event) use ($logger) {
        $logger->rpush("store:{$event->channel}", $event->payload, function () use ($event) {
            echo "Stored message `{$event->payload}` from {$event->channel}.\n";
        });
    });
});


var_dump( $client->isConnected() );
$loop->run();