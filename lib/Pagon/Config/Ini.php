<?php

namespace Pagon\Config;

class Ini
{
    /**
     * Parse ini string
     *
     * @param string $ini
     * @return array
     */
    public static function decode($ini)
    {
        $ini = parse_ini_string($ini);

        $array = array();
        foreach ($ini as $key => $value) {
            $config = & $array;
            $namespaces = explode('.', $key);
            foreach ($namespaces as $namespace) {
                if (!isset($config[$namespace])) $config[$namespace] = array();
                $config = & $config[$namespace];
            }
            $config = $value;
        }

        return $array;
    }

    /**
     * Build String from array
     *
     * @param array $config
     * @return string
     */
    public static function encode(array $config)
    {
        $lines = static::encodeToLines($config);
        $string = '';
        foreach ($lines as $k => $v) {
            $string .= "{$k} = {$v}" . PHP_EOL;
        }
        return $string;
    }

    /**
     * Dump array to lines
     *
     * @param array $array
     * @return array
     */
    protected static function encodeToLines($array)
    {
        $ini_lines = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                foreach (self::encodeToLines($value) as $k => $v) {
                    $ini_lines[$key . '.' . $k] = $v;
                }
            } else {
                $ini_lines[$key] = $value;
            }
        }
        return $ini_lines;
    }
}
