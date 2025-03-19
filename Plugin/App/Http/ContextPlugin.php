<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin\App\Http;

use Magento\Framework\App\Http\Context;
use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * Plugin to handle HTTP context for Monei payment pages
 * This ensures that Monei payment pages are properly handled by Varnish and other caching systems
 */
class ContextPlugin
{
    /**
     * @var HttpRequest
     */
    private HttpRequest $request;

    /**
     * @param HttpRequest $request
     */
    public function __construct(
        HttpRequest $request
    ) {
        $this->request = $request;
    }

    /**
     * Around get vary string to add monei payment specific context
     *
     * @param Context $subject
     * @param callable $proceed
     * @return string
     */
    public function aroundGetVaryString(Context $subject, callable $proceed): string
    {
        // Ensure the result is always a string
        $result = (string) $proceed();

        // Add a vary condition for MONEI payment pages which should never be cached
        if ($this->isMoneiPaymentPage()) {
            $result .= '|monei_payment=' . time();  // Adding a timestamp ensures the page is never cached
        }

        return $result;
    }

    /**
     * Check if the current request is for a MONEI payment page
     *
     * @return bool
     */
    private function isMoneiPaymentPage(): bool
    {
        $moduleName = $this->request->getModuleName();
        $controllerName = $this->request->getControllerName();

        // Return true if the request is for any Monei payment controller
        return ($moduleName === 'monei' && $controllerName === 'payment');
    }
}
