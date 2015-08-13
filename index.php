<?php

include "vendor/autoload.php";

$i = 0;

$app = function (React\Http\Request $request, React\Http\Response $response) use (&$i) {
    $i++;
    $path = $request->getPath();
    $param = $request->getQuery();
    $text = "This is request $path with counter $i.\n";
    
    if ( isset($param['delay']) ) sleep($param['delay']);

    $headers = array('Content-Type' => 'text/plain');
    echo "access $i\n";
    $response->writeHead(200, $headers);
    $response->end($text);
};

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server($loop);
$http = new React\Http\Server($socket);

$http->on('request', $app);

$socket->listen(1337);
$loop->run();
