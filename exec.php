<?php
require "XmlRpcReceiver.php";

echo "Connecting to port $_ENV[XML_RPC_PORT]\n";

$config = [
    "xmlRpcIp"      => $_ENV["XML_RPC_IP"],
    "xmlRpcPort"    => $_ENV["XML_RPC_PORT"],
    "authLogin"     => "xxxxxxxxxxxxxxxxx",
    "authPassword"  => "xxxxxxxxxxxxxxxxxx",
    "webSocketUrl"  => $_ENV["WEB_SOCKET_URL"]
];

$c = new XmlRpcReceiver($config);