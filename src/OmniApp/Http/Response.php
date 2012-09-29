<?php

namespace OmniApp\Http;

class Response
{
    public static $messages = array(
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',

        // Success 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',

        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found', // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        // 306 is deprecated but reserved
        307 => 'Temporary Redirect',

        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',

        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        509 => 'Bandwidth Limit Exceeded'
    );

    protected static $status = 200;
    protected static $headers = array();
    protected static $body = '';
    protected static $cookies = array();
    protected static $content_type = 'text/html';
    protected static $charset = 'utf-8';
    protected static $length = 0;


    /**
     * Set body
     *
     * @static
     * @param string $content
     * @return string
     */
    public static function body($content = null)
    {
        if ($content !== null) self::write($content, true);

        return self::$body;
    }

    /**
     * Get and set length
     *
     * @param  int|null $length
     * @return int
     */
    public static function length($length = null)
    {
        if (!is_null($length)) {
            self::$length = (int)$length;
        }

        return self::$length;
    }

    /**
     * Get or set charset
     *
     * @param $charset
     * @return string
     */
    public static function charset($charset = null)
    {
        if ($charset) {
            self::$charset = $charset;
        }
        return self::$charset;
    }

    /**
     * Write body
     *
     * @param      $body
     * @param bool $replace
     * @return string
     */
    public static function write($body, $replace = false)
    {
        if ($replace) {
            self::$body = $body;
        } else {
            self::$body .= (string)$body;
        }
        self::$length = strlen(self::$body);

        return self::$body;
    }

    /**
     * Set status code
     *
     * @static
     * @param int $status
     * @return int|Response
     * @throws \Exception
     */
    public static function status($status = null)
    {
        if ($status === null) {
            return self::$status;
        } elseif (array_key_exists($status, self::$messages)) {
            return self::$status = (int)$status;
        } else throw new \Exception('Unknown status :value', array(':value' => $status));
    }

    /**
     * Set header
     *
     * @param null $key
     * @param null $value
     * @return array|null
     */
    public static function header($key = null, $value = null)
    {
        if (func_num_args() === 0) {
            return self::$headers;
        } elseif (func_num_args() === 1) {
            return self::$headers[strtoupper(str_replace('_', '-', $key))];
        } else {
            return self::$headers[strtoupper(str_replace('_', '-', $key))] = $value;
        }
    }

    /**
     * Set content type
     *
     * @param $mime_type
     * @return null
     */
    public static function contentType($mime_type)
    {
        if ($mime_type) {
            if (!strpos($mime_type, '/')) {
                $mime_type = MimeType::get($mime_type);
            }

            self::header('Content-Type', $mime_type . '; charset=' . self::charset());
        }

        return self::header('Content-Type');
    }

    /**
     * Get or set cookie
     *
     * @param $key
     * @param $value
     * @return array|string|bool
     */
    public static function cookie($key, $value)
    {
        if (func_num_args() === 0) {
            return self::$headers;
        } elseif (func_num_args() === 1) {
            return self::$headers[$key];
        }
        return setcookie($key, $value) ? $value : false;
    }

    /**
     * Get message by code
     *
     * @param $status
     * @return null
     */
    public static function message($status = null)
    {
        if (!$status) $status = self::$status;

        if (isset(self::$messages[$status])) {
            return self::$messages[$status];
        }
        return null;
    }

    /**
     * Has send header
     *
     * @return bool
     */
    public static function hasSendHeader()
    {
        return headers_sent();
    }

    /**
     * Send headers
     */
    public static function sendHeader()
    {
        if (self::hasSendHeader() === false) {
            header(sprintf('HTTP/%s %s %s', Request::protocol(), self::status(), self::message()));

            foreach (self::header() as $name => $value) {
                $h_values = explode("\n", $value);
                foreach ($h_values as $h_val) {
                    header("$name: $h_val", false);
                }
            }
        }
    }

    /**
     * Set expires time
     *
     * @param string|int $time
     * @return array|null
     */
    public static function expires($time = null)
    {
        if ($time) {
            if (is_string($time)) {
                $time = strtotime($time);
            }
            self::header('Expires', gmdate(DATE_RFC1123, $time));
        }
        return self::header('Expires');
    }

    /**
     * To json
     *
     * @param $data
     */
    public static function json($data)
    {
        self::contentType('json');
        self::body(json_encode($data));
    }

    /**
     * To jsonp
     *
     * @param $callback
     * @param $data
     */
    public static function jsonp($data, $callback)
    {
        self::contentType('js');
        self::body($callback . '(' . json_encode($data) . ');');
    }

    /**
     * To xml
     *
     * @param object|array $data
     * @param string       $root
     * @param string       $item
     */
    public static function xml($data, $root = 'root', $item = 'item')
    {
        self::contentType('xml');
        self::body(\OmniApp\Util\XML::fromArray($data, $root, $item));
    }

    /**
     * Redirect url
     *
     * @param     $url
     * @param int $status
     */
    public static function redirect($url, $status = 302)
    {
        self::$status = $status;
        self::$headers['Location'] = $url;
    }

    /**
     * Is empty?
     *
     * @return bool
     */
    public static function isEmpty()
    {
        return in_array(self::$status, array(201, 204, 304));
    }

    /**
     * Is 200 ok?
     *
     * @return bool
     */
    public static function isOk()
    {
        return self::$status === 200;
    }

    /**
     * Is successful?
     *
     * @return bool
     */
    public static function isSuccessful()
    {
        return self::$status >= 200 && self::$status < 300;
    }

    /**
     * Is redirect?
     *
     * @return bool
     */
    public static function isRedirect()
    {
        return in_array(self::$status, array(301, 302, 303, 307));
    }

    /**
     * Is forbidden?
     *
     * @return bool
     */
    public static function isForbidden()
    {
        return self::$status === 403;
    }

    /**
     * Is found?
     *
     * @return bool
     */
    public static function isNotFound()
    {
        return self::$status === 404;
    }

    /**
     * Is client error?
     *
     * @return bool
     */
    public static function isClientError()
    {
        return self::$status >= 400 && self::$status < 500;
    }

    /**
     * Is server error?
     *
     * @return bool
     */
    public static function isServerError()
    {
        return self::$status >= 500 && self::$status < 600;
    }
}