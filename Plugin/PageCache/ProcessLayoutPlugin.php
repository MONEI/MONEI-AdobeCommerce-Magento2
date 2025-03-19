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
            return $proceed($observer);
        }

        // For MONEI payment pages, we should properly mark the page as non-cacheable
        // using Magento's built-in mechanisms
        $event = $observer->getEvent();
        $response = $event->getData('response');
        $layout = $event->getData('layout');

        if ($layout) {
            // This is the correct way to disable FPC for specific pages
            $layout->getUpdate()->addHandle('page_cache_disable');
        }

        if ($response) {
            // Set proper cache control headers as per Magento standards
            $response->setPublicHeaders(0);
            $response->setHeader('X-Magento-Cache-Control', 'no-cache, no-store, must-revalidate', true);
            $response->setHeader('X-Magento-Cache-Debug', 'MISS', true);
        }

        // Continue with normal processing after setting no-cache directives
        return $proceed($observer);
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
