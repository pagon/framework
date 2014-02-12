<?php

namespace Pagon\Middleware;

use Pagon\Middleware;

class Session extends Middleware
{
    public function call()
    {
        \Pagon\Session::start($this->injectors);

        $this->next();
    }
}