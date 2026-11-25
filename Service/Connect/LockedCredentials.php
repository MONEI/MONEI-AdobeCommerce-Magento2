<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */


declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Connect;

use Magento\Config\Model\Config\Reader\Source\Deployed\SettingChecker;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Credentials set in env.php, config.php or a CONFIG__ environment variable. They win over core_config_data, so
 * Connect and Disconnect, which write there, cannot change them.
 */
class LockedCredentials
{
    private const FIELDS = ['account_id', 'api_key', 'signing_secret'];

    /**
     * @var SettingChecker
     */
    private SettingChecker $settingChecker;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param SettingChecker $settingChecker
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(SettingChecker $settingChecker, StoreManagerInterface $storeManager)
    {
        $this->settingChecker = $settingChecker;
        $this->storeManager = $storeManager;
    }

    /**
     * Get the modes with a credential locked by the deployment configuration at a scope
     *
     * @param string $scope
     * @param int $scopeId
     * @return string[] live and/or test
     */
    public function modes(string $scope, int $scopeId): array
    {
        try {
            $code = null;
            if ($scope === ScopeInterface::SCOPE_WEBSITES) {
                $code = (string) $this->storeManager->getWebsite($scopeId)->getCode();
            } elseif ($scope === ScopeInterface::SCOPE_STORES) {
                $code = (string) $this->storeManager->getStore($scopeId)->getCode();
            } else {
                $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
            }
        } catch (LocalizedException $e) {
            return [];
        }
        $locked = [];
        foreach (['live', 'test'] as $mode) {
            foreach (self::FIELDS as $field) {
                if ($this->settingChecker->isReadOnly(ConnectService::path($mode, $field), $scope, $code)) {
                    $locked[] = $mode;
                    break;
                }
            }
        }

        return $locked;
    }
}
