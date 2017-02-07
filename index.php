<?php
/**
 * Example of using
 */
use MinecraftRcon\Rcon;

require_once 'vendor/autoload.php';

// Connect to server
$rcon = new Rcon();
$rcon
    ->setHost('localhost')
    ->setPort(25575)
    ->setPassword('password')
    ->connect()
;

// Send command
$rcon->sendCommand('time set 12');

echo($rcon->getResponse(Rcon::RESPONSE_FORMATTED));
