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

namespace Gifddo;

use Throwable;
use GuzzleHttp\Client as Guzzle;
use Gifddo\Exceptions\ClientException;
use Gifddo\Exceptions\RequestException;
use GuzzleHttp\Exception\GuzzleException;
use Gifddo\Exceptions\UndefinedParameterException;

/**
 * Class Client
 */
class Client
{
    const LIVE_URL = 'https://gifddo.com/api/giftlink';
    const TEST_URL = 'https://gifddo.staging.elevate.ee/api/giftlink';
    const REQUEST_CODE = '1012';
    const SUCCESSFUL_RESPONSE_CODE = '1111';
    const UNSUCCESSFUL_RESPONSE_CODE = '1911';

    /**
     * @var string
     */
    private $merchantId;

    /**
     * @var string
     */
    private $privateKey;

    /**
     * @var bool
     */
    private $isTest;

    /**
     * @var string
     */
    private $publicKey;

    /**
     * @var Guzzle
     */
    private $guzzle;

    /**
     * Client constructor.
     *
     * @param string $merchantId
     * @param string $privateKey
     * @param bool   $isTest
     */
    public function __construct(string $merchantId, string $privateKey, bool $isTest = false)
    {
        $this->merchantId = $merchantId;
        $this->privateKey = $privateKey;
        $this->isTest = $isTest;
    }

    /**
     * Initialize request data to be sent to the Gifddo API.
     *
     * @param array $data       {
     *
     * @type string $stamp      Unique string identifier for the payment (20). Will be auto-generated if not provided.
     * @type string $amount     Amount to pay. Required.
     * @type string $currency   Payment currency. Defaults to EUR.
     * @type string $reference  Reference number. Required.
     * @type string $email      Client's e-mail address. Required.
     * @type string $first_name Client's first name. Required.
     * @type string $last_name  Client's last name. Required.
     * @type string $return_url URL to return the client to. Status updates will also be sent to this URL. Required.
     * @type string $cancel_url URL to send the client to in case payment failed or was cancelled. Defaults to
     *                          the value of $return_url.
     * }
     *
     * @return array            Array of parameters to pass to the request() method.
     *
     * @throws UndefinedParameterException
     * @throws ClientException
     */
    public function initiate(array $data): array
    {
        try {
            $params = [
                'VK_SERVICE' => static::REQUEST_CODE,
                'VK_VERSION' => '008',
                'VK_SND_ID' => $this->merchantId,
                'VK_STAMP' => $data['stamp'] ?? Helpers::randomString(),
                'VK_AMOUNT' => (string) $data['amount'],
                'VK_CURR' => $data['currency'] ?? 'EUR',
                'VK_REF' => (string) $data['reference'],
                'VK_MSG' => "{$data['email']}|{$data['first_name']}|{$data['last_name']}",
                'VK_RETURN' => $data['return_url'],
                'VK_CANCEL' => $data['cancel_url'] ?? $data['return_url'],
                'VK_DATETIME' => Helpers::dateString(),
            ];
        } catch (Throwable $exception) {
            $params = [];
            if (preg_match('/Undefined index: (\\w+)/', $exception->getMessage(), $matches)) {
                throw new UndefinedParameterException($matches[1], $exception->getCode(), $exception);
            }
        }
        $params['VK_MAC'] = $this->sign($params);

        return $params;
    }

    /**
     * Create message signature.
     *
     * @param array $params
     *
     * @return string
     *
     * @throws ClientException
     */
    private function sign(array $params): string
    {
        try {
            openssl_sign(Helpers::pack($params), $signature, $this->privateKey, \OPENSSL_ALGO_SHA1);
        } catch (Throwable $exception) {
            throw new ClientException("Error trying to sign message: " . $exception->getMessage(), (int) $exception->getCode());
        }
        return base64_encode($signature);
    }

