<?php

namespace Pagon\Session\Store;

use Pagon\Session\Store;

class Memcache extends Store
{
    protected $injectors = array(
        'host'    => 'localhost',
        'port'    => '11211',
        'timeout' => 1,
        'name'    => 'session/:id'
    );

    /**
     * @var \Memcache
     */
    protected $memcache;

    /*--------------------
    * Session Handlers
    ---------------------*/

    public function open($path = null, $name = null)
    {
        if (!class_exists('\Memcache')) {
            throw new \RuntimeException("Use Session\Memcache need memcache extension installed.");
        }

        $this->memcache = new \Memcache();
        $this->memcache->connect($this->injectors['host'], $this->injectors['port'], $this->injectors['timeout']);

        return true;
    }

    public function close()
    {
        $this->memcache->close();
        return true;
    }

    public function read($id)
    {
        return $this->memcache->get(strtr($this->injectors['name'], array(':id' => $id)));
    }

    public function write($id, $data)
    {
        return $this->memcache->set(strtr($this->injectors['name'], array(':id' => $id)), $data, $this->injectors['lifetime']);
    }

    public function destroy($id)
    {
        return $this->memcache->delete(strtr($this->injectors['name'], array(':id' => $id)));
    }

    public function gc($lifetime)
    {
        return true;
    }
}
