/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define(
    [
        'ko',
        'jquery',
        'Monei_MoneiPayment/js/view/payment/method-renderer/monei-insite',
        'moneijs',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (ko, $, Component, monei, redirectOnSuccessAction, globalMessageList, fullScreenLoader) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Monei_MoneiPayment/payment/monei-google-apple-insite',
            },
            redirectAfterPlaceOrder: true,
            googleAppleContainer: null,
            idGoogleAppleContainer: 'monei_google_apple_insite_container',
            failOrderStatus: '',
            applePaySupported: '',
            accountId: '',
            paymentMethodTitle: ko.observable(''),

            initialize: function () {
                this._super();

                this.initMoneiPaymentVariables();
                this.checkPaymentMethods();

                return this;
            },

            initMoneiPaymentVariables: function(){
                this.cancelOrderUrl = window.checkoutConfig.payment[this.getCode()].cancelOrderUrl;
                this.failOrderUrl = window.checkoutConfig.payment[this.getCode()].failOrderUrl;
                this.failOrderStatus = window.checkoutConfig.payment[this.getCode()].failOrderStatus;
                this.accountId = window.checkoutConfig.payment[this.getCode()].accountId;
                this.applePaySupported = !!window.ApplePaySession?.canMakePayments();
                this.paymentMethodTitle(window.checkoutConfig.payment[this.getCode()].googleTitle);
            },

            checkPaymentMethods: function(){
                monei.api.getPaymentMethods({accountId: this.accountId}).then(result => this.setTitle(result))
            },

            setTitle: function(result){
                if(result.paymentMethods.includes('applePay') && this.applePaySupported){
                    this.paymentMethodTitle(window.checkoutConfig.payment[this.getCode()].appleTitle);
                }
            },

            createMoneiPayment: function(){
                if ($.trim($('#' + this.idGoogleAppleContainer).html()) === '') {
                    fullScreenLoader.startLoader();
                    this.isPlaceOrderActionAllowed(false);
                    this.renderGoogleApple();
                    fullScreenLoader.stopLoader();
                }
            },

            /** Render the google apple */
            renderGoogleApple: function(){
                var self = this;
                this.container = document.getElementById(this.idGoogleAppleContainer);
                var style = {
                    base: {
                        'height': '45px'
                    }
                };

                // Create an instance of the Google and Apple using payment_id.
                this.googleAppleContainer = monei.PaymentRequest({
                    accountId: this.accountId,
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
                            self.createOrderInMagento(result.token);
                        }
                    },
                    onError(error) {
                        globalMessageList.addErrorMessage({
                            message: error.message
                        });
                        self.isPlaceOrderActionAllowed(false);
                    }
                });

                this.googleAppleContainer.render(this.container);
            },

            /** Confirm the payment in monei */
            moneiTokenHandler: function (paymentId, token) {
                var self = this;
                fullScreenLoader.startLoader();
                return monei.confirmPayment({
                    paymentId: paymentId,
                    paymentToken: token,
                })
                    .then(function (result) {
                        if (self.failOrderStatus.includes(result.status)) {
                            globalMessageList.addErrorMessage({
                                message: result.statusMessage
                            });
                            self.redirectToFailOrder(result.status);
                        }else if (result.nextAction && result.nextAction.type === 'COMPLETE') {
                            setTimeout(function(){
                                window.location.assign(result.nextAction.redirectUrl);
                            } ,4000);
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
        });
    });
