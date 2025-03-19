/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */
define(['Magento_Ui/js/model/messageList', 'jquery'], function (globalMessageList, $) {
  'use strict';

  var mixin = {
    accountId: '',
    apiKey: '',
    isEnabled: false,

    initialize: function () {
      this._super();

      this.showErrorMessages();

      return this;
    },

    showErrorMessages: function () {
      this.accountId = window.checkoutConfig.moneiAccountId;
      this.apiKey = window.checkoutConfig.moneiApiKey;
      this.isEnabled = window.checkoutConfig.moneiPaymentIsEnabled;
      if (this.isEnabled && (!this.accountId || !this.apiKey)) {
        globalMessageList.addErrorMessage({
          message: $.mage.__(
            'MONEI payment methods are not available. Please, check your MONEI configuration.'
          )
        });
      }
    }
  };

  return function (target) {
    return target.extend(mixin);
  };
});
