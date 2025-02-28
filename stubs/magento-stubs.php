<?php
/**
 * Basic stubs for Magento 2
 */

/**
 * Translation function
 *
 * @param string $text
 * @param mixed ...$args
 * @return string
 */
function __($text, ...$args): string
{
    return $text;
}

namespace Magento\Framework\Component {
    class ComponentRegistrar
    {
        const MODULE = 'module';
        const THEME = 'theme';
        const LANGUAGE = 'language';
        const LIBRARY = 'library';

        /**
         * @param string $type
         * @param string $name
         * @param string $path
         * @return void
         */
        public static function register($type, $name, $path)
        {
        }
    }
}

namespace Magento\Framework\Exception {
    class LocalizedException extends \Exception
    {
    }
    class NoSuchEntityException extends LocalizedException
    {
    }
}

namespace Magento\Framework {
    interface UrlInterface
    {
    }
}

namespace Magento\Framework\Serialize {
    interface SerializerInterface
    {
        /**
         * @param mixed $data
         * @return string
         */
        public function serialize($data);

        /**
         * @param string $string
         * @return mixed
         */
        public function unserialize($string);
    }
}

namespace Magento\Store\Model {
    interface StoreManagerInterface
    {
        /**
         * @return mixed
         */
        public function getStore();
    }
}

namespace Magento\Sales\Model {
    class Order
    {
        const STATE_NEW = 'new';
        const STATE_PENDING_PAYMENT = 'pending_payment';
        const STATE_PROCESSING = 'processing';
        const STATE_COMPLETE = 'complete';
        const STATE_CLOSED = 'closed';
        const STATE_CANCELED = 'canceled';
        const STATE_HOLDED = 'holded';
    }
}

namespace Monolog {
    class Logger
    {
        const DEBUG = 100;
        const INFO = 200;
        const NOTICE = 250;
        const WARNING = 300;
        const ERROR = 400;
        const CRITICAL = 500;
        const ALERT = 550;
        const EMERGENCY = 600;

        /**
         * @param string $message
         * @param array $context
         * @return void
         */
        public function debug($message, array $context = [])
        {
        }

        /**
         * @param string $message
         * @param array $context
         * @return void
         */
        public function info($message, array $context = [])
        {
        }

        /**
         * @param string $message
         * @param array $context
         * @return void
         */
        public function error($message, array $context = [])
        {
        }

        /**
         * @param string $message
         * @param array $context
         * @return void
         */
        public function critical($message, array $context = [])
        {
        }
    }
}
