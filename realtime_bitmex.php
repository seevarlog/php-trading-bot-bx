<?php
require('vendor/autoload.php');

$client = new Client("wss://testnet.bitmex.com/realtime?subscribe=instrument:XBTUSD");
echo $client->receive();