<?php

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
                'VK_DATETIME' => date('Y-m-d') . 'T' . date('H:i:sO'),
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
     */
    private function sign(array $params): string
    {
        openssl_sign(Helpers::pack($params), $signature, $this->privateKey, \OPENSSL_ALGO_SHA1);
        return base64_encode($signature);
    }

    /**
     * Make a request to the Guzzle API endpoint and redirect the browser to the payment page.
     *
     * @param array $params
     *
     * @return void
     *
     * @throws RequestException
     * @throws ClientException
     */
    public function request(array $params): void
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
        try {
            header("Location: $url");
        } catch (Throwable $exception) {
            if (preg_match('/headers already sent/', $exception->getMessage())) {
                throw new ClientException('Gifddo API request needs to be performed before HTTP request headers are sent.');
            }
        }
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
     * Get Gifddo public key.
     *
     * @return string
     */
    private function getPublicKey(): string
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
