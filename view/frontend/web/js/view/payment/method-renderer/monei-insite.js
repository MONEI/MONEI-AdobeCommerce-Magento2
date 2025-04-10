/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */
define([
  'ko',
  'Magento_Checkout/js/view/payment/default',
  'jquery',
  'mage/storage',
  'Magento_Customer/js/model/customer',
  'Magento_Checkout/js/model/quote',
  'Magento_Checkout/js/model/url-builder',
  'Magento_Ui/js/model/messageList',
  'mage/url',
  'Magento_Checkout/js/model/full-screen-loader',
  'Monei_MoneiPayment/js/utils/error-handler',
  'Monei_MoneiPayment/js/utils/payment-handler'
], function (
  ko,
  Component,
  $,
  storage,
  customer,
  quote,
  urlBuilder,
  globalMessageList,
  url,
  fullScreenLoader,
  errorHandler,
  paymentHandler
) {
  'use strict';

  return Component.extend({
    cancelOrderUrl: '',
    completeUrl: '',
    isPlaceOrderActionAllowed: ko.observable(true),

    initialize: function () {
      this._super();

      this.cancelOrderUrl = window.checkoutConfig.payment[this.getCode()].cancelOrderUrl;
      this.completeUrl = window.checkoutConfig.payment[this.getCode()].completeUrl || 'monei/payment/complete';

      return this;
    },

    // Create pending order in Magento
    createOrderInMagento: function (token) {
      var self = this,
        serviceUrl,
        email,
        payload;

      if (customer.isLoggedIn()) {
        serviceUrl = urlBuilder.createUrl('/checkout/createmoneipaymentinsite', {});
        email = '';
      } else {
        serviceUrl = urlBuilder.createUrl('/guest-checkout/:cartId/createmoneipaymentinsite', {
          cartId: quote.getQuoteId()
        });
        email = quote.guestEmail;
      }

      payload = {
        cartId: quote.getQuoteId(),
        email: email
      };

      storage
        .post(serviceUrl, JSON.stringify(payload))
        .done(function (response) {
          response = response.shift();
          self.getPlaceOrderDeferredObject().done(function () {
            self.afterPlaceOrder(response.id, token);
          });
        })
        .fail(function (response) {
          var error = JSON.parse(response.responseText);
          self.handleApiError(error);
          fullScreenLoader.stopLoader();
          self.isPlaceOrderActionAllowed(true);
        });

      return true;
    },

    afterPlaceOrder: function (paymentId, token) {
      this.moneiTokenHandler(paymentId, token);
    },

    /**
     * @return {Boolean}
     */
    selectPaymentMethod: function () {
      var selectPaymentMethod = this._super();
      if (this.item.method === this.getCode()) {
        this.createMoneiPayment();
      }

      return selectPaymentMethod;
    },

    getPaymentCode: function () {
      return 'method_' + this.getCode();
    },

    getTitle: function () {
      var title = this._super();
      if (window.checkoutConfig.isMoneiTestMode) {
        title = title + ' ' + $.mage.__('(Test Mode)');
      }

      return title;
    },

    /**
     * Get payment icon configuration
     * @returns {Object|null}
     */
    getIcon: function () {
      if (window.checkoutConfig.payment[this.getCode()] && window.checkoutConfig.payment[this.getCode()].icon) {
        var iconDimensions = window.checkoutConfig.payment[this.getCode()].iconDimensions || {};
        return {
          url: window.checkoutConfig.payment[this.getCode()].icon,
          width: iconDimensions.width || 40,
          height: iconDimensions.height || 30
        };
      }
      return null;
    },

    /**
     * Handle API error responses with proper message formatting
     *
     * @param {Object} error The error response object
     */
    handleApiError: function (error) {
      errorHandler.handleApiError(error);
    },

    /** Confirm the payment in monei */
    moneiTokenHandler: function (paymentId, token) {
      // Use the common payment handler utility
      return paymentHandler.moneiTokenHandler(this, paymentId, token);
    }
  });
});
