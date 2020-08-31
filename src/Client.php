<?php

declare(strict_types=1);

namespace Gifddo;

use Throwable;
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
     * @return array
     *
     * @throws UndefinedParameterException|Throwable
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
        return [
            'url' => $this->getUrl(),
            'params' => $params,
        ];
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
    private function getUrl()
    {
        return $this->isTest ? self::TEST_URL : self::LIVE_URL;
    }
}
