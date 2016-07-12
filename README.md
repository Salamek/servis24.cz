# servis24.cz

[![Build Status](https://travis-ci.org/Salamek/servis24.cz.svg?branch=master)](https://travis-ci.org/Salamek/servis24.cz)

<div style="width:100%; height:50px; background:#f2dede; border: 1px solid #ebccd1; color:#a94442;">
  This code is using your LIVE access ID and Password, use only on your own risk.
</div>

## API for servis24.cz using servis24.cz as source of data (parsing loaded HTML and CSV). Currently it supports only signin, and getting list of transactions


### Installation

```bash
composer require salamek/servis24api

```

### Setup on servis24.cz

You must enable DAILY extracts on account (ACCOUNT_ID) you wish to watch

### Usage

```php
<?php

require_once "vendor/autoload.php";

use Salamek\Servis24;

$servis24 = new Servis24('ACCOUNT_ID', 'ACCOUNT_PASSWORD', '/my/secured/storage');


//Return transactions as array filtred by dateFrom and dateTo
$array = $servis24->getTransactions('BANK_ACCOUNT_NUMBER', new \DateTime('2016-01-20'), new \DateTime());
echo '<pre>';
print_r($array);
echo '</pre>';

```
