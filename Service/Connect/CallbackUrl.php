<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Connect;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Url;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\Connect;
use InvalidArgumentException;

/**
 * The callback URL of "Connect with MONEI" for a config scope, and the redirect URI that the start sends.
 *
 * The settings button and the start route both use it, so the button shows only for a URI the start sends.
 * It builds the URL with the frontend URL model: the backend one adds the secret key/… of the admin session.
 */
class CallbackUrl
{
    public const ROUTE = 'monei/payment/connect';

    /**
     * @var Url
     */
    private Url $url;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param Url $url
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(Url $url, StoreManagerInterface $storeManager)
    {
        $this->url = $url;
        $this->storeManager = $storeManager;
    }

    /**
     * Get the config scope of the website and store parameters of the admin config page
     *
     * @param mixed $website
     * @param mixed $store
     * @return array{0: string, 1: int} The scope type and id
     */
    public static function scopeOf($website, $store): array
    {
        if (is_scalar($store) && (int) $store > 0) {
            return [ScopeInterface::SCOPE_STORES, (int) $store];
        }
        if (is_scalar($website) && (int) $website > 0) {
            return [ScopeInterface::SCOPE_WEBSITES, (int) $website];
        }

        return [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0];
    }

    /**
     * Get the frontend callback URL of a scope: the one of its store view, or of the default store view
     *
     * @param string $scope
     * @param int $scopeId
     * @return string
     * @throws LocalizedException When the scope has no store view
     */
    public function build(string $scope, int $scopeId): string
    {
        if ($scope === ScopeInterface::SCOPE_STORES) {
            $store = $this->storeManager->getStore($scopeId);
        } elseif ($scope === ScopeInterface::SCOPE_WEBSITES) {
            $store = $this->storeManager->getWebsite($scopeId)->getDefaultStore();
        } else {
            $store = $this->storeManager->getDefaultStoreView();
        }
        if (!$store) {
            throw new LocalizedException(__('This scope has no store view.'));
        }

        return $this->url->getUrl(self::ROUTE, ['_scope' => (int) $store->getId(), '_nosid' => true, '_secure' => true]);
    }

    /**
     * Get the redirect URI of a scope, or null when the broker would refuse its callback URL
     *
     * @param string $scope
     * @param int $scopeId
     * @return string|null
     */
    public function redirectUri(string $scope, int $scopeId): ?string
    {
        try {
            $uri = Connect::canonicalRedirectUri($this->build($scope, $scopeId));
        } catch (InvalidArgumentException|LocalizedException $e) {
            return null;
        }

        return Connect::callbackProblem(Broker::CLIENT, $uri) === null ? $uri : null;
    }
}
