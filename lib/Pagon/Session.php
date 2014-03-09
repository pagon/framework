<?php

namespace Pagon;

use Pagon\Session\Store;

/**
 * Class Session
 *
 * @property \Pagon\App $app
 * @method  has(string $key)
 * @method  get(string $key)
 * @method  set(string $key)
 * @method  all(string $key)
 * @method  save(string $key)
 * @method  clear(string $key)
 * @method  destroy(string $key)
 * @package Pagon
 */
class Session extends Fiber implements \ArrayAccess, \Countable, \Iterator
{
    /**
     * @var Session\Store Default store for new instance
     */
    public static $defaultStore;

    /**
     * @var Session Current session
     */
    public static $current;

    /**
     * @var array Default injectors
     */
    protected $injectors = array(
        /**
         * Use cookie
         */
        'use_cookie' => true,

        /**
         * Id for session
         */
        'id'         => null,

        /**
         * Store
         */
        'store'      => null,

        /**
         * Sessions store
         */
        'sessions'   => array(),

        /**
         * Has been destroyed?
         */
        'destroyed'  => false,

        /**
         * Register to global?
         */
        'global'     => false,

        /**
         * Has been started?
         */
        'started'    => false,

        /**
         * App
         */
        'app'        => null
    );

    /**
     * Factory a session
     *
     * @param string|array $type
     * @param array        $config
     * @throws \InvalidArgumentException
     * @return Session
     */
    public static function create($type, $config = array())
    {
        if (is_string($type)) {
            $config['store'] = $type;
        } else if (is_array($type)) {
            $config = $type;
        } else {
            throw new \InvalidArgumentException("Unknown arguments to create session");
        }

        return new self($config);
    }

    /**
     * Start session
     *
     * @param string|array $type
     * @param array        $config
     * @return Session
     */
    public static function start($type, $config = null)
    {
        $session = self::create($type, $config);
        $session->startSession();
        return $session;
    }

    /**
     * Init
     *
     * @param array $injectors
     */
    public function __construct(array $injectors = array())
    {
        parent::__construct($injectors + $this->injectors);

        // Use default store is not set
        if (self::$defaultStore && $this->injectors['store'] === null) {
            $this->injectors['store'] = self::$defaultStore;
        }

        // If store is config, create store
        if ($this->injectors['store'] && !$this->injectors['store'] instanceof Store) {
            $this->injectors['store'] = Store::create($this->injectors['store']);

            // Set default store if not exists
            if (!self::$defaultStore) {
                self::$defaultStore = $this->injectors['store'];
            }
        }

        // If not app inject, use default
        if (!$this->injectors['app']) {
            $this->injectors['app'] = App::self();
        }
    }

    /**
     * Start session
     */
    public function startSession()
    {
        // Session name
        $name = session_name();

        // Get cookies
        $cookies = $this->injectors['app']->input->cookies;

        // Check id and generate
        if (!$this->injectors['id']) {
            if (isset($cookies[$name])) {
                $this->injectors['id'] = $cookies[$name];
            } else {
                $this->injectors['id'] = sha1(microtime(true) . rand(1000, 9999));
            }
        }

        // Use cookie automatic?
        ini_set('session.use_cookies', 0);
        session_cache_limiter(false);

        // Register sessions
        $this->injectors['sessions'] = & $this->injectors['app']->input->sessions;

        // Support store
        if ($this->injectors['store']) {
            $this->injectors['store']->register($this);
            $this->injectors['store']->app = $this->injectors['app'];

            if ($this->injectors['global']) {
                session_set_save_handler(
                    array($this->injectors['store'], 'open'),
                    array($this->injectors['store'], 'close'),
                    array($this->injectors['store'], 'read'),
                    array($this->injectors['store'], 'write'),
                    array($this->injectors['store'], 'destroy'),
                    array($this->injectors['store'], 'gc')
                );

                // Start session
                session_id($this->injectors['id']);
                if (!session_start()) {
                    throw new \RuntimeException("Session start failed");
                }

                // Get all sessions
                $this->injectors['sessions'] = & $_SESSION;
            } else {
                $this->injectors['store']->open();

                $this->injectors['sessions']
                    = unserialize($this->injectors['store']->read($this->injectors['id']));

                // @TODO GC and Destroy
            }
        } else {
            if ($this->injectors['global']) {
                // Start session
                session_id($this->injectors['id']);
                if (!session_start()) {
                    throw new \RuntimeException("Session start failed");
                }

                $this->injectors['sessions'] = & $_SESSION;
            } else {
                $this->injectors['sessions'] = array();
            }
        }

        // Register save session when app finished
        $session = $this;
        $this->injectors['app']->on('end', function () use ($session) {
            $session->saveSession();
        });

        // Send cookie to save id
        if (!isset($cookies[$name])) {
            $this->injectors['app']->output->cookie($name, $this->injectors['id']);
        }

        // Flat to started
        $this->injectors['started'] = true;

        self::$current = $this;
    }

