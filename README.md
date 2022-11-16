# Minecraft Rcon Library

Simple Minecraft Rcon library to make rcon requests and get response back.

### Installation
```shell
composer require mops1k/minecraft-rcon
```

### Example of usage
```php
<?php
use MinecraftRcon\Rcon;
use MinecraftRcon\RconExceptionInterface;

require_once 'vendor/autoload.php';

try {
    $rcon = new Rcon(
        'localhost',
        25575,
        'password'
    );

    echo $rcon->send('time set 12')->getResponse(Rcon::RESPONSE_FORMATTED);
} catch (RconExceptionInterface $rconException) {
    echo $rconException->getMessage();
}
```
