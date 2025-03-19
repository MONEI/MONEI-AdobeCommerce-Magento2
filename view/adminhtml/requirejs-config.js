/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */
var config = {
  map: {
    '*': {
      'Magento_Sales/order/view/post-wrapper': 'Monei_MoneiPayment/js/post-wrapper',
      moneiJsonValidator: 'Monei_MoneiPayment/js/json-validator',
      moneiJsonFormatter: 'Monei_MoneiPayment/js/json-formatter'
    }
  },
  shim: {
    'Monei_MoneiPayment/js/json-formatter': {
      deps: ['jquery', 'jquery/ui']
    },
    'Monei_MoneiPayment/js/json-validator': {
      deps: ['jquery', 'jquery/validate']
    }
  },
  deps: ['moneiJsonValidator', 'moneiJsonFormatter'],
  config: {
    mixins: {
      'mage/validation': {
        'Monei_MoneiPayment/js/validation-mixin': true
      }
    }
  }
};
