<?php

namespace Pagon\Config;

if (!class_exists('\Symfony\Component\Yaml\Yaml')) {
    throw new \RuntimeException("Use Yaml parser need Symfony/Yaml installed, use `composer update` or include manually.");
}

use Symfony\Component\Yaml\Yaml as YamlParser;

class Yaml
{
    /**
     * Parser yaml
     *
     * @param string $yaml
     * @return array
     */
    public static function decode($yaml)
    {
        return YamlParser::parse($yaml);
    }

    /**
     * Dump array to yaml
     *
     * @param array $array
     * @return string
     */
    public static function encode(array $array)
    {
        return YamlParser::dump($array);
    }
}
