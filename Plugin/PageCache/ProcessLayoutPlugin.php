<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin\PageCache;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\PageCache\Observer\ProcessLayoutRenderElement;

/**
 * Plugin to properly handle MONEI payment pages in the full page cache
 */
class ProcessLayoutPlugin
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
     * Around execute to prevent caching sensitive payment pages
     *
     * @param ProcessLayoutRenderElement $subject
     * @param callable $proceed
     * @param \Magento\Framework\Event\Observer $observer
     * @return ProcessLayoutPlugin|mixed
     */
    public function aroundExecute(
        ProcessLayoutRenderElement $subject,
        callable $proceed,
        \Magento\Framework\Event\Observer $observer
    ) {
        // Process normally for non-payment pages
        if (!$this->isMoneiPaymentPage()) {
            $result = $proceed($observer);
            // Ensure we always return a value even if $proceed returns null
            return $result ?? $this;
        }

        // For MONEI payment pages, add no-cache headers to the response
        $event = $observer->getEvent();
        $response = $event->getData('response');

        if ($response) {
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
            $response->setHeader('Pragma', 'no-cache', true);
            $response->setHeader('X-Magento-Cache-Debug', 'MISS', true);
        }

        // Return without further processing to prevent caching
        return $this;
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
