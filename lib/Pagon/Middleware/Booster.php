<?php

namespace Pagon\Middleware;

use Pagon\Logger;
use Pagon\Middleware;
use Pagon\Utility\Cryptor;

class Booster extends Middleware
{
    /**
     * @var array Default options
     */
    protected $injectors = array(
        'logger'  => 'log',
        'cryptor' => 'crypt'
    );

    public function call()
    {
        $app = $this->app;

        // Set encoding
        if (function_exists('iconv') && PHP_VERSION_ID < 50600) {
            // These are settings that can be set inside code
            iconv_set_encoding('internal_encoding', 'UTF-8');
            iconv_set_encoding('input_encoding', 'UTF-8');
            iconv_set_encoding('output_encoding', 'UTF-8');
        } elseif (PHP_VERSION_ID >= 50600) {
            ini_set('default_charset', 'UTF-8');
        }

        mb_internal_encoding($app->charset);

        // Configure timezone
        date_default_timezone_set($app->timezone);

        // Share the cryptor for the app
        if ($_crypt = $app->get($this->injectors['cryptor'])) {
            $app->cryptor = function () use ($_crypt) {
                return new Cryptor($_crypt);
            };
        }

        // Share the logger for the app
        if ($_log = $app->get($this->injectors['logger'])) {
            $app->logger = Logger::$default = new Logger($_log);
        }

        $this->next();
    }
}