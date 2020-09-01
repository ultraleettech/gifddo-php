<?php

namespace Gifddo\Tests;

use Gifddo\Client;
use Gifddo\Helpers;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as Guzzle;
use Gifddo\Exceptions\ClientException;
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
        $params = $client->initiate([
            'amount' => 10,
            'reference' => 1337,
            'email' => 'test@test.com',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'return_url' => '',
        ]);

        self::assertIsString($params['VK_AMOUNT']);
        self::assertIsString($params['VK_REF']);
    }

    public function testGetUrlReturnsCorrectUrl()
    {
        $client = new Client('TEST', static::$privateKey, true);
        self::assertSame('https://gifddo.staging.elevate.ee/api/giftlink', $client->getUrl());

        $client = new Client('TEST', static::$privateKey);
        self::assertSame('https://gifddo.com/api/giftlink', $client->getUrl());
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
        $params = $client->initiate($params);
        $signature = base64_decode($params['VK_MAC']);
        unset($params['VK_MAC']);
        $pack = Helpers::pack($params);
        self::assertSame(1, openssl_verify($pack, $signature, self::$publicKey, \OPENSSL_ALGO_SHA1));
    }

    /**
     * @runInSeparateProcess
     */
    public function testRequestSetsLocationHeader()
    {
        $client = new Client('TEST', self::$privateKey, true);

        $url = $client->getUrl() . '/test';
        $response = new Response(200, ['Location' => $url]);

        $guzzle = $this->createStub(Guzzle::class);
        $guzzle->method('post')
            ->willReturn($response);

        $client->setGuzzle($guzzle);
        $client->request([]);

        self::assertContains("Location: $url", xdebug_get_headers());
    }

    public function testRequestThrowsWhenHeadersAlreadySent()
    {
        $client = new Client('TEST', self::$privateKey, true);

        $url = $client->getUrl() . '/test';
        $response = new Response(200, ['Location' => $url]);

        $guzzle = $this->createStub(Guzzle::class);
        $guzzle->method('post')
            ->willReturn($response);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Gifddo API request needs to be performed before HTTP request headers are sent.');

        $client->setGuzzle($guzzle);
        $client->request([]);
    }
}
