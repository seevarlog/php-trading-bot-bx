<?php

namespace trading_engine\util;


function convertTime($min): int
{
    $ret = [
        1 => 3,
        3 => 5,
        5 => 15,
        15 => 60,
        60 => 240,
        240 => 1440,
        1440 => 1440
    ];

    return $ret[$min];
}
