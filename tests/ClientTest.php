<?php
/**
 * Gifddo PHP Client
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * @author Rene Aavik <renx81@gmail.com>
 * @copyright 2020-present Gifddo
 *
 * @link https://gifddo.com/
 */

declare(strict_types=1);

namespace Gifddo\Tests;

use Gifddo\Client;
use Gifddo\Helpers;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as Guzzle;
use Gifddo\Exceptions\ClientException;
use Gifddo\Exceptions\RequestException;
use Gifddo\Exceptions\UndefinedParameterException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;

class ClientTest extends TestCase
{
    private static $publicKey;
    private static $privateKey;
    private static $gifddoPublicKey;
    private static $gifddoPrivateKey;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$publicKey = file_get_contents(__DIR__ . '/keys/pubkey.pem');
        static::$privateKey = file_get_contents(__DIR__ . '/keys/privkey.pem');
        static::$gifddoPublicKey = file_get_contents(__DIR__ . '/keys/gifddo-public.pem');
        static::$gifddoPrivateKey = file_get_contents(__DIR__ . '/keys/gifddo-private.pem');
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

    public function testInitiateThrowsWhenEmptyKey()
    {
        $client = new Client('TEST', '', true);

        $this->expectException(ClientException::class);
        $client->initiate([
            'amount' => 10,
            'reference' => 1337,
            'email' => 'test@test.com',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'return_url' => '',
        ]);
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

    public function testRequestReturnsUrlIfSecondArgumentIsTrue()
    {
        $client = new Client('TEST', self::$privateKey, true);

        $url = $client->getUrl() . '/test';
        $response = new Response(200, ['Location' => $url]);

        $guzzle = $this->createStub(Guzzle::class);
        $guzzle->method('post')
            ->willReturn($response);

        $client->setGuzzle($guzzle);
        $location = $client->request([], true);

        self::assertSame($url, $location);
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

    public function testRequestThrowsWhenCaCertNotFound()
    {
        $client = new Client('TEST', self::$privateKey, true);

        $guzzle = $this->getMockBuilder(Guzzle::class)
            ->onlyMethods(['request'])
            ->getMock();

        $guzzle->expects(self::once())
            ->method('request')
            ->willThrowException(new GuzzleRequestException('cURL error 60', new Request('post', '')));

        $this->expectException(RequestException::class);
        $client->setGuzzle($guzzle);
        $client->request([]);
    }

    public function testRequestThrowsWhenNoRedirectionInApiResponse()
    {
        $client = new Client('TEST', self::$privateKey, true);

        $guzzle = $this->getMockBuilder(Guzzle::class)
            ->onlyMethods(['request'])
            ->getMock();

        $guzzle->expects(self::once())
            ->method('request')
            ->willReturn(new Response());

        $this->expectException(ClientException::class);
        $client->setGuzzle($guzzle);
        $client->request([]);
    }

    public function testVerifySuccessfulResponseWithCorrectMac()
    {
        $params = $testParams = [
            'VK_SERVICE' => Client::SUCCESSFUL_RESPONSE_CODE,
            'VK_VERSION' => '008',
            'VK_SND_ID' => 'GIFDDO',
            'VK_REC_ID' => 'TEST',
            'VK_STAMP' => Helpers::randomString(),
            'VK_T_NO' => '1234567890',
            'VK_AMOUNT' => '1',
            'VK_CURR' => 'EUR',
            'VK_REC_ACC' => '',
            'VK_REC_NAME' => 'Gifddo',
            'VK_SND_ACC' => '',
            'VK_SND_NAME' => 'Test',
            'VK_REF' => '1337',
            'VK_MSG' => 'test@test.com|John|Smith',
            'VK_T_DATETIME' => Helpers::dateString(),
            'VK_AUTO' => 'Y',
        ];

        unset($testParams['VK_AUTO']);
        openssl_sign(Helpers::pack($testParams), $signature, static::$gifddoPrivateKey, \OPENSSL_ALGO_SHA1);

        $client = new Client('TEST', self::$privateKey, true);
        $client->setPublicKey(static::$gifddoPublicKey);
        self::assertTrue($client->verify($params, base64_encode($signature)));
    }

    public function testVerifyFailedResponseWithCorrectMac()
    {
        $params = $testParams = [
            'VK_SERVICE' => Client::UNSUCCESSFUL_RESPONSE_CODE,
            'VK_VERSION' => '008',
            'VK_SND_ID' => 'GIFDDO',
            'VK_REC_ID' => 'TEST',
            'VK_STAMP' => Helpers::randomString(),
            'VK_REF' => '1337',
            'VK_MSG' => 'User cancelled the transaction',
            'VK_AUTO' => 'Y',
        ];

        unset($testParams['VK_AUTO']);
        openssl_sign(Helpers::pack($testParams), $signature, static::$gifddoPrivateKey, \OPENSSL_ALGO_SHA1);

        $client = new Client('TEST', self::$privateKey, true);
        $client->setPublicKey(static::$gifddoPublicKey);
        self::assertTrue($client->verify($params, base64_encode($signature)));
    }

    public function testVerifyResponseWithIncorrectMac()
    {
        $params = $testParams = [
            'VK_SERVICE' => Client::UNSUCCESSFUL_RESPONSE_CODE,
            'VK_VERSION' => '008',
            'VK_SND_ID' => 'GIFDDO',
            'VK_REC_ID' => 'TEST',
            'VK_STAMP' => Helpers::randomString(),
            'VK_REF' => '1337',
            'VK_MSG' => 'User cancelled the transaction',
            'VK_AUTO' => 'Y',
        ];

        // self-signed message
        unset($testParams['VK_AUTO']);
        openssl_sign(Helpers::pack($testParams), $signature, static::$privateKey, \OPENSSL_ALGO_SHA1);

        $client = new Client('TEST', self::$privateKey, true);
        $client->setPublicKey(static::$gifddoPublicKey);
        self::assertFalse($client->verify($params, base64_encode($signature)));
    }

    public function testGetPublicKeyReturnsFileContents()
    {
        $testKey = file_get_contents(__DIR__ . '/../keys/gifddo-test.pem');
        $liveKey = file_get_contents(__DIR__ . '/../keys/gifddo-live.pem');

        $client = new Client('TEST', self::$privateKey, true);
        self::assertSame($testKey, $client->getPublicKey());

        $client = new Client('TEST', self::$privateKey);
        self::assertSame($liveKey, $client->getPublicKey());
    }

    public function testGetGuzzle()
    {
        $client = new Client('TEST', self::$privateKey, true);
        self::assertInstanceOf(Guzzle::class, $client->getGuzzle());
    }
}
