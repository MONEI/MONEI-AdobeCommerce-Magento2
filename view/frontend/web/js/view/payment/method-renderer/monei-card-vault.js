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
  'Magento_Ui/js/model/messageList',
  'mage/url',
  'Monei_MoneiPayment/js/utils/error-handler'
], function (
  ko,
  $,
  quote,
  checkoutData,
  VaultComponent,
  additionalValidators,
  fullScreenLoader,
  globalMessageList,
  url,
  errorHandler
) {
  'use strict';

  return VaultComponent.extend({
    defaults: {
      template: 'Monei_MoneiPayment/payment/monei-card-vault',
      active: false,
      isMoneiVault: true,
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

    // Create pending order in Magento using form submission
    createOrderInMagento: function () {
      var self = this;

      fullScreenLoader.startLoader();

      // Submit order using the Magento place order functionality
      self
        .getPlaceOrderDeferredObject()
        .done(function () {
          // After order is placed, submit a form to redirect to payment
          self.submitPaymentForm();
        })
        .fail(function (response) {
          fullScreenLoader.stopLoader();
          self.isPlaceOrderActionAllowed(true);

          if (response && response.responseText) {
            try {
              var error = JSON.parse(response.responseText);
              errorHandler.handleApiError(error);
            } catch (e) {
              // Display generic error message if response is not JSON
              globalMessageList.addErrorMessage({
                message: $.mage.__('There was an error processing your payment. Please try again.')
              });
            }
          }
        });

      return true;
    },

    // Submit the vault redirect form
    submitPaymentForm: function () {
      // Find the form in the DOM
      var form = $('#monei-vault-redirect-form');

      if (form.length) {
        // Set the public hash in the form
        $('#monei-vault-public-hash').val(this.getToken());

        // Submit the form
        form.submit();
      } else {
        console.error('Monei vault redirect form not found in the DOM');
        globalMessageList.addErrorMessage({
          message: $.mage.__('There was an error processing your payment. Please try again.')
        });
        fullScreenLoader.stopLoader();
        this.isPlaceOrderActionAllowed(true);
      }
    },

    // Handle payment errors from the backend
    handlePaymentError: function (response) {
      // Display error message
      globalMessageList.addErrorMessage({
        message: $.mage.__('There was an error processing your payment. Please try again.')
      });

      fullScreenLoader.stopLoader();
      this.isPlaceOrderActionAllowed(true);
    },

    /**
     * Redirect to cancel page
     */
    redirectToCancelOrder: function () {
      window.location.replace(url.build(this.cancelOrderUrl));
    }
  });
});
