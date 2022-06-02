<?php

namespace trading_engine\util;
/**
 * class Singleton
 */
class Singleton
{
    public static function getInstance()
    {
        static $inst = [];

        $called_class = get_called_class();
        if (!isset($inst[$called_class]))
        {
            $inst[$called_class] = new static();
        }

        return $inst[$called_class];
    }

}