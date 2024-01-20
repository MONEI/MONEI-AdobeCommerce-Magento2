/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Monei_MoneiPayment/js/action/set-payment-method',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/storage',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'moneijs',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Ui/js/model/messageList',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (ko, $, Component, setPaymentMethodAction, additionalValidators, storage, customer, quote, urlBuilder, monei, redirectOnSuccessAction, globalMessageList, url, fullScreenLoader) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Monei_MoneiPayment/payment/monei-bizum-insite',
            },
            redirectAfterPlaceOrder: true,
            bizumContainer: null,
            idBizumContainer: 'monei_bizum_insite_container',
            cancelOrderUrl: '',
            failOrderUrl:'',
            failOrderStatus: '',

            initialize: function () {
                this._super();

                this.initMoneiPaymentVariables();

                return this;
            },

            initMoneiPaymentVariables: function(){
                this.cancelOrderUrl = window.checkoutConfig.payment[this.getCode()].cancelOrderUrl;
                this.failOrderUrl = window.checkoutConfig.payment[this.getCode()].failOrderUrl;
                this.failOrderStatus = window.checkoutConfig.payment[this.getCode()].failOrderStatus;
            },

            createMoneiPayment: function(){
                if ($.trim($('#' + this.idBizumContainer).html()) === '') {
                    fullScreenLoader.startLoader();
                    this.isPlaceOrderActionAllowed(false);
                    var self = this,
                        serviceUrl,
                        payload,
                        email;

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
                            quote.setMoneiBizumPaymentId(response.id);
                            self.renderBizum(response.id);
                            self.isPlaceOrderActionAllowed(true);
                            fullScreenLoader.stopLoader();
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
                }
            },

            /** Render the bizum */
            renderBizum: function(paymentId){
                var self = this;
                this.container = document.getElementById(this.idBizumContainer);
                var style = {
                    base: {
                        'height': '45px'
                    }
                };

                // Create an instance of the Bizum using payment_id.
                this.bizumContainer = monei.Bizum({
                    paymentId: paymentId,
                    style: style,
                    onLoad: function () {
                        self.isPlaceOrderActionAllowed(true);
                    },
                    onSubmit(result) {
                        if (result.error) {
                            globalMessageList.addErrorMessage({
                                message: result.error
                            });
                            self.isPlaceOrderActionAllowed(true);
                        } else {
                            // Confirm payment using the token.
                            quote.setMoneiBizumToken(result.token);
                            self.createOrderInMagento();
                        }
                    },
                    onError(error) {
                        globalMessageList.addErrorMessage({
                            message: error.message
                        });
                        self.isPlaceOrderActionAllowed(false);
                    }
                });

                this.bizumContainer.render(this.container);
            },

            // Create pending order in Magento
            createOrderInMagento: function(){
                var self = this;
                this.getPlaceOrderDeferredObject()
                    .done(
                        function () {
                            self.afterPlaceOrder();
                        }
                    );

                return true;
            },

            afterPlaceOrder: function () {
                this.moneiTokenHandler(quote.getMoneiBizumToken());
            },

            /** Confirm the payment in monei */
            moneiTokenHandler: function (token) {
                var self = this;
                fullScreenLoader.startLoader();
                return monei.confirmPayment({
                    paymentId: quote.getMoneiBizumPaymentId(),
                    paymentToken: token,
                })
                    .then(function (result) {
                        if (self.failOrderStatus.includes(result.status)) {
                            globalMessageList.addErrorMessage({
                                message: result.statusMessage
                            });
                            self.redirectToFailOrder(result.status);
                        }else if (result.nextAction && result.nextAction.mustRedirect) {
                            setTimeout(function(){
                                window.location.assign(result.nextAction.redirectUrl);
                            } , 4000);
                        }else if(self.redirectAfterPlaceOrder) {
                            redirectOnSuccessAction.execute();
                        }
                    })
                    .catch(function (error) {
                        globalMessageList.addErrorMessage({
                            message: error.message
                        });
                        self.redirectToCancelOrder();
                    });
            },

            /**
             * @return {Boolean}
             */
            selectPaymentMethod: function () {
                var selectPaymentMethod = this._super();
                if(this.item.method === 'monei_bizum'){
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
        });
    });