    /**
     * Make a request to the Guzzle API endpoint and redirect the browser to the payment page.
     *
     * @param array $params Array returned by initiate().
     * @param bool  $return Set to TRUE to return the redirect location instead of redirecting automatically.
     *
     * @return string|null
     *
     * @throws ClientException
     * @throws RequestException
     */
    public function request(array $params, bool $return = false): ?string
    {
        try {
            $client = $this->getGuzzle();
            $response = $client->post($this->getUrl(), [
                'allow_redirects' => false,
                'form_params' => $params,
            ]);
        } catch (GuzzleException $e) {
            throw new RequestException($e->getMessage(), $e->getCode(), $e);
        }

        $url = $response->getHeader('Location')[0] ?? null;
        if (!$url) {
            throw new ClientException("Guzzle API didn't return an URL to the payment page.");
        }

        if ($return) {
            return $url;
        }

        try {
            header("Location: $url");
        } catch (Throwable $exception) {
            if (preg_match('/headers already sent/', $exception->getMessage())) {
                throw new ClientException('Gifddo API request needs to be performed before HTTP request headers are sent.');
            }
        }
        return null;
    }

    /**
     * Get Guzzle client.
     *
     * @return Guzzle
     */
    public function getGuzzle(): Guzzle
    {
        if (!isset($this->guzzle)) {
            $this->guzzle = new Guzzle();
        }
        return $this->guzzle;
    }

    /**
     * Set Guzzle client.
     *
     * @param Guzzle $guzzle
     */
    public function setGuzzle(Guzzle $guzzle): void
    {
        $this->guzzle = $guzzle;
    }

    /**
     * Verifies signature against response parameters.
     *
     * @param array  $params
     * @param string $signature
     *
     * @return bool
     */
    public function verify(array $params, string $signature): bool
    {
        $params = ($params['VK_SERVICE'] === static::SUCCESSFUL_RESPONSE_CODE) ? [
            'VK_SERVICE' => $params['VK_SERVICE'],
            'VK_VERSION' => $params['VK_VERSION'],
            'VK_SND_ID' => $params['VK_SND_ID'],
            'VK_REC_ID' => $params['VK_REC_ID'],
            'VK_STAMP' => $params['VK_STAMP'],
            'VK_T_NO' => $params['VK_T_NO'],
            'VK_AMOUNT' => $params['VK_AMOUNT'],
            'VK_CURR' => $params['VK_CURR'],
            'VK_REC_ACC' => $params['VK_REC_ACC'],
            'VK_REC_NAME' => $params['VK_REC_NAME'],
            'VK_SND_ACC' => $params['VK_SND_ACC'],
            'VK_SND_NAME' => $params['VK_SND_NAME'],
            'VK_REF' => $params['VK_REF'],
            'VK_MSG' => $params['VK_MSG'],
            'VK_T_DATETIME' => $params['VK_T_DATETIME'],
        ] : [
            'VK_SERVICE' => $params['VK_SERVICE'],
            'VK_VERSION' => $params['VK_VERSION'],
            'VK_SND_ID' => $params['VK_SND_ID'],
            'VK_REC_ID' => $params['VK_REC_ID'],
            'VK_STAMP' => $params['VK_STAMP'],
            'VK_REF' => $params['VK_REF'],
            'VK_MSG' => $params['VK_MSG'],
        ];
        $pack = Helpers::pack($params);
        return openssl_verify($pack, base64_decode($signature), $this->getPublicKey(), \OPENSSL_ALGO_SHA1) === 1;
    }

    /**
     * Get Gifddo public key.
     *
     * @return string
     */
    public function getPublicKey(): string
    {
        if (!isset($this->publicKey)) {
            $mode = $this->getMode();
            $this->publicKey = (string) file_get_contents(__DIR__ . "/../keys/gifddo-$mode.pem");
        }
        return $this->publicKey;
    }

    /**
     * Set Gifddo public key (used for testing).
     *
     * @param string $publicKey
     */
    public function setPublicKey(string $publicKey): void
    {
        $this->publicKey = $publicKey;
    }

    /**
     * Get live/test mode string.
     *
     * @return string
     */
    private function getMode()
    {
        return $this->isTest ? 'test' : 'live';
    }

    /**
     * Get Gifddo API URL.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->isTest ? self::TEST_URL : self::LIVE_URL;
    }
}
