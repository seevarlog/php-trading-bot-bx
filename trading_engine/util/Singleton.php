<?php

namespace trading_engine\util;
/**
 * class Singleton
 */
class Singleton
{
    public static function getInstance()
    {
        static $instance = null;

        if ($instance === null)
        {
            $instance = new static;
        }

        return $instance;
    }

    private function __construct()
    {
    }

}