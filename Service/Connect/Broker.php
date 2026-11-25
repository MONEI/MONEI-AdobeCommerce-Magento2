<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Connect;

use Monei\Connect;
use Monei\ConnectException;

/**
 * The MONEI connect broker, through the SDK.
 *
 * MONEI_CONNECT_BASE_URL (a PHP constant, else an environment variable) points it at a dev broker.
 */
class Broker
{
    public const CLIENT = 'magento';

    /**
     * Start a connect
     *
     * @param string $redirectUri The output of Connect::canonicalRedirectUri()
     * @return array{url: string, state: string, verifier: string}
     */
    public function start(string $redirectUri): array
    {
        return Connect::start(self::CLIENT, $redirectUri, $this->baseUrl());
    }

    /**
     * Exchange the callback code for the keys
     *
     * @param string $code
     * @param string $verifier
     * @param string $redirectUri
     * @return array<string, array<string, string>> Keys live, test and errors, each optional
     * @throws ConnectException With the OAuth error code of the broker
     */
    public function exchange(string $code, string $verifier, string $redirectUri): array
    {
        return Connect::exchange($code, $verifier, self::CLIENT, $redirectUri, $this->baseUrl());
    }

    /**
     * Get the broker base URL
     *
     * @return string
     */
    public function baseUrl(): string
    {
        $url = defined('MONEI_CONNECT_BASE_URL') ? constant('MONEI_CONNECT_BASE_URL') : getenv('MONEI_CONNECT_BASE_URL');

        return is_string($url) && $url !== '' ? $url : Connect::BASE_URL;
    }
}
