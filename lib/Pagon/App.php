<?php
/**
 * Pagon Framework
 *
 * @package               Pagon
 * @author                Corrie Zhao <hfcorriez@gmail.com>
 * @copyright         (c) 2011 - 2014 Pagon Framework
 */

namespace Pagon;

/**
 * App
 * The core of Pagon
 *
 * @package Pagon
 *
 * @property Http\Input|Command\Input   input        Input of Application
 * @property Http\Output|Command\Output output       Output of Application
 * @property Router                     router       Router of Application
 * @property array                      locals       The locals variables to used in template
 * @property string                     mode         Current run mode, Default is "develop"
 * @property bool                       debug        Is debug mode enabled?
 * @property string                     views        Views dir
 * @property bool                       error        Handle error
 * @property bool                       buffer       Buffer enabled?
 * @property string                     timezone     Current timezone
 * @property string                     charset      The application charset
 * @property array                      routes       Store the routes for run
 * @property array                      prefixes     The prefixes to mapping the route namespace
 * @property array                      names        Routes names
 * @property string                     autoload     The path to autoload
 * @property array                      namespaces   The namespaces to load with directory path
 * @property array                      alias        The class to alias
 * @property array                      engines      The engines to render template
 * @property array                      errors       Default error handles
 * @property array                      stacks       The middleware stacks to load
 * @property array                      bundles      The bundles to load
 */
class App extends EventEmitter
{
    /**
     * @var Config
     */
    protected $injectors = array(
        /**
         * Application control
         */
        'mode'       => 'develop',
        'debug'      => false,
        'error'      => true,
        'exception'  => true,
        'views'      => false,
        'routes'     => array(),
        'prefixes'   => array(),
        'names'      => array(),
        'buffer'     => true,
        'timezone'   => 'UTC',
        'charset'    => 'UTF-8',
        'autoload'   => null,
        'alias'      => array(),
        'namespaces' => array(),
        'engines'    => array(
            'jade' => 'Jade'
        ),
        'errors'     => array(
            '404'       => array(404, 'Request path not found'),
            'exception' => array(500, 'Error occurred'),
            'crash'     => array(500, 'Application crashed')
        ),
        'stacks'     => array(),
        'mounts'     => array('/' => ''),
        'bundles'    => array(),
        'locals'     => array(),
        'resource'   => array(
            'index'   => array('GET'),
            'create'  => array('GET', 'create'),
            'store'   => array('POST'),
            'show'    => array('GET', ':id'),
            'edit'    => array('GET', ':id/edit'),
            'update'  => array('PUT', ':id'),
            'destroy' => array('DELETE', ':id')
        ),

        /**
         * Main dependencies
         */
        'input'      => null,
        'output'     => null,
        'router'     => null,

        /**
         * System control
         */
        'running'    => false,
        'cli'        => null,
    );

    /**
     * @var Http\Input|Command\Input
     */
    public $input;

    /**
     * @var Http\Output|Command\Output
     */
    public $output;

    /**
     * @var Router
     */
    public $router;

    /**
     * @var App The top app
     */
    protected static $self;

    /**
     * @var array The file has loads
     */
    protected static $loads = array();

    /**
     * Return current app
     *
     * @throws \RuntimeException
     * @return App
     */
    public static function self()
    {
        if (!self::$self) {
            throw new \RuntimeException("There is no running App exists");
        }

        return self::$self;
    }

    /**
     * App init
     *
     * @param array|string $config
     * @throws \RuntimeException
     * @return App
     */
    public function __construct($config = array())
    {
        // Register shutdown
        register_shutdown_function(array($this, '__shutdown'));

        // Register autoload
        spl_autoload_register(array($this, '__autoload'));

        // Cli check
        if ($this->injectors['cli'] === null) $this->injectors['cli'] = PHP_SAPI === 'cli';

        // Set config
        $this->injectors =
            (!is_array($config) ? Config::load((string)$config)->raw() : $config)
            + (!empty($this->injectors['cli']) ? array('buffer' => false) : array())
            + $this->injectors;

        // Set default locals
        $this->injectors['locals']['config'] = & $this->injectors;

        // Set mode
        $this->injectors['mode'] = ($_mode = getenv('PAGON_ENV')) ? $_mode : $this->injectors['mode'];

        // Set pagon root directory
        $this->injectors['mounts']['pagon'] = dirname(dirname(__DIR__));

        $this->input = & $this->injectors['input'];
        $this->output = & $this->injectors['output'];
        $this->router = & $this->injectors['router'];
    }

