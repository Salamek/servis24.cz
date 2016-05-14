<?php
/**
 * Copyright (C) 2016 Adam Schubert <adam.schubert@sg1-game.net>.
 */

use Salamek\Servis24;

class FetchTest extends PHPUnit_Framework_TestCase
{
    /** @var Servis24 */
    public $servis24;

    public function setUp()
    {
        $this->servis24 = new Servis24('fake_id', 'fake_password');
    }

    /**
     * @test
     * @expectedException \Exception
     */
    public function getRawBody()
    {
        $transactionList = $this->servis24->getTransactions('fake_account_number', Servis24::TRANSACTION_ALL);
    }
}