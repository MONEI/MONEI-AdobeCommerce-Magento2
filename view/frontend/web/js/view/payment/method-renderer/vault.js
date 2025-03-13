/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

define([
  'ko',
  'jquery',
  'Magento_Checkout/js/model/quote',
  'Magento_Checkout/js/checkout-data',
  'Magento_Vault/js/view/payment/method-renderer/vault',
  'Magento_Checkout/js/model/payment/additional-validators',
  'Magento_Checkout/js/model/full-screen-loader',
  'Magento_Checkout/js/model/url-builder',
  'Magento_Checkout/js/action/redirect-on-success',
  'Magento_Ui/js/model/messageList',
  'mage/storage',
  'mage/url',
  'moneijs',
  'Monei_MoneiPayment/js/utils/error-handler',
  'Monei_MoneiPayment/js/utils/payment-handler'
], function (
  ko,
  $,
  quote,
  checkoutData,
  VaultComponent,
  additionalValidators,
  fullScreenLoader,
  urlBuilder,
  redirectOnSuccessAction,
  globalMessageList,
  storage,
  url,
  monei,
  errorHandler,
  paymentHandler
) {
  'use strict';

  return VaultComponent.extend({
    defaults: {
      template: 'Monei_MoneiPayment/payment/monei-card-vault',
      active: false,
      isMoneiVault: true,
      paymentMethodTokenizationId: '',
      paymentCardType: '',
      paymentCardNumberLast4: '',
      paymentCardExpMonth: '',
      paymentCardExpYear: '',
      redirectUrl: '',
      cancelOrderUrl: '',
      completeUrl: '',
      failOrderStatus: []
    },
    methodCardCode: '',
    isPlaceOrderActionAllowed: ko.observable(true),

    initialize: function () {
      this._super();

      this.redirectUrl = window.checkoutConfig.vault[this.getCode()].redirectUrl;
      this.cancelOrderUrl = window.checkoutConfig.vault[this.getCode()].cancelOrderUrl;
      this.completeUrl =
        window.checkoutConfig.vault[this.getCode()].completeUrl || 'monei/payment/complete';
      this.failOrderStatus = window.checkoutConfig.vault[this.getCode()].failOrderStatus;
      this.methodCardCode = window.checkoutConfig.vault[this.getCode()].methodCardCode;

      if (
        !checkoutData.getSelectedPaymentMethod() ||
        this.getId() === checkoutData.getSelectedPaymentMethod()
      ) {
        this.selectPaymentMethod();
      }

      return this;
    },

    /**
     * @returns {Object}
     */
    getData: function () {
      var data = this._super();

      data['method'] = this.methodCardCode;

      return data;
    },

    /**
     * @returns {String}
     */
    getToken: function () {
      return this.publicHash;
    },

    /**
     * @returns {String}
     */
    getId: function () {
      return this.index;
    },

    /**
     * @returns {String}
     */
    getCode: function () {
      return this.code;
    },

    /**
     * Get last 4 digits of card
     * @returns {String}
     */
    getMaskedCard: function () {
      return this.details.last4;
    },

    /**
     * Get expiration date
     * @returns {String}
     */
    getExpirationDate: function () {
      return this.details.expiration_date;
    },

    /**
     * Get card type
     * @returns {String}
     */
    getCardType: function () {
      return this.details.brand;
    },

    /**
     * @param {String} type
     * @returns {Boolean}
     */
    getIcons: function (type) {
      var cardBrandType = window.checkoutConfig.vault[this.code].card_icons[type] ?? '';

      return window.checkoutConfig.payment.ccform.icons.hasOwnProperty(cardBrandType)
        ? window.checkoutConfig.payment.ccform.icons[cardBrandType]
        : false;
    },

    placeOrder: function (data, event) {
      /** Generate the payment token in monei  */
      var self = this;

      if (event) {
        event.preventDefault();
      }

      this.isPlaceOrderActionAllowed(false);

      if (this.validate() && additionalValidators.validate()) {
        self.createOrderInMagento();

        return false;
      }

      this.isPlaceOrderActionAllowed(true);

      return false;
    },

    // Create pending order in Magento
    createOrderInMagento: function () {
      var self = this,
        serviceUrl = urlBuilder.createUrl('/checkout/createmoneipaymentvault', {}),
        payload = {
          cartId: quote.getQuoteId(),
          publicHash: this.getToken()
        };

      storage
        .post(serviceUrl, JSON.stringify(payload))
        .done(function (response) {
          response = response.shift();
          self.getPlaceOrderDeferredObject().done(function () {
            self.afterPlaceOrder(response.id, response.paymentToken);
          });
        })
        .fail(function (response) {
          var error = JSON.parse(response.responseText);
          errorHandler.handleApiError(error);
          fullScreenLoader.stopLoader();
          self.isPlaceOrderActionAllowed(true);
        });

      return true;
    },

    afterPlaceOrder: function (paymentId, token) {
      this.moneiTokenHandler(paymentId, token);
    },

    /** Confirm the payment in monei */
    moneiTokenHandler: function (paymentId, token) {
      // Use the common payment handler utility
      return paymentHandler.moneiTokenHandler(this, paymentId, token);
    },

    /**
     * Redirect to success page.
     */
    redirectToCancelOrder: function () {
      window.location.replace(url.build(this.cancelOrderUrl));
    }
  });
});
