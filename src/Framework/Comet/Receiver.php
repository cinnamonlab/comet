<?php

namespace Framework\Comet;

class Receiver
{

    private $trigger;

    /**
     * @return mixed
     */
    public function getTrigger()
    {
        return $this->trigger;
    }

    /**
     * @param mixed $trigger
     */
    public function setTrigger($trigger)
    {
        $this->trigger = $trigger;
    }

    public function __construct($trigger) {
        $this->trigger = $trigger;
    }

}