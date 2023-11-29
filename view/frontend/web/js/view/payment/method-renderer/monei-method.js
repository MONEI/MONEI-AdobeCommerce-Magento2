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
                template: 'Monei_MoneiPayment/payment/monei',
            },
            redirectAfterPlaceOrder: true,
            cardInput: null,
            idCardInput: 'monei-insite-card-input',
            idCardError: 'monei-insite-card-error',
            cancelOrderUrl: window.checkoutConfig.payment.moneiMonei.cancelOrderUrl,
            failOrderUrl: window.checkoutConfig.payment.moneiMonei.failOrderUrl,
            failOrderStatus: window.checkoutConfig.payment.moneiMonei.failOrderStatus,

            initialize: function () {
                this._super();

                this.showCustomTemplate = ko.observable(false);

                if (window.checkoutConfig.payment.moneiMonei.typeOfConnection === 'insite') {
                    this.showCustomTemplate(true);
                }
            },

            /**
             * @return {String}
             */
            getTemplate: function () {
                return this.showCustomTemplate() ? 'Monei_MoneiPayment/payment/monei-card-insite' : this.template;
            },

            /** Redirect to monei when the type of connection is "redirect" */
            continueToMonei: function () {
                if (additionalValidators.validate()) {
                    setPaymentMethodAction(this.messageContainer);
                    return false;
                }
            },

            /** Create a payment in monei when the type of connection is "insite" */
            createMoneiPayment: function(){
                if ($.trim($('#' + this.idCardInput).html()) === '') {
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
                            quote.setMoneiPaymentId(response.id);
                            self.renderCard(response.id);
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

            /** Render the card input */
            renderCard: function(paymentId){
                var self = this;
                this.container = document.getElementById(this.idCardInput);
                this.errorText = document.getElementById(this.idCardError);
                // Create an instance of the Card Input using payment_id.
                this.cardInput = monei.CardInput({
                    paymentId: paymentId,
                    onChange: function (event) {
                        // Handle real-time validation errors.
                        if (event.isTouched && event.error) {
                            self.container.classList.add('is-invalid');
                            self.errorText.innerText = event.error;
                        } else {
                            self.container.classList.remove('is-invalid');
                            self.errorText.innerText = '';
                        }
                    },
                    onFocus: function () {
                        self.container.classList.add("is-focused");
                    },
                    onBlur: function () {
                        self.container.classList.remove("is-focused");
                    },
                    onLoad: function () {
                        self.isPlaceOrderActionAllowed(true);
                    }
                });

                // Render an instance of the Card Input into the `card_input` <div>.
                this.cardInput.render(this.container);
            },

            /** Generate the payment token in monei  */
            confirmCardMonei: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                //Disable the button of place order
                this.isPlaceOrderActionAllowed(false);

                if (additionalValidators.validate()) {

                    monei.createToken(this.cardInput).then(function (result) {
                        if (result.error) {
                            // Inform the user if there was an error.
                            self.container.classList.add('is-invalid');
                            self.errorText.innerText = result.error;
                            self.isPlaceOrderActionAllowed(true);
                        } else {
                            quote.setMoneiCardToken(result.token);
                            self.createOrderInMagento();
                        }
                    }).catch(function (error) {
                        self.container.classList.add('is-invalid');
                        self.errorText.innerText = result.error;
                        //Enable the button of place order
                        self.isPlaceOrderActionAllowed(true);
                    });

                    return false;
                }
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
                this.moneiTokenHandler(quote.getMoneiCardToken());
            },

            /** Confirm the payment in monei */
            moneiTokenHandler: function (token) {
                var self = this;
                return monei.confirmPayment({
                    paymentId: quote.getMoneiPaymentId(),
                    paymentToken: token
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
                if(this.item.method === 'monei' && this.showCustomTemplate()){
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
            }
        });
    });
