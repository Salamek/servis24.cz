<?php
/**
 * Copyright (C) 2016 Adam Schubert <adam.schubert@sg1-game.net>.
 */

use Salamek\Servis24;

class FetchTest extends PHPUnit_Framework_TestCase
{
    /** @var Servis24 */
    private $servis24;

    private $configuration;

    public function setUp()
    {
        $configurationJson = file_get_contents(__DIR__.'/config.json');
        $this->configuration = json_decode($configurationJson);
        $this->servis24 = new Servis24($this->configuration->username, $this->configuration->password, 'cookiejar.txt');
    }
    
    /**
     * @test
     * @expectedException \Exception
     */
    public function getRawBody()
    {
        $transactionList = $this->servis24->getTransactions($this->configuration->account, Servis24::TRANSACTION_ALL);

        print_r($transactionList);
    }
}