    /**
     * Check has session
     *
     * @param string $key
     * @return bool
     */
    public function hasSession($key)
    {
        return isset($this->injectors['sessions'][$key]);
    }

    /**
     * Get session
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function getSession($key, $default = null)
    {
        return isset($this->injectors['sessions'][$key]) ? $this->injectors['sessions'][$key] : $default;
    }

    /**
     * Set session
     *
     * @param string $key
     * @param mixed  $value
     */
    public function setSession($key, $value)
    {
        $this->injectors['sessions'][$key] = $value;
    }

    /**
     * Delete session
     *
     * @param string $key
     */
    public function deleteSession($key)
    {
        unset($this->injectors['sessions'][$key]);
    }

    /**
     * Get all session
     *
     * @return mixed
     */
    public function allSession()
    {
        return $this->injectors['sessions'];
    }

    /**
     * Clear all session
     */
    public function clearSession()
    {
        $this->injectors['sessions'] = array();
    }

    /**
     * Destroy all session
     *
     * @return bool
     */
    public function destroySession()
    {
        if ($this->injectors['global']) {
            session_destroy();
        } else if ($this->injectors['store']) {
            $this->injectors['store']->destroy($this->injectors['id']);
        } else {
            $this->clearSession();
        }
    }

    /**
     * Save session
     */
    public function saveSession()
    {
        if ($this->injectors['global']) {
            session_write_close();
        } else if ($this->injectors['store']) {
            $this->injectors['store']->write($this->injectors['id'], serialize($this->injectors['sessions']));
        }
    }

    /*
     * Magin method to make helpful usable.
     */

    public static function __callStatic($method, $args)
    {
        if (!self::$current) {
            throw new \RuntimeException("No session started");
        }

        return call_user_func_array(array(self::$current, $method . 'Session'), $args);
    }

    public function __call($method, $args)
    {
        return call_user_func_array(array($this, $method . 'Session'), $args);
    }

    /*
     * Array access to use such as $_SESSION
     */

    public function offsetGet($offset)
    {
        return $this->getSession($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->setSession($offset, $value);
    }

    public function offsetExists($offset)
    {
        return $this->hasSession($offset);
    }

    public function offsetUnset($offset)
    {
        $this->deleteSession($offset);
    }

    /*
     * Object intance can be countable
     */

    public function count()
    {
        return count($this->injectors['sessions']);
    }

    /*
     * Object instance can be loop with foreach
     */

    public function rewind()
    {
        reset($this->injectors['sessions']);
    }

    public function current()
    {
        return current($this->injectors['sessions']);
    }

    public function key()
    {
        return key($this->injectors['sessions']);
    }

    public function next()
    {
        next($this->injectors['sessions']);
    }

    public function valid()
    {
        return current($this->injectors['sessions']) !== false;
    }
}