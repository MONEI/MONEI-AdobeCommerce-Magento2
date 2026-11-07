<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Monei\MoneiPayment\Service\Express\ExpressSessionId;

/**
 * Gives the express buttons their session id.
 *
 * A private section, not the shortcut config: product pages are full-page cached,
 * and an id in their HTML would be shared by every visitor.
 */
class ExpressSession implements SectionSourceInterface
{
    /**
     * @var ExpressSessionId
     */
    private ExpressSessionId $expressSessionId;

    /**
     * @param ExpressSessionId $expressSessionId Session id shared by token and payment
     */
    public function __construct(ExpressSessionId $expressSessionId)
    {
        $this->expressSessionId = $expressSessionId;
    }

    /**
     * @return array{sessionId: string}
     */
    public function getSectionData(): array
    {
        return ['sessionId' => $this->expressSessionId->get()];
    }
}
