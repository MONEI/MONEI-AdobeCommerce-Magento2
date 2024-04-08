/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define([
    'Magento_Ui/js/model/messageList',
    'jquery'
], function (globalMessageList, $) {
    'use strict';

    var mixin = {

        accountId: '',
        apiKey: '',

        initialize: function () {
            this._super();

            this.showErrorMessages();

            return this;
        },


        showErrorMessages: function () {
            this.accountId = window.checkoutConfig.moneiAccountId;
            this.apiKey = window.checkoutConfig.moneiApiKey;
            if(!this.accountId || !this.apiKey) {
                globalMessageList.addErrorMessage({
                    message: $.mage.__('Monei payment method is not available. Please, check your Monei configuration.')
                });
            }
        }
    };

    return function (target) {
        return target.extend(mixin);
    };
});
