/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
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
