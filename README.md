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

$db = new ProIPInfo\DbStream('/path/to/db', true);
$client = new ProIPInfo\Client($db);

$rec = $client->getRecord('8.8.8.8');
if (empty($rec)) {
    throw(new \Exception('Not found'));
}

echo $rec->countryCode;
echo $rec->region;
echo $rec->city;
echo $rec->ISP;
```

More information
[docs on ProIP.info](https://proip.info/docs/php-client)
