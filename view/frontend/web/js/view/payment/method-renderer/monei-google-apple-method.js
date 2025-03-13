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
  'Magento_Checkout/js/model/quote',
  'Magento_Checkout/js/action/redirect-on-success',
  'Magento_Ui/js/model/messageList',
  'Magento_Checkout/js/model/full-screen-loader',
  'Monei_MoneiPayment/js/utils/error-handler',
  'Monei_MoneiPayment/js/utils/constants',
  'Monei_MoneiPayment/js/utils/payment-handler'
], function (
  ko,
  $,
  MoneiInsiteComponent,
  additionalValidators,
  monei,
  quote,
  redirectOnSuccessAction,
  globalMessageList,
  fullScreenLoader,
  errorHandler,
  constants,
  paymentHandler
) {
  'use strict';

  return MoneiInsiteComponent.extend({
    defaults: {
      template: 'Monei_MoneiPayment/payment/monei-google-apple-insite'
    },
    redirectAfterPlaceOrder: true,
    googleAppleContainer: null,
    idGoogleAppleContainer: 'monei_google_apple_insite_container',
    failOrderStatus: '',
    applePaySupported: '',
    language: 'en',
    accountId: '',
    jsonStyle: JSON.parse('{"base":{"height":"45px"}}'),
    isEnabledGooglePay: ko.observable(false),
    isEnabledApplePay: ko.observable(false),
    paymentMethodTitle: ko.observable(''),
    baseGrandTotal: quote.totals().base_grand_total,
    isPlaceOrderActionAllowed: ko.observable(true),

    initialize: function () {
      this._super();

      this.initMoneiPaymentVariables();
      this.checkPaymentMethods();

      return this;
    },

    initMoneiPaymentVariables: function () {
      this.language = window.checkoutConfig.moneiLanguage ?? this.language;
      this.failOrderStatus = window.checkoutConfig.payment[this.getCode()].failOrderStatus;
      this.accountId = window.checkoutConfig.payment[this.getCode()].accountId;
      this.jsonStyle = window.checkoutConfig.payment[this.getCode()].jsonStyle ?? this.jsonStyle;
      this.applePaySupported = !!window.ApplePaySession?.canMakePayments();
      this.paymentMethodTitle(window.checkoutConfig.payment[this.getCode()].googleTitle);
      this.isEnabledGooglePay(window.checkoutConfig.payment[this.getCode()].isEnabledGooglePay);
      this.isEnabledApplePay(window.checkoutConfig.payment[this.getCode()].isEnabledApplePay);
    },

    checkPaymentMethods: function () {
      monei.api
        .getPaymentMethods({accountId: this.accountId})
        .then((result) => this.setTitle(result));
    },

    getPaymentCode: function () {
      if (this.applePaySupported) {
        return 'method_monei_apple_pay';
      }
      return 'method_monei_google_pay';
    },

    isMethodVisible: function () {
      if (this.applePaySupported) {
        return this.isEnabledApplePay();
      }
      return this.isEnabledGooglePay();
    },

    setTitle: function (result) {
      if (
        result.paymentMethods.includes(constants.PAYMENT_METHODS.APPLE_PAY) &&
        this.applePaySupported
      ) {
        this.paymentMethodTitle(window.checkoutConfig.payment[this.getCode()].appleTitle);
      }
      if (window.checkoutConfig.isMoneiTestMode) {
        this.paymentMethodTitle(this.paymentMethodTitle() + ' ' + $.mage.__('(Test Mode)'));
      }
    },

    createMoneiPayment: function () {
      if ($.trim($('#' + this.idGoogleAppleContainer).html()) === '') {
        fullScreenLoader.startLoader();
        this.isPlaceOrderActionAllowed(false);
        this.renderGoogleApple();
        quote.totals.subscribe(
          function (totals) {
            if (this.baseGrandTotal !== totals.base_grand_total) {
              this.baseGrandTotal = totals.base_grand_total;
              this.googleAppleContainer.close();
              this.renderGoogleApple();
            }
          }.bind(this)
        );
        fullScreenLoader.stopLoader();
      }
    },

    /** Render the google apple */
    renderGoogleApple: function () {
      var self = this;
      this.container = document.getElementById(this.idGoogleAppleContainer);

      // Create an instance of the Google and Apple using payment_id.
      this.googleAppleContainer = monei.PaymentRequest({
        accountId: this.accountId,
        amount: quote.totals().base_grand_total * 100,
        currency: quote.totals().base_currency_code,
        language: this.language,
        style: this.jsonStyle,
        onLoad: function () {
          self.isGPaymentsLoaded = true;
          self.isPlaceOrderActionAllowed(false);
        },
        onBeforeOpen: function () {
          return additionalValidators.validate();
        },
        onBeforeSubmit: function () {
          fullScreenLoader.startLoader();
        },
        onSubmit: function (result) {
          if (result.error) {
            console.log(result.error);
            self.isPlaceOrderActionAllowed(true);
          } else {
            // Confirm payment using the token.
            self.createOrderInMagento(result.token);
          }
        },
        onError: function (error) {
          console.error('Apple Pay Error', error);
          fullScreenLoader.stopLoader();
          self.isPlaceOrderActionAllowed(true);
        },
        onCancel: function (e) {
          console.error('Apple Payment Cancelled', e);
          fullScreenLoader.stopLoader();
        }
      });

      this.googleAppleContainer.render(this.container);
    },

    /** Confirm the payment in monei */
    moneiTokenHandler: function (paymentId, token) {
      // Use the common payment handler utility
      return paymentHandler.moneiTokenHandler(this, paymentId, token);
    }
  });
});
