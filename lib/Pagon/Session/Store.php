<?php

namespace Pagon\Session;

use Pagon\Fiber;
use Pagon\Session;

abstract class Store extends Fiber
{
    public static function factory($type, array $config = array())
    {
        if (is_array($type)) {
            $config = $type['config'];
            $type = $type['type'];
        }

        $class = __NAMESPACE__ . '\\Store\\' . ucfirst(strtolower($type));

        if (!class_exists($class) && !class_exists($class = $type)) {
            throw new \InvalidArgumentException('Can not find given "' . $type . '" session store adapter');
        }

        return new $class($config);
    }

    public function __construct(array $injectors = array())
    {
        parent::__construct($injectors + $this->injectors);

        if (!isset($this->injectors['lifetime'])) {
            $this->injectors['lifetime'] = ini_get('session.gc_maxlifetime');
        }
    }

    public function register(Session $session)
    {
        $session->app->on('end', function () use ($session) {
            $session->saveSession();
        });
    }

    abstract function open($path, $name);

    abstract function close();

    abstract function read($id);

    abstract function write($id, $data);

    abstract function destroy($id);

    abstract function gc($lifetime);
}