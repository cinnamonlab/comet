<?php

namespace Framework\Comet;

use Framework\Config;
use Framework\Redis\Redis;
use Predis\Async\Client;
use React\Http\Request;
use React\Http\Response;
use React\Socket\Server as SocketServer;
use React\Http\Server as HttpServer;
use React\EventLoop\StreamSelectLoop;

class Comet
{

    private $loop;
    private $socket;
    private $http;

    private $app; //the main loop app
    private $controllers;

    private $http_connections;
    private $redis_subscriber;

    private $not_found;

    private $receiver_functions = array();

    /**
     * @return array
     */
    public function getReceiverFunctions()
    {
        return $this->receiver_functions;
    }
    public $receivers = array();

    /**
     * @return array
     */
    public function getReceivers()
    {
        return $this->receivers;
    }



    public function get( $uri, $func ) {
        if ( ! isset($this) ) return static::getInstance()->get($uri, $func);
        $this->controllers['GET'][$uri] = $func;
        return $this;
    }

    public function post( $uri, $func ) {
        if ( ! isset($this) ) return static::getInstance()->post($uri, $func);
        $this->controllers['POST'][$uri] = $func;
        return $this;
    }
    public function put( $uri, $func ) {
        if ( ! isset($this) ) return static::getInstance()->put($uri, $func);
        $this->controllers['PUT'][$uri] = $func;
        return $this;
    }

    public function delete( $uri, $func ) {
        if ( ! isset($this) ) return static::getInstance()->delete($uri, $func);
        $this->controllers['DELETE'][$uri] = $func;
        return $this;
    }

    public function receive( $trigger, $func ) {

        $this->receiver_functions[Comet::getPreg($trigger)] = $func;
    }

    public function findReceiverFunction( $trigger ) {
        foreach($this->receiver_functions as $trigger_preg => $func ) {
            if ( preg_match(Comet::getPreg($trigger_preg), $trigger) ) {
                return $func;
            }
        }
        return function($a,$b){};
    }

    public function proceedReceiverFunction($pub, $trigger, &$request, &$response) {

        $rs = null;

        if ( ! $this->checkTrigger($trigger, $pub) ) return false;
        $f = $this->findReceiverFunction($trigger);

        try {
            $rs = $f(json_decode($pub), $request, $response);
            if ( is_array($rs) ) {
                $response->writeHead(200, array("Content-type", "application/json"));
                $response->end(json_encode($rs));
            }
            if ( is_string($rs) ) {
                $response->writeHead(200, array("Content-type", "text/html"));
                $response->end( $rs );
            }
            if ( $rs instanceof \Framework\Response ) {
                $response->writeHead($rs->getCode(), $this->getHeadersArray($rs));
                $response->end($rs->getContent());
            }
        } catch ( \Exception $e ) {
            //Mostly assumed that the header is closed already
            return false;
        }
        return true;
    }

    public function getHeadersArray( \Framework\Response $rs ) {
        $headers = $rs->getHeaders();
        $headers_array = array();
        foreach ( $headers as $header ) {
            if ( preg_match("/^(.*): (.*)$/i", $header, $match) ) {
                $headers_array[$match[1]] = $match[2];
            }
        }
        return $headers_array;
    }

    public function checkTrigger( $trigger, $pub ) {

        $pub_array = json_decode($pub);

        if ( json_last_error() == JSON_ERROR_NONE ) {
            foreach ( $pub_array as $key => $value ) {
                if ( preg_match(Comet::getPreg($trigger), $key . ":" . "$value" ) ) {
                    return true;
                }
            }
        } else {
            if ( preg_match(Comet::getPreg($trigger), $pub) ) {
                return true;
            }
        }
        return false;
    }

    public function notfound( $func ) {
        if ( ! isset($this) ) self::getInstance()->notfound($func);
        $this->not_found = $func;
        return $this;
    }

    public function getNotFound()
    {
        return $this->not_found;
    }

