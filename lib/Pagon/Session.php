<?php

namespace Pagon;

use Pagon\Session\Store;

/**
 * Class Session
 *
 * @property \Pagon\App $app
 * @package Pagon
 */
class Session extends Fiber
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
     * @param array $config
     * @return Session
     */
    public static function factory(array $config = array())
    {
        return new self($config);
    }

    /**
     * Start session
     *
     * @param array $config
     * @return Session
     */
    public static function start(array $config = array())
    {
        $session = self::factory($config);
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
            $this->injectors['store'] = Store::factory($this->injectors['store']);

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

        // Check id and generate
        if (!$this->injectors['id']) {
            if (isset($_COOKIE[$name])) {
                $this->injectors['id'] = $_COOKIE[$name];
            } else {
                $this->injectors['id'] = sha1(microtime(true) . rand(1000, 9999));
            }
        }

        // Use cookie automatic?
        ini_set('session.use_cookies', 0);
        session_cache_limiter(false);

        // Support store
        if ($this->injectors['store']) {
            $this->injectors['store']->register($this);
            $this->injectors['store']->app = $this->injectors['app'];

            session_set_save_handler(
                array($this->injectors['store'], 'open'),
                array($this->injectors['store'], 'close'),
                array($this->injectors['store'], 'read'),
                array($this->injectors['store'], 'write'),
                array($this->injectors['store'], 'destroy'),
                array($this->injectors['store'], 'gc')
            );
        } else {
            $this->injectors['app']->on('end', function () {
                session_write_close();
            });
        }

        // Send cookie to save id
        if (!isset($_COOKIE[$name])) {
            $this->injectors['app']->output->cookie($name, $this->injectors['id']);
        }

        // Start session
        session_id($this->injectors['id']);
        if (!session_start()) {
            throw new \RuntimeException("Session start failed");
        }

        // Get all sessions
        $this->injectors['sessions'] = & $_SESSION;

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
    public function clear()
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
        session_destroy();
    }

    /**
     * Save session
     */
    public function saveSession()
    {
        session_write_close();
    }

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
} 