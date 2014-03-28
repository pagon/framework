<?php

namespace Pagon\Session;

use Pagon\Fiber;
use Pagon\Session;

abstract class Store extends Fiber
{
    public static function create($type, array $config = array())
    {
        if (is_array($type)) {
            $config = $type['config'];
            $type = $type['type'];
        }

        $prefixes[] = '';
        $prefixes[] = __NAMESPACE__ . "\\Store";

        foreach ($prefixes as $namespace) {
            if (!is_subclass_of($class = ($namespace ? $namespace . '\\' : '') . $type, __CLASS__, true)) continue;

            return new $class($config);
        }

        throw new \InvalidArgumentException("Non-exists store class '$type'");
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