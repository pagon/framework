<?php

namespace Pagon\Session\Store;

use Pagon\Session\Store;
use Pagon\Session;

class Cookie extends Store
{
    protected $injectors = array(
        'name' => 'session'
    );

    public function register(Session $session)
    {
        $session->app->output->on('header', function () use ($session) {
            $session->saveSession();
        });
    }

    /*--------------------
    * Session Handlers
    ---------------------*/

    public function open($path, $name)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        return $this->injectors['app']->input->cookie($this->injectors['name']);
    }

    public function write($id, $data)
    {
        $this->injectors['app']->output->cookie($this->injectors['name'], $data, array('encrypt' => true, 'timeout' => $this->injectors['lifetime']));
        return true;
    }

    public function destroy($id)
    {
        $this->injectors['app']->output->cookie($this->injectors['name'], '');
        return true;
    }

    public function gc($lifetime)
    {
        return true;
    }
}
