<?php

namespace Gifddo\Tests;

use Gifddo\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testValidatesArgumentCountForConstructor()
    {
        $this->expectException(\ArgumentCountError::class);
        $client = new Client();
    }

    public function testValidatesArgumentTypesForConstructor()
    {
        $this->expectException(\TypeError::class);
        $client = new Client(null, null, null, null);
    }

    public function testConstructor()
    {
        $client = new Client('', '', '', true);
        $this->assertInstanceOf(Client::class, $client);
    }
}
