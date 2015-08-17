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

$c->get('index', function(&$request, &$response){

    Return \Framework\Blade\View::make("index");

});

$c->get('chat', function(&$request, &$response){

    $parameters = $request->getQuery();

    if ( ! isset($parameters['name']))
        return (new Response())->setCode(404)->setContent("name not found");

    if ( ! isset($parameters['name']))
        $to = "guest";
    else $to = $parameters['to'];

    Return \Framework\Blade\View::make("chat")
        ->with('name', $parameters['name'])
        ->with('to', $to);

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
    return Response::json(array("status"=>"success"));
});

$c->receive('/^name:/', function($data, &$request, &$response){
    return Response::json($data);
});

