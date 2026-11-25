<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Connect;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Url;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Monei\MoneiPayment\Service\Connect\CallbackUrl;
use PHPUnit\Framework\TestCase;

/**
 * The config scope of the admin page's website and store parameters.
 *
 * Every test names the bug it catches.
 */
class CallbackUrlTest extends TestCase
{
    /**
     * Bug: a page URL with a website or store code (Magento resolves it) turns into the default scope, so a connect
     * or a disconnect changes every store; or an unknown scope, the admin scope, or a store of another website is
     * written to.
     *
     * @dataProvider parameters
     */
    public function testTheScopeComesFromTheStoreManagerByCodeOrId($website, $store, ?array $expected): void
    {
        $websites = ['retail' => 1, '1' => 1, 'outlet' => 2, '0' => 0];
        $stores = ['retail_en' => [3, 1], '3' => [3, 1], 'outlet_en' => [4, 2], '0' => [0, 0]];
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsite')->willReturnCallback(function ($code) use ($websites) {
            if (!isset($websites[(string) $code])) {
                throw new NoSuchEntityException();
            }
            $model = $this->createMock(Website::class);
            $model->method('getId')->willReturn($websites[(string) $code]);
            return $model;
        });
        $storeManager->method('getStore')->willReturnCallback(function ($code) use ($stores) {
            if (!isset($stores[(string) $code])) {
                throw new NoSuchEntityException();
            }
            $model = $this->createMock(Store::class);
            $model->method('getId')->willReturn($stores[(string) $code][0]);
            $model->method('getWebsiteId')->willReturn($stores[(string) $code][1]);
            return $model;
        });

        $scope = (new CallbackUrl($this->createMock(Url::class), $storeManager))->scope($website, $store);

        $this->assertSame($expected, $scope);
    }

    public function parameters(): array
    {
        return [
            'default' => [null, null, ['default', 0]],
            'website code' => ['retail', '', ['websites', 1]],
            'website id' => ['1', null, ['websites', 1]],
            'store code' => [null, 'retail_en', ['stores', 3]],
            'store of its website' => ['retail', '3', ['stores', 3]],
            'store of another website' => ['retail', 'outlet_en', null],
            'unknown website' => ['nope', null, null],
            'unknown store' => [null, 'nope', null],
            'admin website' => ['0', null, null],
            'admin store' => [null, '0', null],
            'array' => [['1'], null, null],
        ];
    }
}
