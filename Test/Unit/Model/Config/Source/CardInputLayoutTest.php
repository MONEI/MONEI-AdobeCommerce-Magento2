<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Config\Source;

use Monei\MoneiPayment\Api\Config\MoneiCardPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\Source\CardInputLayout;
use PHPUnit\Framework\TestCase;

/**
 * Test for CardInputLayout source model.
 */
class CardInputLayoutTest extends TestCase
{
    /**
     * @var CardInputLayout
     */
    private $_source;

    protected function setUp(): void
    {
        $this->_source = new CardInputLayout();
    }

    /**
     * The admin dropdown must offer exactly the two values the config model
     * accepts, or a merchant can save a value that silently falls back.
     *
     * @return void
     */
    public function testToOptionArrayMatchesTheAcceptedValues(): void
    {
        $options = $this->_source->toOptionArray();

        $this->assertCount(2, $options);

        $values = array_column($options, 'value');
        $this->assertSame(
            [
                MoneiCardPaymentModuleConfigInterface::LAYOUT_SPLIT,
                MoneiCardPaymentModuleConfigInterface::LAYOUT_SINGLE,
            ],
            $values
        );

        foreach ($options as $option) {
            $this->assertArrayHasKey('label', $option);
            $this->assertNotEmpty((string) $option['label']);
        }
    }

    /**
     * Split is listed first so it reads as the default in the admin.
     *
     * @return void
     */
    public function testSplitIsListedFirst(): void
    {
        $options = $this->_source->toOptionArray();

        $this->assertSame(
            MoneiCardPaymentModuleConfigInterface::LAYOUT_SPLIT,
            $options[0]['value']
        );
    }
}
