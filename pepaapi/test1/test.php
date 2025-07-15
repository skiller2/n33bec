<?php
require "anvizcmd.php";
require "Protocol.php";
require "Tools.php";


//third parameter is optional, if not set class uses default config.ini we've created earlier

function transmit($msg){
    $client = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_connect($client, '127.0.0.1', 5010);



}

$anviz = new AnvizCommand();

echo "Info1: " . var_export($anviz->getNetwork(), true) . "\n";

