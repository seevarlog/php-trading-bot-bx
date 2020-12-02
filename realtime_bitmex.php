<?php
require('vendor/autoload.php');

use

$client = new Client("wss://testnet.bitmex.com/realtime?subscribe=instrument:XBTUSD");
echo $client->receive();