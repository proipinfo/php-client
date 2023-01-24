# PHP client ProIP.info

### Requirements

* PHP 5.6 or newer
* bcmath extension

### Installation

```composer log
composer require proipinfo/php-client
```

### Usage
```php
<?php

require dirname(__FILE__) . '/vendor/autoload.php';

$ip='8.8.8.8';
$db = new ProIPInfo\DbStream('/path/to/db', true);
$client = new ProIPInfo\Client($db);
$rec = $client->getRecord($ip);
if (empty($rec)) {
    throw(new \Exception('Not found'));
}
echo $client->countryCode;
echo $client->region;
echo $client->city;
echo $client->ISP;
```

More information
[docs on ProIP.info](https://proip.info/docs/php-client)
