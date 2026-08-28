var config = {
  map: {
    '*': {
      moneijs: 'https://js.monei.com/v3/monei.js',
      moneiLoader: 'Monei_MoneiPayment/js/monei-loader'
    }
  },
  // No shim for moneijs: v3 is a proper AMD module and sets no global. A shim's
  // `exports` is only read for scripts that do not call define(), so declaring one
  // here would be dead config that implies a `window.monei` which does not exist.
  config: {
    mixins: {
      'Magento_Checkout/js/model/quote': {
        'Monei_MoneiPayment/js/model/quote-mixin': true
      },
      'Magento_Checkout/js/view/payment': {
        'Monei_MoneiPayment/js/view/payment-mixin': true
      }
    }
  }
};
