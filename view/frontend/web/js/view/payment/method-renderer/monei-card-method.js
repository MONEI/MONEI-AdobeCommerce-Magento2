/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define(
    [
        'ko',
        'jquery',
        'Monei_MoneiPayment/js/view/payment/method-renderer/monei-insite',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/storage',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'moneijs',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Vault/js/view/payment/vault-enabler'
    ],
    function (ko, $, Component, additionalValidators, storage, customer, quote, urlBuilder, monei, redirectOnSuccessAction, globalMessageList, fullScreenLoader, VaultEnabler) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Monei_MoneiPayment/payment/monei-card-insite',
            },
            redirectAfterPlaceOrder: true,
            cardInput: null,
            idCardHolderInput: 'monei-insite-cardholder-name',
            idCardInput: 'monei-insite-card-input',
            idCardError: 'monei-insite-card-error',
            isEnabledTokenization: false,
            failOrderStatus: '',
            accountId: '',
            cardHolderNameValid: ko.observable(true),
            checkedVault: ko.observable(false),

            initialize: function () {
                this._super();

                this.initMoneiPaymentVariables();
                this.initMoneiObservable();

                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());

                return this;
            },

            initMoneiPaymentVariables: function(){
                this.isEnabledTokenization = window.checkoutConfig.payment[this.getCode()].isEnabledTokenization;
                this.failOrderStatus = window.checkoutConfig.payment[this.getCode()].failOrderStatus;
                this.accountId = window.checkoutConfig.payment[this.getCode()].accountId;
            },

            initMoneiObservable: function(){
                var self = this,
                    serviceUrl = urlBuilder.createUrl('/checkout/savemoneitokenization',{})

                this.checkedVault.subscribe(function (val) {
                    var payload,
                        isVaultChecked = val ? 1 : 0;

                    payload = {
                        cartId: quote.getQuoteId(),
                        isVaultChecked: isVaultChecked
                    };

                    storage.post(
                        serviceUrl,
                        JSON.stringify(payload)
                    ).done(
                        function () {
                            quote.setMoneiVaultChecked(val);
                        }
                    ).fail(
                        function (response) {
                            self.checkedVault(quote.getMoneiVaultChecked() ?? false);

                            var error = JSON.parse(response.responseText);
                            globalMessageList.addErrorMessage({
                                message: error.message
                            });
                        }
                    );
                });
                return this;
            },

            /**
             * @returns {Object}
             */
            getData: function () {
                var data = {
                    'method': this.getCode(),
                    'additional_data': {}
                };

                this.vaultEnabler.visitAdditionalData(data);

                return data;
            },

            /** Create a payment in monei when the type of connection is "insite" */
            createMoneiPayment: function(){
                if ($.trim($('#' + this.idCardInput).html()) === '') {
                    fullScreenLoader.startLoader();
                    this.isPlaceOrderActionAllowed(false);

                    this.renderCard();

                    fullScreenLoader.stopLoader();
                }
            },

            /** Render the card input */
            renderCard: function(){
                var self = this;
                this.container = document.getElementById(this.idCardInput);
                this.errorText = document.getElementById(this.idCardError);
                var style = {
                    base: {
                        'height': '30px',
                        'padding': '0',
                        'font-size': '14px'
                    },
                    input: {
                        'height': '30px',
                    }
                };
                // Create an instance of the Card Input using payment_id.
                this.cardInput = monei.CardInput({
                    // paymentId: paymentId,
                    accountId: this.accountId,
                    style: style,
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

                if (this.validate() && additionalValidators.validate()) {

                    monei.createToken(this.cardInput).then(function (result) {
                        if (result.error) {
                            // Inform the user if there was an error.
                            self.container.classList.add('is-invalid');
                            self.errorText.innerText = result.error;
                            self.isPlaceOrderActionAllowed(true);
                        } else {
                            self.createOrderInMagento(result.token);
                        }
                    }).catch(function (error) {
                        console.log(error);
                        //Enable the button of place order
                        self.isPlaceOrderActionAllowed(true);
                    });

                    return false;
                }

                this.isPlaceOrderActionAllowed(true);

                return false;
            },

            validate: function (){

                var cardHolderName = $('#'+this.idCardHolderInput).val();

                if (cardHolderName === '' || cardHolderName === undefined) {
                    this.cardHolderNameValid(false);

                    return false;
                }

                this.cardHolderNameValid(true);
                quote.setMoneiCardholderName(cardHolderName);

                return true;
            },

            /** Confirm the payment in monei */
            moneiTokenHandler: function (paymentId, token) {
                var self = this;
                fullScreenLoader.startLoader();
                return monei.confirmPayment({
                    paymentId: paymentId,
                    paymentToken: token,
                    generatePaymentToken: !!quote.getMoneiVaultChecked(),
                    paymentMethod: {
                        card: {
                            cardholderName: quote.getMoneiCardholderName()
                        }
                    }
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
             * Show checkbox to save token card
             */
            isVaultEnabled: function () {
                return this.isEnabledTokenization && customer.isLoggedIn();
            },

            /**
             * Get vault payment code
             */
            getVaultCode: function () {
                return window.checkoutConfig.payment[this.getCode()].ccVaultCode;
            }
        });
    });
