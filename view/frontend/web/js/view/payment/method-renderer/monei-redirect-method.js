/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define([
  'Magento_Checkout/js/view/payment/default',
  'jquery',
  'Monei_MoneiPayment/js/action/set-payment-method',
  'Magento_Checkout/js/model/payment/additional-validators'
], function (Component, $, setPaymentMethodAction, additionalValidators) {
  'use strict';

  return Component.extend({
    defaults: {
      template: 'Monei_MoneiPayment/payment/monei-redirect'
    },

    /** Redirect to monei when the type of connection is "redirect" */
    continueToMonei: function () {
      if (additionalValidators.validate()) {
        setPaymentMethodAction(this.messageContainer);
        return false;
      }
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
    getIcon: function() {
      if (window.checkoutConfig.payment[this.getCode()] &&
          window.checkoutConfig.payment[this.getCode()].icon) {
        var config = window.checkoutConfig.payment[this.getCode()];
        var iconDimensions = config.iconDimensions || {};
        return {
          url: config.icon,
          width: iconDimensions.width || 40,
          height: iconDimensions.height || 30
        };
      }
      return null;
    }
  });
});
