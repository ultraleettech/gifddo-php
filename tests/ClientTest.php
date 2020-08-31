<?php

namespace Gifddo\Tests;

use Gifddo\Client;
use Gifddo\Helpers;
use PHPUnit\Framework\TestCase;
use Gifddo\Exceptions\UndefinedParameterException;

class ClientTest extends TestCase
{
    private static $publicKey;
    private static $privateKey;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$publicKey = file_get_contents(__DIR__ . '/keys/pubkey.pem');
        static::$privateKey = file_get_contents(__DIR__ . '/keys/privkey.pem');
    }

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

    public function testInitiateWithMissingArguments()
    {
        $client = new Client('TEST', static::$privateKey, true);

        $this->expectException(UndefinedParameterException::class);
        $client->initiate([]);
    }

    public function testInitiateWithNumericAmountAndRef()
    {
        $client = new Client('TEST', static::$privateKey, true);
        $data = $client->initiate([
            'amount' => 10,
            'reference' => 1337,
            'email' => 'test@test.com',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'return_url' => '',
        ]);

        self::assertIsString($data['params']['VK_AMOUNT']);
        self::assertIsString($data['params']['VK_REF']);
    }

    public function testInitiateReturnsCorrectUrl()
    {
        $params = [
            'amount' => '10',
            'reference' => '1337',
            'email' => 'test@test.com',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'return_url' => '',
        ];

        $client = new Client('TEST', static::$privateKey, true);
        $data = $client->initiate($params);
        self::assertSame('https://gifddo.staging.elevate.ee/api/giftlink', $data['url']);

        $client = new Client('TEST', static::$privateKey);
        $data = $client->initiate($params);
        self::assertSame('https://gifddo.com/api/giftlink', $data['url']);
    }

    public function testInitiateReturnsCorrectMac()
    {
        $params = [
            'amount' => '10',
            'reference' => '1337',
            'email' => 'test@test.com',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'return_url' => '',
        ];

        $client = new Client('TEST', self::$privateKey, true);
        ['params' => $params] = $client->initiate($params);
        $signature = base64_decode($params['VK_MAC']);
        unset($params['VK_MAC']);
        $pack = Helpers::pack($params);
        self::assertSame(1, openssl_verify($pack, $signature, self::$publicKey, \OPENSSL_ALGO_SHA1));
    }
}
