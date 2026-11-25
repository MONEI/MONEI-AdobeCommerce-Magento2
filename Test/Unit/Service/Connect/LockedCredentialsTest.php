<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Connect;

use Magento\Config\Model\Config\Reader\Source\Deployed\SettingChecker;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Monei\MoneiPayment\Service\Connect\LockedCredentials;
use PHPUnit\Framework\TestCase;

/**
 * Credentials locked by env.php, config.php or a CONFIG__ environment variable.
 *
 * Every test names the bug it catches.
 */
class LockedCredentialsTest extends TestCase
{
    /**
     * Bug: a lock is looked up with the scope ID where the deployment configuration names the scope by code, so a
     * locked website or store view looks writable; or one locked field of a mode is missed.
     *
     * @dataProvider scopes
     */
    public function testAModeWithALockedFieldAtItsScopeCodeIsLocked(string $scope, int $scopeId, ?string $code): void
    {
        $website = $this->createMock(Website::class);
        $website->method('getCode')->willReturn('retail');
        $store = $this->createMock(Store::class);
        $store->method('getCode')->willReturn('retail_en');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsite')->with(2)->willReturn($website);
        $storeManager->method('getStore')->with(3)->willReturn($store);
        $checker = $this->createMock(SettingChecker::class);
        $checker->method('isReadOnly')->willReturnCallback(function ($path, $checkedScope, $checkedCode) use ($scope, $code) {
            return $path === 'payment/monei/production_signing_secret' && $checkedScope === $scope && $checkedCode === $code;
        });

        $this->assertSame(['live'], (new LockedCredentials($checker, $storeManager))->modes($scope, $scopeId));
    }

    public function scopes(): array
    {
        return [
            'default' => ['default', 0, null],
            'website' => ['websites', 2, 'retail'],
            'store view' => ['stores', 3, 'retail_en'],
        ];
    }
}
