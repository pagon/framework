<?php

namespace Pagon;

use Pagon\Exception\Pass;
use Pagon\Exception\Stop;

/**
 * Router
 * parse and manage the Route
 *
 * @package Pagon
 * @property App      app       Application to service
 * @property string   path      The current path
 * @property string   base      The base path
 * @property \Closure automatic Automatic closure for auto route
 */
class Router extends Middleware
{
    const _CLASS_ = __CLASS__;

    /**
     * @var App
     */
    public $app;

    /**
     * @var array Routes pointer
     */
    protected $routes;

    /**
     * @var int Last index
     */
    protected $last = -1;

    protected $injectors = array(
        'base' => ''
    );

    /**
     * @param array $injectors
     */
    public function __construct(array $injectors = array())
    {
        parent::__construct($injectors + $this->injectors);
        $this->app = & $this->injectors['app'];
        $this->routes = & $this->app->routes;
    }

    /**
     * Add router for path
     *
     * @param string          $path
     * @param \Closure|string $route
     * @param string          $method
     * @return $this
     */
    public function map($path, $route, $method = '*')
    {
        // Support base inject
        $path = $this->injectors['base'] . $path;

        if (!is_array($route)) {
            // Init route node
            $this->routes[] = array($method, $path, $route);
            $this->last++;
        } else {
            foreach ($route as $r) {
                // Init route node
                $this->routes[] = array($method, $path, $r);
                $this->last++;
            }
        }

        return $this;
    }

    /**
     * Naming the route
     *
     * @param string $name
     * @param string $path
     * @return $this
     */
    public function name($name, $path = null)
    {
        if ($path === null) {
            $path = $this->routes[$this->last][1];
        }

        $this->app->names[$name] = $path;
        return $this;
    }

    /**
     * Set default parameters
     *
     * @param array $defaults
     * @return $this
     */
    public function defaults($defaults = array())
    {
        $this->routes[$this->last]['defaults'] = $defaults;
        return $this;
    }

    /**
     * Set rules for parameters
     *
     * @param array $rules
     * @return $this
     */
    public function rules($rules = array())
    {
        $this->routes[$this->last]['rules'] = $rules;
        return $this;
    }

    /**
     * Get path by name
     *
     * @param string $name
     * @return mixed
     */
    public function path($name)
    {
        return isset($this->app->names[$name]) ? $this->app->names[$name] : false;
    }

    /**
     * Run route
     *
     * @return array
     * @throws \Exception
     * @return bool
     */
    public function dispatch()
    {
        // Check path
        if ($this->injectors[1] === null) return false;

        $method = $this->input->method();
        $path = & $this->injectors[1];
        $prefixes = & $this->injectors['prefixes'];
        $app = & $this->app;

        if ($this->handle($this->routes, function ($route) use ($path, $method, $prefixes, $app) {
            // Lookup rules
            $rules = isset($route['rules']) ? $route['rules'] : array();

            // Lookup defaults
            $defaults = isset($route['defaults']) ? $route['defaults'] : array();

            // Try to parse the params
            if (($params = Router::match($path, $route[1] . ($app->cli ? '(:args)' : ''), $rules, $defaults)) !== false) {
                // Method match
                if ($route[0] && is_string($route[0]) && strpos($route[0], $method) === false && $route[0] !== '*') return false;

                $app->input->params = $params;

                return Route::build($route[2], null, $prefixes);
            }
            return false;
        })
        ) return true;


        // Try to check automatic route parser
        if (isset($this->injectors['automatic']) && is_callable($this->injectors['automatic'])) {
            $routes = (array)call_user_func($this->injectors['automatic'], $this->injectors[1]);

            return $this->handle($routes, function ($route) use ($prefixes) {
                try {
                    return Route::build($route, null, $prefixes);
                } catch (\Exception $e) {
                }
            });
        }

        return false;
    }

    /**
     * Process the routes serious
     *
     * @param array    $routes
     * @param callable $mapper
     * @return bool
     */
    public function handle(array $routes, \Closure $mapper)
    {
        array_unshift($routes, null);

        $app = $this->app;

        $arguments = array(
            $this->app->input,
            $this->app->output,
            function () use (&$routes, $mapper, &$arguments, $app) {
                while ($route = next($routes)) {
                    if ($route = $mapper($route)) break;
                }

                if (!$route) throw new Pass;

                $app->output->write(call_user_func_array($route, $arguments));

                return true;
            }
        );

        try {
            return $arguments[2]();
        } catch (Pass $e) {
            return false;
        }
    }

    /**
     * Run the given route
     *
     * @param \Closure|string $route
     * @param array           $params
     * @return bool
     */
    public function run($route, array $params = array())
    {
        if ($route = Route::build($route)) {
            $this->app->input->params = $params;
            call_user_func_array($route, array(
                $this->app->input,
                $this->app->output,
                function () {
                    throw new Pass;
                }
            ));
            return true;
        }
        return false;
    }

    /**
     * Call for middleware
     */
    public function call()
    {
        $prefixes = array();
        $this->injectors[1] = $this->app->input->path();

        // Prefixes Lookup
        if ($this->app->prefixes) {
            foreach ($this->app->prefixes as $path => $namespace) {
                if (strpos($this->injectors[1], $path) === 0) {
                    $prefixes[99 - strlen($path)] = $namespace;
                }
            }
            ksort($prefixes);
        }
        $this->injectors['prefixes'] = $prefixes;

        if (!$this->dispatch()) {
            $this->next();
        }
    }

    /**
     * Parse the route
     *
     * @static
     * @param string $path
     * @param string $route
     * @param array  $rules
     * @param array  $defaults
     * @return array|bool
     */
    public static function match($path, $route, $rules = array(), $defaults = array())
    {
        $param = false;

        // Regex or Param check
        if (!strpos($route, ':') && strpos($route, '^') === false) {
            if ($path === $route || $path === $route . '/') {
                $param = array();
            }
        } else {
            // Try match
            if (preg_match(self::pathToRegex($route, $rules), $path, $matches)) {
                array_shift($matches);
                $param = $matches;
            }
        }

        // When complete the return
        return $param === false ? false : ($param + $defaults);
    }

    /**
     * To regex
     *
     * @static
     * @param string $path
     * @param array  $rules
     * @return string
     */
    public static function pathToRegex($path, $rules = array())
    {
        if ($path[1] !== '^') {
            $path = str_replace(array('/'), array('\\/'), $path);
            if ($path{0} == '^') {
                // As regex
                $path = '/' . $path . '/';
            } elseif (strpos($path, ':')) {
                $path = str_replace(array('(', ')'), array('(?:', ')?'), $path);

                if (!$rules) {
                    // Need replace
                    $path = '/^' . preg_replace('/(?<!\\\\):([a-zA-Z0-9]+)/', '(?<$1>[^\/]+?)', $path) . '\/?$/';
                } else {
                    $path = '/^' . preg_replace_callback('/(?<!\\\\):([a-zA-Z0-9]+)/', function ($match) use ($rules) {
                            return '(?<' . $match[1] . '>' . (isset($rules[$match[1]]) ? $rules[$match[1]] : '[^\/]+') . '?)';
                        }, $path) . '\/?$/';
                }
            } else {
                // Full match
                $path = '/^' . $path . '\/?$/';
            }

            // * support
            if (strpos($path, '*')) {
                $path = str_replace('*', '([^\/]+?)', $path);
            }
        }
        return $path;
    }
}
