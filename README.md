#Minecraft Rcon Library

Minecraft Rcon, library to make rcon requests and get response back.

### Installation
```
composer require mops1k/minecraft-rcon
```
###Example of usage
```php
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

```