    public function publish( $data ) {

        try {
            Redis::publish(Config::get('comet.pubsub', 'default_pubsub'),
                json_encode($data));
        } catch ( \Exception $e ) { echo $e->getTraceAsString(); die;}
    }


    private function __construct( ) {
        echo "constructor started\n";

        $this->loop = new StreamSelectLoop();
        $this->socket = new SocketServer($this->loop);
        $this->http = new HttpServer($this->socket);
        $this->controllers = array();
        $this->http_connections = array();

        $this->loop->addPeriodicTimer(5, function() {
            $receivers = Comet::getInstance()->getReceivers();
            foreach($receivers as $key => $receiver) {
                $trigger = $receiver[0];
                $request = $receiver[1];
                $response = $receiver[2];
                $time = $receiver[3];

                if ( $time < date("U") - Config::get('comet.timeout', 30) ) {
                    try {
                        $response->writeHead(408, array("Content-type"=>"text/html"));
                        $response->end("Request Timeout");

                    } catch ( \Exception $e ) { }
                    Comet::getInstance()->removeReceivers($key);

                }
            }
        });


        $this->not_found = function(Request &$request, Response &$response) {
            return (new \Framework\Response())->setCode(404)
                ->setContent("Not Found");
        };


        $this->redis_subscriber = new Client( Config::get('redis.uri'), $this->loop );

        $this->redis_subscriber->connect(function ($client)  {
            Comet::log("Start Redis Connection");
            $client->pubSubLoop(Config::get('comet.pubsub', 'default_pubsub'),
                function ($event) {

                    $payload = $event->payload;

                    $receivers = Comet::getInstance()->getReceivers();
                    foreach( $receivers as $receiver) {
                        $trigger = $receiver[0];
                        $request = $receiver[1];
                        $response = $receiver[2];
                        Comet::proceedReceiverFunction($payload, $trigger, $request, $response);
                    }

                });
        });

        $this->app = function (Request $request, Response $response) {

            $received = false;
            $result = null;

            foreach( $this->controllers as $method => $controllers ) {
                foreach ($controllers as $uri => $controller) {
                    //TODO: Richer URI controller can be implemented
                    if (!preg_match("/^\//", $uri)) $uri = "/" . $uri;
                    if (preg_match("/\/$/", $uri)) $uri = substr($uri, 0, -1);

                    if ($request->getPath() == $uri && $request->getMethod() == $method ) {
                        $result = $controller($request, $response);
                        $received = true;
                    }
                }
            }

            if ( ! $received ) {
                $f = Comet::getInstance()->getNotFound();
                $result = $f($request, $response);
            }

            try {

                if ( $result instanceof \Framework\Response ) {

                    $response->writeHead($result->getCode(),
                        Comet::getInstance()->getHeadersArray($result));
                    $response->end($result->getContent());

                } elseif ( $result instanceof Receiver) {
                    array_push(Comet::getInstance()->receivers,
                        array($result->getTrigger(), &$request, &$response, date("U")) );
                } else {
                    //Assume it is a string
                    $response->writeHead(200, array("Content-type"=>"text/html"));
                    $response->end($result);
                }
            } catch ( \Exception $e ) { Comet::log($e->getMessage());}

        };

        $this->http->on('request', $this->app);
    }

    public function run( ) {

        try {
            $this->publish(array("start" => date("U")));

            $this->socket->listen(1337);
            $this->loop->run();
            echo "run process started\n";
        } catch ( \Exception $e ) {
            echo "process ended (maybe redis error)";
        }
    }

    public function removeReceivers( $key ) {
        unset($this->receivers[$key]);
    }


    private static $me = null;
    /**
     * Have to be guaranteed as SingleTon
     */
    public static function getInstance() {
        if ( ! self::$me ) self::$me = new static();
        return self::$me;

    }

    private static function log( $str ) {
        echo $str . "\n";
    }

    private static function getPreg($str) {

        if ( ! preg_match("/^\//", $str) ) {
            $str = "/" . preg_quote( $str ) . "/";
        }
        return $str;
    }
}