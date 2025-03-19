<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin\PageCache;

use Magento\Framework\App\PageCache\Identifier;
use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * Plugin to ensure unique cache identifiers for MONEI payment pages
 */
class IdentifierPlugin
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
     * After getValue to modify cache identifier for MONEI payment pages
     *
     * @param Identifier $subject
     * @param string $result
     * @return string
     */
    public function afterGetValue(Identifier $subject, string $result): string
    {
        if ($this->isMoneiPaymentPage()) {
            // Add a specific suffix for MONEI payment pages
            // Instead of using time() and uniqid which create excessive cache entries
            $orderId = $this->request->getParam('order_id') ?: '';
            $sessionId = $this->request->getParam('session_id') ?: '';

            // Create a more stable but still unique cache identifier
            return $result . '_monei_payment_' . $orderId . '_' . $sessionId;
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

        return ($moduleName === 'monei' && $controllerName === 'payment');
    }
}
