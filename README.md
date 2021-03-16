# PHP client ProIP.info

### Installation

```composer log
composer require proipinfo/php-client
```

### Usage
```php
<?php

require dirname(__FILE__) . '/vendor/autoload.php';

$ip='8.8.8.8';
$client = new ProIPInfo\Client('/path/to/db');
$rec = $client->getRecord($ip);
if (empty($rec)) {
    throw(new \Exception('Not found'));
}
echo $client->$countryCode;
echo $client->$region;
echo $client->$city;
echo $client->$ISP;
```

More information
[docs on ProIP.info](https://proip.info/docs/php-client)
