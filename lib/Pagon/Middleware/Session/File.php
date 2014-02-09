<?php

namespace Pagon\Middleware\Session;

use Pagon\Middleware\Session;

class File extends Session
{
    protected $injectors = array(
        'dir' => '/tmp'
    );

    /*--------------------
    * Session Handlers
    ---------------------*/

    public function open()
    {
        if (!is_dir($this->injectors['dir'])) {
            mkdir($this->injectors['dir'], 0777, true);
        }
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        if (file_exists($this->injectors['dir'] . '/' . $id)) {
            return unserialize(file_get_contents($this->injectors['dir'] . '/' . $id));
        }
        return array();
    }

    public function write($id, $data)
    {
        return !!file_put_contents($this->injectors['dir'] . '/' . $id, $data);
    }

    public function destroy($id)
    {
        return unlink($this->injectors['dir'] . '/' . $id);
    }

    public function gc($lifetime)
    {
        return true;
    }
}
