<?php

namespace Pagon\Config;

class Json
{
    /**
     * Parse ini string
     *
     * @param string $json
     * @return array
     */
    public static function decode($json)
    {
        return json_decode($json, true);
    }

    /**
     * Build String from array
     *
     * @param array $json
     * @return string
     */
    public static function encode(array $json)
    {
        return json_encode($json);
    }
}
