<?php

namespace Pagon\Session\Store;

use Pagon\Session\Store;

class File extends Store
{
    protected $injectors = array(
        'dir' => '/tmp'
    );

    /*--------------------
    * Session Handlers
    ---------------------*/

    public function open($path = null, $name = null)
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
        if (file_exists($this->injectors['dir'] . '/SESS_' . $id)) {
            return file_get_contents($this->injectors['dir'] . '/SESS_' . $id);
        }
        return "";
    }

    public function write($id, $data)
    {
        return !!file_put_contents($this->injectors['dir'] . '/SESS_' . $id, $data);
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
