<?php

define('__APP__', __DIR__);
require __DIR__ . '/vendor/autoload.php';


$loop = new React\EventLoop\StreamSelectLoop();
$client = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop);





$app = function (React\Http\Request $request, React\Http\Response $response)
    use ($loop, $client) {
    echo "Access came...polling\n";

    $client->connect(function ($client) use ($loop, $response) {
        echo "Connected to Redis, now listening for incoming messages...\n";

        $logger = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop);

        $client->pubSubLoop('nrk:channel', function ($event, $ps) use ($client, $logger, $response) {
            echo "pubsub loop started.....";
            $logger->rpush("store:{$event->channel}", $event->payload,
                function () use ($event) {
                    echo "Stored message `{$event->payload}` from {$event->channel}.\n";
                });
            $headers = array('Content-Type' => 'text/plain');
            $response->writeHead(200, $headers);
            $response->end("end with store:{$event->channel}");
            $ps->quit();
            echo "pubsub ended";
        });
    });
};


$socket = new React\Socket\Server($loop);
$http = new React\Http\Server($socket);
$http->on('request', $app);
$socket->listen(1337);
$loop->run();


