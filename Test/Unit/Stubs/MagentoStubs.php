<?php

namespace Magento\Framework\Exception;

if (!class_exists('\Magento\Framework\Exception\LocalizedException')) {
    class LocalizedException extends \Exception {}
}

if (!class_exists('\Magento\Framework\Exception\NoSuchEntityException')) {
    class NoSuchEntityException extends LocalizedException {}
}

namespace Magento\Framework\Phrase;

if (!class_exists('\Magento\Framework\Phrase')) {
    class Phrase {
        private $text;
        
        public function __construct($text, array $arguments = []) {
            $this->text = $text;
        }
        
        public function __toString() {
            return (string)$this->text;
        }
    }
}

namespace Magento\Store\Api\Data;

if (!interface_exists('\Magento\Store\Api\Data\StoreInterface')) {
    interface StoreInterface {
        public function getId();
    }
}

namespace Magento\Store\Model;

if (!interface_exists('\Magento\Store\Model\StoreManagerInterface')) {
    interface StoreManagerInterface {
        public function getStore();
    }
}

namespace Magento\Sales\Api\Data;

if (!interface_exists('\Magento\Sales\Api\Data\OrderInterface')) {
    interface OrderInterface {
        public function getEntityId();
        public function getIncrementId();
        public function getPayment();
        public function getState();
        public function getStoreId();
        public function getId();
    }
}

if (!interface_exists('\Magento\Sales\Api\Data\OrderPaymentInterface')) {
    interface OrderPaymentInterface {
        public function setAdditionalInformation($key, $value);
        public function getAdditionalInformation($key = null);
    }
}

namespace Magento\Sales\Model;

if (!class_exists('\Magento\Sales\Model\Order')) {
    class Order implements \Magento\Sales\Api\Data\OrderInterface {
        const STATE_NEW = 'new';
        const STATE_PROCESSING = 'processing';
        const STATE_CANCELED = 'canceled';
        
        private $id = null;
        private $incrementId = null;
        private $state = self::STATE_NEW;
        private $status = null;
        private $storeId = 1;
        private $data = [];
        
        public function getId() {
            return $this->id;
        }
        
        public function getEntityId() {
            return $this->id;
        }
        
        public function getIncrementId() {
            return $this->incrementId;
        }
        
        public function getPayment() {
            // Return a stub payment object
            $payment = new class implements \Magento\Sales\Api\Data\OrderPaymentInterface {
                private $additionalInfo = [];
                
                public function setAdditionalInformation($key, $value) {
                    $this->additionalInfo[$key] = $value;
                    return $this;
                }
                
                public function getAdditionalInformation($key = null) {
                    if ($key === null) {
                        return $this->additionalInfo;
                    }
                    return $this->additionalInfo[$key] ?? null;
                }
            };
            
            return $payment;
        }
        
        public function getState() {
            return $this->state;
        }
        
        public function getStoreId() {
            return $this->storeId;
        }
        
        public function setState($state) {
            $this->state = $state;
            return $this;
        }
        
        public function setStatus($status) {
            $this->status = $status;
            return $this;
        }
        
        public function setCanSendNewEmailFlag($flag) {
            $this->data['can_send_new_email_flag'] = $flag;
            return $this;
        }
        
        public function getEmailSent() {
            return $this->data['email_sent'] ?? false;
        }
        
        public function addCommentToStatusHistory($comment) {
            if (!isset($this->data['status_histories'])) {
                $this->data['status_histories'] = [];
            }
            
            $history = new class {
                private $comment;
                private $isCustomerNotified = false;
                
                public function setComment($comment) {
                    $this->comment = $comment;
                    return $this;
                }
                
                public function getComment() {
                    return $this->comment;
                }
                
                public function setIsCustomerNotified($notified) {
                    $this->isCustomerNotified = $notified;
                    return $this;
                }
                
                public function getIsCustomerNotified() {
                    return $this->isCustomerNotified;
                }
            };
            
            $history->setComment($comment);
            $this->data['status_histories'][] = $history;
            
            return $this;
        }
        
        public function getData($key = null) {
            if ($key === null) {
                return $this->data;
            }
            return $this->data[$key] ?? null;
        }
        
        public function setData($key, $value = null) {
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    $this->data[$k] = $v;
                }
            } else {
                $this->data[$key] = $value;
            }
            return $this;
        }
        
        public function getStatusHistories() {
            return $this->data['status_histories'] ?? [];
        }
        
        public function loadByIncrementId($incrementId) {
            $this->incrementId = $incrementId;
            // Simulate loading an order - in a real test you would set the ID in your test method
            // For now, we'll set a dummy ID that can be overridden in tests
            $this->id = 1;
            return $this;
        }
    }
}

namespace Magento\Sales\Api;

if (!interface_exists('\Magento\Sales\Api\OrderRepositoryInterface')) {
    interface OrderRepositoryInterface {
        public function save(\Magento\Sales\Api\Data\OrderInterface $order);
        public function get($id);
        public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);
    }
}

namespace Magento\Framework\Api;

if (!interface_exists('\Magento\Framework\Api\SearchCriteriaInterface')) {
    interface SearchCriteriaInterface {}
}

if (!class_exists('\Magento\Framework\Api\SearchCriteriaBuilder')) {
    class SearchCriteriaBuilder {
        public function addFilter($field, $value, $conditionType = null) {}
        public function create() {}
    }
}

namespace Magento\Framework\Url;

if (!class_exists('\Magento\Framework\Url')) {
    class Url {
        public function getUrl($route = '', $params = []) {}
    }
}

namespace Magento\Sales\Model;

if (!class_exists('\Magento\Sales\Model\OrderFactory')) {
    class OrderFactory {
        /**
         * @return Order
         */
        public function create() {
            return new Order();
        }
    }
}

namespace Magento\Sales\Model\Order\Email\Sender;

if (!class_exists('\Magento\Sales\Model\Order\Email\Sender\OrderSender')) {
    class OrderSender {
        public function send($order) {
            return true;
        }
    }
}

// For any used classes that might be missing
spl_autoload_register(function ($class) {
    // Handle factory classes that might be referenced but not needed for the tests
    if (preg_match('/Factory$/', $class)) {
        eval('namespace ' . substr($class, 0, strrpos($class, '\\')) . '; class ' . basename($class) . ' { public function create() {} }');
        return true;
    }
    return false;
});