<?php

declare(strict_types=1);

namespace Gifddo;

/**
 * Class Client
 */
class Client
{
    const LIVE_URL = 'https://gifddo.com/api/giftlink';
    const TEST_URL = 'http://gifddo.staging.elevate.ee/api/giftlink';
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
    private $publicKey;

    /**
     * @var string
     */
    private $privateKey;

    /**
     * @var bool
     */
    private $isTest;

    /**
     * Client constructor.
     *
     * @param string $merchantId
     * @param string $publicKey
     * @param string $privateKey
     * @param bool   $isTest
     */
    public function __construct(string $merchantId, string $publicKey, string $privateKey, bool $isTest = false)
    {
        $this->merchantId = $merchantId;
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        $this->isTest = $isTest;
    }
}
