<?php

/**
 * Basic stubs for Magento 2.
 *
 * @param mixed $text
 */

/**
 * Translation function.
 *
 * @param string $text
 * @param mixed ...$args
 *
 * @return string
 */
function __($text, ...$args): string
{
    return $text;
}

namespace Magento\Framework\Component {
    class ComponentRegistrar
    {
        public const MODULE = 'module';
        public const THEME = 'theme';
        public const LANGUAGE = 'language';
        public const LIBRARY = 'library';

        /**
         * @param string $type
         * @param string $name
         * @param string $path
         *
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
         *
         * @return string
         */
        public function serialize($data);

        /**
         * @param string $string
         *
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
        public const STATE_NEW = 'new';
        public const STATE_PENDING_PAYMENT = 'pending_payment';
        public const STATE_PROCESSING = 'processing';
        public const STATE_COMPLETE = 'complete';
        public const STATE_CLOSED = 'closed';
        public const STATE_CANCELED = 'canceled';
        public const STATE_HOLDED = 'holded';
    }
}

namespace Monolog {
    class Logger
    {
        public const DEBUG = 100;
        public const INFO = 200;
        public const NOTICE = 250;
        public const WARNING = 300;
        public const ERROR = 400;
        public const CRITICAL = 500;
        public const ALERT = 550;
        public const EMERGENCY = 600;

        /**
         * @param string $message
         * @param array $context
         *
         * @return void
         */
        public function debug($message, array $context = [])
        {
        }

        /**
         * @param string $message
         * @param array $context
         *
         * @return void
         */
        public function info($message, array $context = [])
        {
        }

        /**
         * @param string $message
         * @param array $context
         *
         * @return void
         */
        public function error($message, array $context = [])
        {
        }

        /**
         * @param string $message
         * @param array $context
         *
         * @return void
         */
        public function critical($message, array $context = [])
        {
        }
    }
}
