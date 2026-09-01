/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */
define([
  'ko',
  'uiComponent',
  'Magento_Checkout/js/model/quote',
  'Monei_MoneiPayment/js/view/express/monei-express'
], function (ko, Component, quote, expressFactory) {
  'use strict';

  return Component.extend({
    defaults: {
      template: 'Monei_MoneiPayment/express/checkout'
    },

    /**
     * Whether express is configured for the checkout surface.
     *
     * @returns {Boolean}
     */
    isAvailable: function () {
      var config = window.checkoutConfig.moneiExpress;

      return !!(config && config.enabledOnCheckout && config.accountId);
    },

    /**
     * Mount the wallet button once knockout has produced its container.
     *
     * The same component the shortcut surfaces use, so the shipping callbacks and
     * failure handling behave identically here.
     *
     * @param {HTMLElement} element
     */
    mountExpressButton: function (element) {
      var config = window.checkoutConfig.moneiExpress;

      if (!this.isAvailable()) {
        return;
      }

      expressFactory(
        {
          accountId: config.accountId,
          language: config.language,
          style: config.style,
          location: 'checkout',
          // Server-computed, like every other surface. The checkout quote is the
          // same one the express service reads, so the figures agree.
          amount: config.amount,
          currency: config.currency,
          requestShipping: config.requestShipping
        },
        element
      );
    }
  });
});
