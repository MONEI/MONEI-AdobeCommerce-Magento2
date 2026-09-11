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
    express: null,
    currentAmount: 0,

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
     * Mount the express buttons once knockout has produced their container.
     *
     * The same component the shortcut surfaces use, so the shipping callbacks and
     * failure handling behave identically here.
     *
     * @param {HTMLElement} element
     */
    mountExpressButton: function (element) {
      var self = this,
        config = window.checkoutConfig.moneiExpress;

      if (!this.isAvailable()) {
        return;
      }

      this.mount(element, config.amount);

      // The config amount is a snapshot from page load. A coupon or a shipping
      // change on this page moves the total, and a sheet opened with the old
      // figure is refused by the server's amount cross-check. Remount with the
      // current total instead - the same source the other renderers read.
      quote.totals.subscribe(function (totals) {
        var amount = Math.round(Number(totals.base_grand_total) * 100);

        if (amount && amount !== self.currentAmount) {
          self.mount(element, amount);
        }
      });
    },

    /**
     * Mount the express buttons for an amount, tearing down any previous mount.
     *
     * @param {HTMLElement} element
     * @param {Number} amount
     */
    mount: function (element, amount) {
      var config = window.checkoutConfig.moneiExpress;

      if (this.express) {
        this.express.destroy();
      }

      this.currentAmount = amount;
      this.express = expressFactory(
        {
          accountId: config.accountId,
          language: config.language,
          style: config.style,
          location: 'checkout',
          amount: amount,
          currency: config.currency,
          requestShipping: config.requestShipping,
          paypal: config.paypal
        },
        element
      );
    }
  });
});
