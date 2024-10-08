/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define(
    [
        'jquery',
        'Monei_MoneiPayment/js/view/payment/method-renderer/monei-insite',
        'Magento_Checkout/js/model/payment/additional-validators',
        'moneijs',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function ($, Component, additionalValidators, monei, redirectOnSuccessAction, globalMessageList, fullScreenLoader) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Monei_MoneiPayment/payment/monei-bizum-insite',
            },
            redirectAfterPlaceOrder: true,
            bizumContainer: null,
            idBizumContainer: 'monei_bizum_insite_container',
            failOrderStatus: '',
            language: 'en',
            accountId: '',
            jsonStyle: JSON.parse('{"base":{"height":"45px"}}'),

            initialize: function () {
                this._super();

                this.initMoneiPaymentVariables();

                return this;
            },

            initMoneiPaymentVariables: function(){
                this.language = window.checkoutConfig.moneiLanguage ?? this.language;
                this.failOrderStatus = window.checkoutConfig.payment[this.getCode()].failOrderStatus;
                this.accountId = window.checkoutConfig.payment[this.getCode()].accountId;
                this.jsonStyle = window.checkoutConfig.payment[this.getCode()].jsonStyle ?? this.jsonStyle;
            },

            createMoneiPayment: function(){
                if ($.trim($('#' + this.idBizumContainer).html()) === '') {
                    fullScreenLoader.startLoader();
                    this.isPlaceOrderActionAllowed(false);
                    this.renderBizum();
                    fullScreenLoader.stopLoader();
                }
            },

            /** Render the bizum */
            renderBizum: function(){
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
                    onSubmit(result) {
                        if (result.error) {
                            console.log(result.error);
                            self.isPlaceOrderActionAllowed(true);
                        } else {
                            // Confirm payment using the token.
                            self.createOrderInMagento(result.token);
                        }
                    },
                    onError(error) {
                        console.log(error);
                        self.isPlaceOrderActionAllowed(false);
                    }
                });

                this.bizumContainer.render(this.container);
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
                        }else if (result.nextAction && (result.nextAction.mustRedirect ||  result.nextAction.type === 'COMPLETE')) {
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
            }
        });
    });
