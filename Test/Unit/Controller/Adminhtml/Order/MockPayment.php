<?php

/**
 * MONEI Payment for Magento 2
 *
 * @category  Payment
 * @package   Monei\MoneiPayment
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

namespace Monei\MoneiPayment\Test\Unit\Controller\Adminhtml\Order;

use Monei\Model\Payment as MoneiPayment;
use ArrayAccess;

/**
 * Mock Payment class for testing that implements ArrayAccess
 * to behave both as an object and array
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://monei.com/
 */
class MockPayment extends MoneiPayment implements ArrayAccess
{
    /**
     * @var array
     */
    private $_data;

    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->_data = $data;
    }

    /**
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->_data['id'] ?? null;
    }

    /**
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->_data['status'] ?? null;
    }

    /**
     * @return int|null
     */
    public function getAmount(): ?int
    {
        return $this->_data['amount'] ?? null;
    }

    /**
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        return $this->_data['currency'] ?? null;
    }

    /**
     * ArrayAccess implementation
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->_data[$offset]);
    }

    /**
     * ArrayAccess implementation
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->_data[$offset] ?? null;
    }

    /**
     * ArrayAccess implementation
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset !== null) {
            $this->_data[$offset] = $value;
        } else {
            $this->_data[] = $value;
        }
    }

    /**
     * ArrayAccess implementation
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->_data[$offset]);
    }
}
