<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Authorization\PolicyInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;

/**
 * Backend model for validating JSON in configuration fields.
 */
class JsonValidator extends Value
{
    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var PolicyInterface
     */
    private $policyInterface;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ManagerInterface $messageManager
     * @param PolicyInterface $policyInterface
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ManagerInterface $messageManager,
        PolicyInterface $policyInterface,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->messageManager = $messageManager;
        $this->policyInterface = $policyInterface;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Check if the current user is allowed to save this configuration
     *
     * @return bool
     */
    protected function isAllowed(): bool
    {
        return $this->policyInterface->isAllowed('admin', 'Monei_MoneiPayment::config');
    }

    /**
     * Validate JSON value before saving
     *
     * @return JsonValidator
     * @throws LocalizedException
     */
    public function beforeSave(): JsonValidator
    {
        // Check permissions
        if (!$this->isAllowed()) {
            throw new LocalizedException(
                __('You do not have permission to save this configuration.')
            );
        }

        $value = $this->getValue();

        // If value is empty, we allow it (defaults will be used)
        if (empty($value)) {
            return parent::beforeSave();
        }

        // Try to decode the JSON to validate it
        $decoded = json_decode($value, true);
        $jsonError = json_last_error();

        // Check if decoding was successful
        if ($jsonError !== JSON_ERROR_NONE) {
            $errorMsg = json_last_error_msg();

            // Get field name from path or use generic name
            $pathParts = explode('/', (string) $this->getPath());
            $fieldName = end($pathParts) ?: 'JSON style';

            // Create a user-friendly error message with examples
            $errorMessage = __(
                'Invalid JSON format in %1 field: %2. Please ensure that you provide valid JSON. Examples:
                - For a simple style: {"height":"45px"}
                - For nested properties: {"base":{"height":"30px","padding":"0"},"input":{"height":"30px"}}
                - Make sure all property names and string values are enclosed in double quotes
                - No trailing commas allowed after the last property',
                $fieldName,
                $errorMsg
            );

            // Add message to the admin session for display
            $this->messageManager->addErrorMessage($errorMessage);

            // Throw exception to prevent saving
            throw new LocalizedException($errorMessage);
        }

        return parent::beforeSave();
    }
}
