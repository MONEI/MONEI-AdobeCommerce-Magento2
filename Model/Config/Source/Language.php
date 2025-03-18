<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Monei type of payment source model.
 */
class Language implements OptionSourceInterface
{
    public const LANGUAGES = [
        'en' => 'English',
        'es' => 'Spanish',
        'ca' => 'Catalan',
        'pt' => 'Portuguese',
        'de' => 'German',
        'it' => 'Italian',
        'fr' => 'French',
        'nl' => 'Dutch',
        'et' => 'Estonian',
        'fi' => 'Finnish',
        'lv' => 'Latvian',
        'no' => 'Norwegian',
        'pl' => 'Polish',
        'ru' => 'Russian',
    ];

    /**
     * Get options for language dropdown.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::LANGUAGES as $key => $value) {
            $options[] = ['label' => __($value), 'value' => $key];
        }

        return $options;
    }
}
