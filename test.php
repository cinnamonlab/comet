<?php

class A {
    public $b;
    function __construct($c) {
        $this->b = $c;
    }
}

$c = new A (function(){echo "kkkkkk";});

$a = $c->b; $a();

