/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

define([
    'ko',
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Vault/js/view/payment/method-renderer/vault',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Checkout/js/action/redirect-on-success',
    'Magento_Ui/js/model/messageList',
    'mage/storage',
    'mage/url',
    'moneijs',
], function (
    ko,
    $,
    quote,
    VaultComponent,
    additionalValidators,
    fullScreenLoader,
    urlBuilder,
    redirectOnSuccessAction,
    globalMessageList,
    storage,
    url,
    monei
) {
    'use strict';

    return VaultComponent.extend({
        defaults: {
            template: 'Monei_MoneiPayment/payment/monei-card-vault'
        },

        initialize: function () {
            this._super();

            this.initMoneiPaymentVariables();

            return this;
        },

        initMoneiPaymentVariables: function(){
            this.cancelOrderUrl = window.checkoutConfig.vault[this.getCode()].cancelOrderUrl;
            this.failOrderUrl = window.checkoutConfig.vault[this.getCode()].failOrderUrl;
            this.failOrderStatus = window.checkoutConfig.vault[this.getCode()].failOrderStatus;
        },

        /**
         * @returns {Object}
         */
        getData: function () {
            var data = this._super();

            data['method'] = 'monei';

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

            return window.checkoutConfig.payment.ccform.icons.hasOwnProperty(cardBrandType) ?
                window.checkoutConfig.payment.ccform.icons[cardBrandType]
                : false;
        },

        placeOrder: function (data, event) {
            /** Generate the payment token in monei  */
            var self = this;

            if (event) {
                event.preventDefault();
            }

            //Disable the button of place order
            this.isPlaceOrderActionAllowed(false);

            if (this.validate() && additionalValidators.validate()) {

                self.createOrderInMagento();

                return false;
            }

            this.isPlaceOrderActionAllowed(true);

            return false;
        },

        // Create pending order in Magento
        createOrderInMagento: function(){
            var self = this,
                serviceUrl = urlBuilder.createUrl("/checkout/createmoneipaymentvault", {}),
                payload = {
                    cartId: quote.getQuoteId(),
                    publicHash: this.getToken(),
                };

            storage.post(
                serviceUrl,
                JSON.stringify(payload)
            ).done(function (response) {
                response = response.shift();
                quote.setMoneiPaymentId(response.id);
                self.getPlaceOrderDeferredObject().done(
                    function () {
                        self.afterPlaceOrder(response.paymentToken);
                    }
                );
            }).fail(function (response) {
                    var error = JSON.parse(response.responseText);
                    globalMessageList.addErrorMessage({
                        message: error.message
                    });
                    fullScreenLoader.stopLoader();
                }
            );

            return true;
        },

        afterPlaceOrder: function (token) {
            this.moneiTokenHandler(token);
        },

        moneiTokenHandler: function (token) {
            var self = this;
            fullScreenLoader.startLoader();
            return monei.confirmPayment({
                paymentId: quote.getMoneiPaymentId(),
                paymentToken: token
            }).then(function (result) {
                if (self.failOrderStatus.includes(result.status)) {
                    globalMessageList.addErrorMessage({
                        message: result.statusMessage
                    });
                    self.redirectToFailOrder(result.status);
                }else if (result.nextAction && result.nextAction.type === 'COMPLETE') {
                    setTimeout(function(){
                        window.location.assign(result.nextAction.redirectUrl);
                    } , 4000);
                }else if(self.redirectAfterPlaceOrder) {
                    redirectOnSuccessAction.execute();
                }
            }).catch(function (error) {
                globalMessageList.addErrorMessage({
                    message: error.message
                });
                self.redirectToCancelOrder();
            });
        },

        /**
         * Redirect to success page.
         */
        redirectToCancelOrder: function () {
            window.location.replace(url.build(this.cancelOrderUrl));
        },

        /**
         * Redirect to fail page.
         */
        redirectToFailOrder: function (status) {
            window.location.replace(url.build(this.failOrderUrl+'?status='+status));
        },
    });
});
