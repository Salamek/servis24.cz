# servis24.cz

<div style="width:100%; height:50px; background:#f2dede; border: 1px solid #ebccd1; color:#a94442;">
  This code is using your LIVE access ID and Password, use only on your own risk.
</div>

## API for servis24.cz using servis24.cz as source of data (parsing loaded HTML). Currently it supports only signin, and getting list of transactions


### Installation

```bash
composer require salamek/servis24api

```

### Usage

```php
<?php

require_once "vendor/autoload.php";

$active24 = new Servis24('ACCOUNT_ID', 'ACCOUNT_PASSWORD');
try
{
  //Return transactions as array filtred by Servis24::TRANSACTION_REVENUES
  $array = $active24->getTransactions('BANK_ACCOUNT_NUMBER', Servis24::TRANSACTION_REVENUES);
  echo '<pre>';
  print_r($array);
  echo '</pre>';
}
catch (\Exception $e)
{
  //It is posible that call will fail but it is not a configuration or code issue... servis24.cz is rly bad piece of sh*t and it act very strange on relogin
  //So i suggest to try calling it one more time after error is catched (and use sleep between calls)
  echo $e->getMessage();
}
```
