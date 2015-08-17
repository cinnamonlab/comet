<?php

use \Framework\Comet\Comet;
use \Framework\Response;
use \Framework\Comet\Receiver;

/**
 * Simple Chat
 */

$c = Comet::getInstance();

$c->get('hello', function(&$request, &$response){
    return (new Response())->setContent('hello guest');
});

$c->get('listen', function(&$request, &$response) {
    //TODO: Authentication if needed

    $parameters = $request->getQuery();

    if ( ! isset($parameters['name']))
        return (new Response())->setCode(404);

    $my_name = $parameters['name'];

    return new Receiver("name:$my_name");
});


$c->get('talk', function(&$request, &$response) {
    //TODO: Authentication if needed

    $parameters = $request->getQuery();

    if ( ! isset($parameters['name']))
        return (new Response())->setCode(400);

    if ( ! isset($parameters['to']))
        return (new Response())->setCode(404);

    if ( ! isset($parameters['message']))
        return (new Response())->setCode(400);

    $my_name = $parameters['name'];
    $message = $parameters['message'];
    $to_name = $parameters['to'];

    Comet::getInstance()->publish(array(
        "name" => $to_name,
        "from" => $my_name,
        "message" => $message
    ));

    return new Receiver("name:$my_name");
});

$c->receive('/^name:/', function($data, &$request, &$response){
    return (new Response())->setContent(print_r($data, true));
});

