<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Block\System\Config\Form\Field;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\ObjectManager as AppObjectManager;
use Magento\Framework\Data\Form\Element\Text;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Monei\MoneiPayment\Block\System\Config\Form\Field\ApiKeyField;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * The renderer of the API key fields.
 *
 * Every test names the bug it catches.
 */
class ApiKeyFieldTest extends TestCase
{
    /**
     * Bug: a scoped key set in env.php or an environment variable (no backend model runs) sits in full in the admin
     * HTML; or a manual key the merchant must read back is hidden; or the browser fills a saved password in.
     *
     * @dataProvider keys
     */
    public function testAScopedKeyNeverReachesThePage(string $key, string $expectedHtml, ?string $expectedComment): void
    {
        $element = $this->getMockBuilder(Text::class)->disableOriginalConstructor()->onlyMethods(['getElementHtml'])->getMock();
        $element->setValue($key);
        $element->method('getElementHtml')->willReturnCallback(function () use ($element) {
            return '<input value="' . $element->getValue() . '" placeholder="' . $element->getPlaceholder() . '"/>';
        });
        $render = new ReflectionMethod(ApiKeyField::class, '_getElementHtml');
        $render->setAccessible(true);

        // The block constructors ask the global object manager for helpers; give it mocks, then put it back.
        $global = new ReflectionProperty(AppObjectManager::class, '_instance');
        $global->setAccessible(true);
        $before = $global->getValue();
        $helper = new ObjectManager($this);
        $helper->prepareObjectManager([
            [JsonHelper::class, $this->createMock(JsonHelper::class)],
            [DirectoryHelper::class, $this->createMock(DirectoryHelper::class)],
            [SecureHtmlRenderer::class, $this->createMock(SecureHtmlRenderer::class)],
        ]);
        try {
            $html = $render->invoke($helper->getObject(ApiKeyField::class), $element);
        } finally {
            $global->setValue(null, $before);
        }

        $this->assertSame(
            [$expectedHtml, $expectedComment],
            [$html, $element->getComment() === null ? null : (string) $element->getComment()]
        );
    }

    public function keys(): array
    {
        return [
            'scoped' => [
                'mk_test_0123456789abcdef',
                '<input autocomplete="new-password" value="" placeholder="•••• cdef"/>',
                'Set by Connect with MONEI. Leave empty to keep this key.',
            ],
            'manual' => ['pk_test_manual', '<input autocomplete="new-password" value="pk_test_manual" placeholder=""/>', null],
        ];
    }
}
