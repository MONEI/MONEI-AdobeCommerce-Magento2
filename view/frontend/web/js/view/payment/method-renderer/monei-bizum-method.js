/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define([
  'ko',
  'jquery',
  'Monei_MoneiPayment/js/view/payment/method-renderer/monei-insite',
  'Magento_Checkout/js/model/payment/additional-validators',
  'moneijs',
  'Magento_Checkout/js/action/redirect-on-success',
  'Magento_Ui/js/model/messageList',
  'Magento_Checkout/js/model/full-screen-loader',
  'Monei_MoneiPayment/js/utils/error-handler',
  'Monei_MoneiPayment/js/utils/payment-handler'
], function (
  ko,
  $,
  MoneiInsiteComponent,
  additionalValidators,
  monei,
  redirectOnSuccessAction,
  globalMessageList,
  fullScreenLoader,
  errorHandler,
  paymentHandler
) {
  'use strict';

  return MoneiInsiteComponent.extend({
    defaults: {
      template: 'Monei_MoneiPayment/payment/monei-bizum-insite'
    },
    redirectAfterPlaceOrder: true,
    bizumContainer: null,
    idBizumContainer: 'monei_bizum_insite_container',
    failOrderStatus: '',
    language: 'en',
    accountId: '',
    jsonStyle: JSON.parse('{"height":"45px"}'),
    isPlaceOrderActionAllowed: ko.observable(true),

    initialize: function () {
      this._super();

      this.initMoneiPaymentVariables();

      return this;
    },

    initMoneiPaymentVariables: function () {
      this.language = window.checkoutConfig.moneiLanguage ?? this.language;
      this.failOrderStatus = window.checkoutConfig.payment[this.getCode()].failOrderStatus;
      this.accountId = window.checkoutConfig.payment[this.getCode()].accountId;
      this.jsonStyle = window.checkoutConfig.payment[this.getCode()].jsonStyle ?? this.jsonStyle;
    },

    /**
     * Get payment icon configuration
     * @returns {Object|null}
     */
    getIcon: function () {
      if (window.checkoutConfig.payment[this.getCode()].icon) {
        var iconDimensions = window.checkoutConfig.payment[this.getCode()].iconDimensions || {};
        return {
          url: window.checkoutConfig.payment[this.getCode()].icon,
          width: iconDimensions.width || 70,
          height: iconDimensions.height || 45
        };
      }
      return null;
    },

    createMoneiPayment: function () {
      if ($.trim($('#' + this.idBizumContainer).html()) === '') {
        fullScreenLoader.startLoader();
        this.isPlaceOrderActionAllowed(false);
        this.renderBizum();
        fullScreenLoader.stopLoader();
      }
    },

    /** Render the bizum */
    renderBizum: function () {
      var self = this;
      this.container = document.getElementById(this.idBizumContainer);

      // Create an instance of the Bizum using payment_id.
      this.bizumContainer = monei.Bizum({
        accountId: this.accountId,
        language: this.language,
        style: this.jsonStyle,
        onLoad: function () {
          self.isPlaceOrderActionAllowed(true);
        },
        onBeforeOpen: function () {
          return additionalValidators.validate();
        },
        onSubmit: function (result) {
          if (result.error) {
            // Handle error case
            self.isPlaceOrderActionAllowed(true);
          } else {
            // Payment succeeded
            self.createOrderInMagento(result.token);
          }
        },
        onError: function (error) {
          self.isPlaceOrderActionAllowed(false);
        }
      });

      this.bizumContainer.render(this.container);
    },

    /** Confirm the payment in monei */
    moneiTokenHandler: function (paymentId, token) {
      // Use the common payment handler utility
      return paymentHandler.moneiTokenHandler(this, paymentId, token);
    }
  });
});
