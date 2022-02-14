<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Monei\MoneiPayment\Model\Config\Source\CancelReason;

/**
 * ViewModel which provides refund reasons for creditmemo.
 */
class RefundReasons implements ArgumentInterface
{
    /**
     * @var CancelReason
     */
    private $reasonsSource;

    /**
     * @param CancelReason $reasonsSource
     */
    public function __construct(
        CancelReason $reasonsSource
    ) {
        $this->reasonsSource = $reasonsSource;
    }

    /**
     * Get refund reasons array
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->reasonsSource->toOptionArray();
    }
}