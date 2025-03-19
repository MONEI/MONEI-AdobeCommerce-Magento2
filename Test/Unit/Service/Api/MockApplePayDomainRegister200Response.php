<?php

/**
 * Mock implementation of ApplePayDomainRegister200Response for testing purposes
 *
 * @category  Monei
 * @package   Monei\MoneiPayment\Test\Unit\Service\Api
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Monei\Model\ApplePayDomainRegister200Response;

/**
 * Mock implementation of ApplePayDomainRegister200Response for testing
 *
 * @category  Monei
 * @package   Monei\MoneiPayment\Test\Unit\Service\Api
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://monei.com/
 */
class MockApplePayDomainRegister200Response extends ApplePayDomainRegister200Response
{
    /**
     * The domain name for the response
     *
     * @var string
     */
    private string $_domainName;

    /**
     * The status value for the response
     *
     * @var string
     */
    private string $_status;

    /**
     * Constructor
     *
     * @param string $domainName The domain name
     * @param string $status     The status value
     */
    public function __construct(string $domainName, string $status)
    {
        parent::__construct(['success' => true]);
        $this->_domainName = $domainName;
        $this->_status = $status;
    }

    /**
     * Get domain name
     *
     * @return string
     */
    public function getDomainName(): string
    {
        return $this->_domainName;
    }

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->_status;
    }
}