    /**
     * Get or set id
     *
     * @param string|\Closure $id
     * @return mixed
     */
    public function id($id = null)
    {
        return $this->input->id($id);
    }

    /**
     * Check if cli
     *
     * @return bool
     */
    public function cli()
    {
        return $this->injectors['cli'];
    }

    /**
     * Check if run
     *
     * @return bool
     */
    public function running()
    {
        return $this->injectors['running'];
    }

    /**
     * Set injectors
     *
     * @param string $key
     * @param mixed  $value
     * @return $this|void
     */
    public function set($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->set($k, $v);
            }
        } elseif (strpos($key, '.') !== false) {
            $config = & $this->injectors;
            $namespaces = explode('.', $key);
            foreach ($namespaces as $namespace) {
                if (!isset($config[$namespace])) $config[$namespace] = array();
                $config = & $config[$namespace];
            }
            $config = $value;
        } else {
            $this->injectors[$key] = $value;
        }
        return $this;
    }

    /**
     * Set config as true
     *
     * @param string $key
     * @return $this|void
     */
    public function enable($key)
    {
        return $this->set($key, true);
    }

    /**
     * Set config as false
     *
     * @param string $key
     * @return $this|void
     */
    public function disable($key)
    {
        return $this->set($key, false);
    }

    /**
     * Check config if true
     *
     * @param string $key
     * @return bool
     */
    public function enabled($key)
    {
        return $this->get($key);
    }

    /**
     * Check config if false
     *
     * @param string $key
     * @return bool
     */
    public function disabled($key)
    {
        return !$this->get($key);
    }

    /**
     * Config mode
     *
     * # Manuel call must before App::init
     *
     * @param string|\Closure $mode
     * @return callable|null|string
     */
    public function mode($mode = null)
    {
        if ($mode) {
            $this->injectors['mode'] = $mode instanceof \Closure ? $mode() : (string)$mode;
        }
        return $this->injectors['mode'];
    }

    /**
     * Add middleware
     *
     * @param Middleware|\Closure|string $path
     * @param Middleware|\Closure|string $middleware
     * @param array                      $options
     * @throws \RuntimeException
     * @return void
     */
    public function add($path, $middleware = null, $options = array())
    {
        if ($path instanceof Middleware
            || is_string($path) && $path{0} != '/'
            || $path instanceof \Closure
        ) {
            // If not path
            $options = (array)$middleware;
            $middleware = $path;
            $path = '';
        }

        // Add to the end
        $this->injectors['stacks'][] = array($path, $middleware, $options);
    }

    /**
     * Add a bundle
     *
     * @param string       $name    Bundle name or path
     * @param string|array $options Bundle options or name
     */
    public function bundle($name, $options = array())
    {
        if (!is_array($options)) {
            $path = $name;
            $name = $options;
            $options = array('path' => $path);
        }
        $this->injectors['bundles'][$name] = $options;
    }

    /**
     * Route get method or get injector
     *
     * @param string          $key
     * @param \Closure|string $default
     * @return mixed|Router
     */
    public function get($key = null, $default = null)
    {
        // Get config for use
        if ($default === null) {
            if ($key === null) return $this->injectors;

            $tmp = null;
            if (strpos($key, '.') !== false) {
                $ks = explode('.', $key);
                $tmp = $this->injectors;
                foreach ($ks as $k) {
                    if (!isset($tmp[$k])) return null;

                    $tmp = & $tmp[$k];
                }
            } else {
                if (isset($this->injectors[$key])) {
                    $tmp = & $this->injectors[$key];
                }
            }

            return $tmp;
        }

        if (func_num_args() > 2) {
            return $this->router->map($key, array_slice(func_get_args(), 1), 'GET');
        } else {
            return $this->router->map($key, $default, 'GET');
        }
    }

    /**
     * Route post method
     *
     * @param string          $path
     * @param \Closure|string $route
     * @param \Closure|string $more
     * @return Router
     */
    public function post($path, $route, $more = null)
    {
        if ($more !== null) {
            return $this->router->map($path, array_slice(func_get_args(), 1), 'POST');
        } else {
            return $this->router->map($path, $route, 'POST');
        }
    }

    /**
     * Route put method
     *
     * @param string          $path
     * @param \Closure|string $route
     * @param \Closure|string $more
     * @return Router
     */
    public function put($path, $route, $more = null)
    {
        if ($more !== null) {
            return $this->router->map($path, array_slice(func_get_args(), 1), 'PUT');
        } else {
            return $this->router->map($path, $route, 'PUT');
        }
    }

    /**
     * Route delete method
     *
     * @param string          $path
     * @param \Closure|string $route
     * @param \Closure|string $more
     * @return Router
     */
    public function delete($path, $route, $more = null)
    {
        if ($more !== null) {
            return $this->router->map($path, array_slice(func_get_args(), 1), 'DELETE');
        } else {
            return $this->router->map($path, $route, 'DELETE');
        }
    }

    /**
     * Map cli route
     *
     * @param string          $path
     * @param \Closure|string $route
     * @param \Closure|string $more
     * @return Router
     */
    public function command($path, $route = null, $more = null)
    {
        if ($path instanceof \Closure) {
            $route = $path;
            $path = '';
        }

        if ($more !== null) {
            return $this->router->map($path, array_slice(func_get_args(), 1), "CLI");
        } else {
            return $this->router->map($path, $route, "CLI");
        }
    }

    /**
     * Match all method
     *
     * @param string          $path
     * @param \Closure|string $route
     * @param \Closure|string $more
     * @return Router
     */
    public function all($path, $route = null, $more = null)
    {
        if ($more !== null) {
            return $this->router->map($path, array_slice(func_get_args(), 1));
        } else {
            return $this->router->map($path, $route);
        }
    }

    /**
     * Resource map
     *
     * @param string $path
     * @param string $namespace
     * @param array  $excepts
     * @throws \InvalidArgumentException
     */
    public function resource($path, $namespace, $excepts = array())
    {
        foreach ($this->injectors['resource'] as $type => $opt) {
            if (in_array($type, $excepts)) continue;

            $this->router->map(
                $path . (!empty($opt[1]) ? '/' . $opt[1] : ''),
                $namespace . '\\' . (!empty($opt[2]) ? $opt[2] : ucfirst($type)),
                $opt[0]
            );
        }
    }

    /**
     * Use auto route
     *
     * @param callable|bool $closure
     * @param string        $index
     * @return Router|mixed
     */
    public function autoRoute($closure, $index = 'Index')
    {
        if ($closure instanceof \Closure) {
            return $this->router->automatic = $closure;
        } elseif ($closure === true || is_string($closure)) {
            $_cli = $this->injectors['cli'];
            // Set route use default automatic
            return $this->router->inject('automatic', function ($path) use ($closure, $_cli, $index) {
                $parts = array();

                // Split rule
                if (!$_cli && $path !== '/') {
                    $_paths = explode('/', ltrim($path, '/'));
                } else if ($_cli && $path !== '') {
                    $_ = explode(' ', $path);
                    $_paths = explode(':', array_shift($_));
                }

                // Process parts
                if (isset($_paths)) {
                    foreach ($_paths as $_path) {
                        $parts[] = ucfirst(strtolower($_path));
                    }
                }

                // Set default namespace
                if ($closure !== true) array_unshift($parts, $closure);

                // Index
                if (!$parts) return $index;

                // Generate class name
                $class = join('\\', $parts);

                // Try to lookup class and with it's index
                return array($class, $class . '\\' . $index);
            });
        }
        return false;
    }

    /**
     * Mount dir to path
     *
     * @param string $path
     * @param string $dir
     * @return $this
     */
    public function mount($path, $dir = null)
    {
        if (!$dir) {
            $dir = $path;
            $path = '';
        }

        $this->injectors['mounts'][$path] = $dir;
        return $this;
    }

    /**
     * Load PHP file
     *
     * @param string $file
     * @return bool|mixed
     * @throws \InvalidArgumentException
     * @return mixed
     */
    public function load($file)
    {
        if (!$file = $this->path($file)) {
            throw new \InvalidArgumentException('Can load non-exists file "' . $file . '"');
        }

        if (isset(self::$loads[$file])) {
            return self::$loads[$file];
        }

        return self::$loads[$file] = include($file);
    }

    /**
     * Get path of mounts file system
     *
     * @param string $file
     * @return bool|string
     */
    public function path($file)
    {
        foreach ($this->injectors['mounts'] as $path => $dir) {
            if ($path === '' || strpos($file, $path) === 0) {
                $_path = rtrim($dir, '/') . '/' . ltrim(($path ? substr($file, strlen($path)) : $file), '/');
                if (!is_file($_path)) continue;
                return $_path;
            }
        }

        return false;
    }

    /**
     * Set or get view
     *
     * @param string $name
     * @param string $engine
     * @return string
     */
    public function engine($name, $engine = null)
    {
        if ($engine) {
            // Set engine
            $this->injectors['engines'][$name] = $engine;
        }
        return isset($this->injectors['engines'][$name]) ? $this->injectors['engines'][$name] : null;
    }

    /**
     * Render template
     *
     * @param string $path
     * @param array  $data
     * @param array  $options
     * @return void
     */
    public function render($path, array $data = null, array $options = array())
    {
        $this->output->body($this->compile($path, $data, $options)->render());
    }

    /**
     * Render View
     *
     * @param string $view
     * @param array  $data
     * @return mixed
     */
    public function renderView($view, array $data = null)
    {
        $this->output->body(View::create($view, $data));
    }

    /**
     * Compile view
     *
     * @param string $path
     * @param array  $data
     * @param array  $options
     * @throws \RuntimeException
     * @return View
     */
    public function compile($path, array $data = null, array $options = array())
    {
        // Check engine
        if (!isset($options['engine'])) {
            // Get ext
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $options['engine'] = false;

            // If ext then check engine with ext
            if ($ext && isset($this->injectors['engines'][$ext])) {
                // If engine exists
                if (is_string($this->injectors['engines'][$ext])) {
                    if (!class_exists($class = $this->injectors['engines'][$ext])
                        && !class_exists($class = __NAMESPACE__ . '\\Engine\\' . $this->injectors['engines'][$ext])
                    ) {
                        throw new \RuntimeException("Unavailable view engine '{$this->injectors['engines'][$ext]}'");
                    }
                    // Create new engine
                    $this->injectors['engines'][$ext] = $options['engine'] = new $class();
                } else {
                    // Get engine from exists engines
                    $options['engine'] = $this->injectors['engines'][$ext];
                }
            }
        }

        // Set default data
        $data = (array)$data;

        // Default set app
        $data['_'] = $this;

        // Create view
        $view = new View($path,
            $data + $this->injectors['locals'],
            $options + array('dir' => $this->injectors['views'], 'app' => $this)
        );

        // Return view
        return $view;
    }

    /**
     * App will run
     */
    public function run()
    {
        // Check if run
        if ($this->injectors['running']) {
            throw new \RuntimeException("Application already running");
        }

        // Save current app
        self::$self = $this;

        // Emit run
        $this->emit('run');

        // Set run
        $this->injectors['running'] = true;

        $_path = $this->input->path();

        if ($this->injectors['error']) set_error_handler(array($this, '__error'));

        try {
            // Emit "bundle" event
            $this->emit('bundle');
            /**
             * Loop bundles and lookup the bundle info and start the bundle
             */
            foreach ($this->injectors['bundles'] as $id => $options) {
                // Set id
                $id = isset($options['id']) ? $options['id'] : $id;

                // Set bootstrap file
                $bootstrap = isset($options['bootstrap']) ? $options['bootstrap'] : 'bootstrap.php';

                // Set dir to load
                $dir = isset($options['dir']) ? $options['dir'] : 'bundles/' . $id;

                // Check the file path
                if (!$file = $this->path($dir . '/' . $bootstrap)) {
                    throw new \InvalidArgumentException('Bundle "' . $id . '" can not bootstrap');
                }

                // Check if bootstrap file loaded
                if (isset(self::$loads[$file])) {
                    throw new \RuntimeException('Bundle "' . $id . '" can not bootstrap twice');
                }

                // Emit "bundle.[id]" event
                $this->emit('bundle.' . $id);

                // Check path and set bundle path
                $_bundle_path = $this->router->bundle_path;
                $this->router->bundle_path = !empty($options['path']) ? $options['path'] : '/' . $id;

                // Set variable for bootstrap file
                $app = $this;

                // Extract the variables
                extract($options);

                // Include file to bootstrap
                require $file;

                // Restore the bundle path
                if ($_bundle_path) $this->router->bundle_path = $_bundle_path;

                // Save to loads
                self::$loads[$file] = true;
            }

            // Start buffer
            if ($this->injectors['buffer']) ob_start();
            if (!in_array($this->router, $this->injectors['stacks'])) $this->injectors['stacks'][] = $this->router;

            // Emit "middleware" event
            $this->emit('middleware');

            // Process the stacks
            if (!$this->router->handle($this->injectors['stacks'], function ($stack) use ($_path) {
                // Try to match the path
                if (is_array($stack) && $stack[0] && strpos($_path, $stack[0]) === false) {
                    return false;
                }

                if (!is_array($stack)) {
                    return $stack;
                } else {
                    return Middleware::build($stack[1], $stack[2]);
                }
            })
            ) {
                $this->handleError('404');
            }

            // Write direct output to the head of buffer
            if ($this->injectors['buffer']) $this->output->write(ob_get_clean());
        } catch (Exception\Stop $e) {
        } catch (\Exception $e) {
            try {
                $this->handleError('exception', $e);
            } catch (Exception\Stop $e) {
            }
            $this->emit('error', $e);
        }

        // Send start
        $this->emit('flush');

        // Flush
        $this->flush();

        // Cleanup
        $this->__cleanup();

        // Set not running
        $this->injectors['running'] = false;
    }

    /**
     * Register or run error
     *
     * @param string   $type
     * @param callable $route
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function handleError($type, $route = null)
    {
        if (!isset($this->injectors['errors'][$type])) {
            throw new \InvalidArgumentException('Unknown error type "' . $type . '" to call');
        }

        if (is_string($route) && class_exists($route)
            || $route instanceof \Closure
        ) {
            // Set error handle
            $this->injectors['errors'][$type][2] = $route;
        } else if (!$this->injectors['exception'] && $route instanceof \Exception) {
            // Not handle exception and throw it
            throw $route;
        } else {
            // Clean the output buffer
            ob_get_level() && ob_clean();

            // Check handle route and run it
            if (empty($this->injectors['errors'][$type][2])
                || !$this->router->run($this->injectors['errors'][$type][2], array('error' => array(
                    'type'    => $type,
                    'error'   => $route,
                    'message' => $this->injectors['errors'][$type])
                ))
            ) {
                /**
                 * Get error information
                 */
                $error = $route instanceof \Exception ? $route : null;
                $title = $error ? $error->getMessage() : $this->injectors['errors'][$type][1];
                $code = $this->injectors['errors'][$type][0];
                $message = $route ? ($error ? $error->getFile() . ' [' . $error->getLine() . ']' : (string)$route) : '';

                if ($this->injectors['cli']) {
                    // Cli error show
                    $this->halt($code,
                        Console::text('[' . $title . '] ' . ($message ? $message : '"' . $this->input->path() . '"') . ' ', 'redbg') . "\n" .
                        ($this->injectors['debug'] && $error ? Console::text("\n[Error]\n", "yellow") . $error . "\n" : '')
                    );
                } else {
                    // Http error show
                    $this->output->status($this->injectors['errors'][$type][0]);
                    if ($this->injectors['debug']) {
                        // Debug error show
                        $this->renderView('Error', array(
                            'title'   => $title,
                            'message' => $message ? $message : 'Could not ' . $this->input->method() . ' ' . $this->input->path(),
                            'stacks'  => $this->injectors['debug'] && $error ? $error->getTraceAsString() : null
                        ));
                    } else {
                        // Normal error show
                        $this->renderView('Error', array(
                            'title'   => $title,
                            'message' => $message ? $message : 'Could not ' . $this->input->method() . ' ' . $this->input->path()
                        ));
                    }

                    $this->stop();
                }
            }
        }
    }

    /**
     * Get or set param
     *
     * @param string|null $param
     * @return array|bool|null
     */
    public function param($param = null)
    {
        if ($param === null) {
            return $this->input->params;
        } else {
            if (is_array($param)) {
                $this->input->params = $param;
                return true;
            } else {
                return isset($this->input->params[$param]) ? $this->input->params[$param] : null;
            }
        }
    }

    /**
     * Start assist for common functions
     */
    public function assisting()
    {
        $this->load(dirname(__DIR__) . '/assistant.php');
    }

    /**
     * Output the response
     *
     * @param int    $status
     * @param string $body
     * @throws Exception\Stop
     */
    public function halt($status, $body = '')
    {
        $this->output->status($status)->body($body);
        throw new Exception\Stop;
    }

    /**
     * Flush output
     */
    public function flush()
    {
        // Send headers
        if (!$this->injectors['cli']) {
            $this->output->sendHeader();
        }

        // Send
        echo $this->output->body();

        // Shutdown the output buffer
        $this->injectors['buffer'] && ob_get_level() && ob_end_flush();

        // Clear
        $this->output->clear();
    }

    /**
     * Stop
     *
     * @throws Exception\Stop
     */
    public function stop()
    {
        throw new Exception\Stop();
    }

    /**
     * Pass
     *
     * @throws Exception\Pass
     */
    public function pass()
    {
        ob_get_level() && ob_clean();
        throw new Exception\Pass();
    }

    /**
     * Auto load class
     *
     * @param string $class
     * @return bool
     */
    protected function __autoload($class)
    {
        if ($class{0} == '\\') $class = ltrim($class, '\\');

        // Alias check
        if (!empty($this->injectors['alias'][$class])) {
            class_alias($this->injectors['alias'][$class], $class);
            $class = $this->injectors['alias'][$class];
        }

        // Set the 99 high order for default autoload
        $available_path = array();

        // Autoload
        if ($this->injectors['autoload']) {
            $available_path[99] = $this->injectors['autoload'];
        }

        // Check other namespaces
        if ($this->injectors['namespaces']) {
            // Loop namespaces as autoload
            foreach ($this->injectors['namespaces'] as $_prefix => $_path) {
                // Check if match prefix
                if (strpos($class, $_prefix) === 0) {
                    // Set ordered path
                    $available_path[(99 - strlen($_prefix)) . $_prefix] = $_path;
                }
            }
            // Sort by order
            ksort($available_path);
        }

        // No available path, no continue
        if ($available_path) {
            // Set default file name
            $file_name = '';
            // PSR-0 check
            if ($last_pos = strrpos($class, '\\')) {
                $namespace = substr($class, 0, $last_pos);
                $class = substr($class, $last_pos + 1);
                $file_name = str_replace('\\', '/', $namespace) . '/';
            }
            // Get last file name
            $file_name .= str_replace('_', '/', $class) . '.php';
            // Loop available path for check
            foreach ($available_path as $_path) {
                // Check file if exists
                if ($file = stream_resolve_include_path($_path . '/' . $file_name)) {
                    require $file;
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Error handler for app
     *
     * @param int    $type
     * @param string $message
     * @param string $file
     * @param int    $line
     * @throws \ErrorException
     */
    public function __error($type, $message, $file, $line)
    {
        if (error_reporting() & $type) throw new \ErrorException($message, $type, 0, $file, $line);
    }

    /**
     * Release something
     */
    protected function __cleanup()
    {
        // Emit "end" event
        $this->emit('end');

        // Restore error handle if registered
        if ($this->injectors['error']) restore_error_handler();
    }

    /**
     * Shutdown handler for app
     */
    public function __shutdown()
    {
        // Emit "exit" event
        $this->emit('exit');
        if (!$this->injectors['running']) return;

        // Check error and process it
        if (($error = error_get_last())
            && in_array($error['type'], array(E_PARSE, E_ERROR, E_USER_ERROR, E_COMPILE_ERROR, E_CORE_ERROR))
        ) {
            try {
                $this->handleError('crash', new \ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']));
            } catch (Exception\Stop $e) {
            }
            $this->emit('crash', $error);
            $this->flush();
        }

        // Cleanup
        $this->__cleanup();
    }
}
