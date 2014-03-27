<?php

namespace Pagon;

/**
 * Middleware
 * structure of base middleware
 *
 * @package Pagon
 *
 */
abstract class Middleware extends EventEmitter
{
    const _CLASS_ = __CLASS__;

    /**
     * @var App
     */
    public $app;

    /**
     * @var Http\Input|Command\Input
     */
    public $input;

    /**
     * @var Http\Output|Command\Output
     */
    public $output;

    /**
     * @var callable
     */
    protected $next;

    /**
     * Create new middleware or route
     *
     * @param string|\Closure $route
     * @param array           $options
     * @param array           $prefixes
     * @throws \InvalidArgumentException
     * @return bool|Route|callable
     */
    public static function build($route, $options = null, array $prefixes = array())
    {
        if (is_object($route)) return $route;

        if (!is_string($route)) throw new \InvalidArgumentException('The parameter $route need string');

        $prefixes[] = '';
        $prefixes[] = __NAMESPACE__ . "\\Middleware";

        /**
         * Support direct method like "Index@start"
         */
        if (strpos($route, '@')) {
            $arr = explode('@', $route);
            $route = $arr[0];
            $options = (array)$options + array('entry' => $arr[1]);
        }

        foreach ($prefixes as $namespace) {
            if (!is_subclass_of($class = ($namespace ? $namespace . '\\' : '') . $route, __CLASS__, true)) continue;

            return new $class((array)$options);
        }

        throw new \InvalidArgumentException("Non-exists route class '$route'");
    }

    /**
     * @return mixed
     */
    abstract function call();

    /**
     * @param $input
     * @param $output
     * @param $next
     */
    public function __invoke($input, $output, $next)
    {
        $this->injectors['input'] = $input;
        $this->injectors['output'] = $output;
        $this->injectors['app'] = $input->app;
        $this->input = & $this->injectors['input'];
        $this->output = & $this->injectors['output'];
        $this->app = & $this->injectors['app'];
        $this->next = $next;
        $this->call();
    }

    /**
     * Call next
     */
    public function next()
    {
        call_user_func($this->next);
    }
}
