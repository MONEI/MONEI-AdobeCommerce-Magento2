/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/storage',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Ui/js/model/messageList',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (Component, storage, customer, quote, urlBuilder, globalMessageList, url, fullScreenLoader) {
        'use strict';

        return Component.extend({
            cancelOrderUrl: '',
            failOrderUrl:'',

            initialize: function () {
                this._super();

                this.cancelOrderUrl = window.checkoutConfig.payment[this.getCode()].cancelOrderUrl;
                this.failOrderUrl = window.checkoutConfig.payment[this.getCode()].failOrderUrl;

                return this;
            },

            // Create pending order in Magento
            createOrderInMagento: function(token){
                var self = this,
                    serviceUrl,
                    email,
                    payload;

                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl("/checkout/createmoneipaymentinsite", {});
                    email = '';
                } else {
                    serviceUrl = urlBuilder.createUrl("/guest-checkout/:cartId/createmoneipaymentinsite", {cartId: quote.getQuoteId()});
                    email = quote.guestEmail;
                }

                payload = {
                    cartId: quote.getQuoteId(),
                    email: email
                };

                storage.post(
                    serviceUrl,
                    JSON.stringify(payload)
                ).done(
                    function (response) {
                        response = response.shift();
                        self.getPlaceOrderDeferredObject().done(
                            function () {
                                self.afterPlaceOrder(response.id, token);
                            }
                        );
                    }
                ).fail(
                    function (response) {
                        var error = JSON.parse(response.responseText);

                        globalMessageList.addErrorMessage({
                            message: error.message
                        });
                        fullScreenLoader.stopLoader();
                    }
                );

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
                if(this.item.method === this.getCode()){
                    this.createMoneiPayment();
                }

                return selectPaymentMethod;
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

            getPaymentCode: function () {
                return 'method_'+this.getCode();
            },
        });
    });
