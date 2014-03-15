<?php

/**
 * Classic route
 *
 * It's classic "controller/action" path mapping
 *
 * You can set route
 *
 *  $app->all('man/:action', 'ManController')
 *
 * Or
 *
 *  $app->autoRoute(function($path) use ($app) {
 *      list($ctrl, $act) = explode('/', $path);
 *      if (!$act) return false;
 *
 *      $app->param('action', $act);
 *      return ucfirst($ctrl);
 *  });
 *
 * Then add method to your 'ManController'
 *
 *  class ManController extend \Pagon\Route\Classic {
 *      function actionLogin() {
 *          // to do some thing
 *      }
 *  }
 *
 * Then you can visit
 *
 *  GET http://domain/man/login
 *
 */

namespace Pagon\Route;

use Pagon\Route;

/**
 * Classic base route
 *
 * @package Pagon\Route
 */
abstract class Classic extends Route
{
    /**
     * Default action if not action assign
     *
     * @var string
     */
    protected $default_action = 'index';

    /**
     * Action prefix for method name
     *
     * @var string
     */
    protected $action_prefix = 'action';

    public function call()
    {
        // Set params
        $this->params = $this->input->params;

        // Set action
        if (!$action = $this->input->param('action')) {
            $action = $this->default_action;
        }

        // Set method name
        if (!$method = $this->action_prefix . $action) {
            throw new \InvalidArgumentException("Action must be specified");
        }

        // Check method
        $this->before();

        // Fallback call all
        if (!method_exists($this, $method) && method_exists($this, 'missing')) {
            $method = 'missing';
        }
        $this->$method($this->input, $this->output);
        $this->after();
    }
}