<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Connect;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

/**
 * core_config_data in memory, keyed "<scope>/<scope id>/<path>". No scope inherits from another: a test sets each
 * value at the scope it reads.
 */
trait ConfigFake
{
    /**
     * @var array<string, string>
     */
    protected array $config = [];

    /**
     * Get a ScopeConfigInterface that reads the fake
     *
     * @return ScopeConfigInterface
     */
    private function fakeScopeConfig(): ScopeConfigInterface
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(function ($path, $scope = 'default', $code = null) {
            return $this->config[$scope . '/' . (int) $code . '/' . $path] ?? null;
        });

        return $scopeConfig;
    }

    /**
     * Get a WriterInterface that writes the fake and fails on a delete
     *
     * @return WriterInterface
     */
    private function fakeConfigWriter(): WriterInterface
    {
        $writer = $this->createMock(WriterInterface::class);
        $writer->method('save')->willReturnCallback(function ($path, $value, $scope = 'default', $scopeId = 0) {
            $this->config[$scope . '/' . (int) $scopeId . '/' . $path] = $value;
        });
        $writer->expects($this->never())->method('delete');

        return $writer;
    }
}
