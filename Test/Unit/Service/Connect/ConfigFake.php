<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Connect;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

/**
 * core_config_data in memory, keyed "<scope>/<scope id>/<path>". A store inherits from its website (stores 1 and 2
 * are in website 1, store 3 in website 2), a website from the default scope.
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
            $chain = [$scope . '/' . (int) $code];
            if (in_array($scope, ['store', 'stores'], true)) {
                $chain[] = 'websites/' . ((int) $code === 3 ? 2 : 1);
            }
            $chain[] = 'default/0';
            foreach ($chain as $scopeKey) {
                if (array_key_exists($scopeKey . '/' . $path, $this->config)) {
                    return $this->config[$scopeKey . '/' . $path];
                }
            }

            return null;
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
