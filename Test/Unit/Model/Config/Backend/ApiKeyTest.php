<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Monei\MoneiPayment\Model\Config\Backend\ApiKey;
use PHPUnit\Framework\TestCase;

/**
 * The backend model of the API key fields.
 *
 * Every test names the bug it catches.
 */
class ApiKeyTest extends TestCase
{
    /**
     * Bug: after a manual key change, v2 callbacks still verify with the secret of the replaced key; the secret is
     * deleted (the scope then inherits a parent's secret) or cleared at another scope; or the other mode loses its
     * secret; or a save that leaves the key alone drops the secret of a connected mode.
     *
     * @dataProvider savedKeys
     */
    public function testAChangedKeyClearsTheSecretOfItsModeAtItsScope(string $storedKey, array $expectedSaves): void
    {
        $context = $this->createMock(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createMock(ManagerInterface::class));
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn($storedKey);
        $saves = [];
        $writer = $this->createMock(WriterInterface::class);
        $writer->method('save')->willReturnCallback(function (...$args) use (&$saves) {
            $saves[] = $args;
        });
        $writer->expects($this->never())->method('delete');
        $model = new ApiKey(
            $context,
            $this->createMock(Registry::class),
            $config,
            $this->createMock(TypeListInterface::class),
            $writer,
            $this->createMock(AbstractResource::class)
        );
        $model->setPath('payment/monei/test_api_key');
        $model->setScope('websites');
        $model->setScopeId(2);
        $model->setValue('mk_test_new');

        $model->afterSave();

        $this->assertSame($expectedSaves, $saves);
    }

    public function savedKeys(): array
    {
        return [
            'changed' => ['pk_test_old', [['payment/monei/test_signing_secret', '', 'websites', 2]]],
            'unchanged' => ['mk_test_new', []],
        ];
    }
}
