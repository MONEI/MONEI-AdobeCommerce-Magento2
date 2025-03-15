/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define([
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
  'Magento_Vault/js/view/payment/vault-enabler',
  'Monei_MoneiPayment/js/utils/error-handler',
  'Monei_MoneiPayment/js/utils/payment-handler',
  'mage/url',
  'Monei_MoneiPayment/js/utils/card-type-detector'
], function (
  ko,
  $,
  Component,
  additionalValidators,
  storage,
  customer,
  quote,
  urlBuilder,
  monei,
  redirectOnSuccessAction,
  globalMessageList,
  fullScreenLoader,
  VaultEnabler,
  errorHandler,
  paymentHandler,
  cardTypeDetector
) {
  'use strict';

  return Component.extend({
    defaults: {
      template: 'Monei_MoneiPayment/payment/monei-card-insite',
      paymentMethodTitleBase: '',
      availableCards: ko.observableArray([]),
      cardImages: ko.observableArray([]),
      cardData: {
        brand: '',
        last4: '',
        isValid: false
      }
    },
    redirectAfterPlaceOrder: true,
    cardInput: null,
    idCardHolderInput: 'monei-insite-cardholder-name',
    idCardInput: 'monei-insite-card-input',
    idCardError: 'monei-insite-card-error',
    isEnabledTokenization: false,
    failOrderStatus: '',
    language: 'en',
    accountId: '',
    jsonStyle: JSON.parse(
      '{"base":{"height":"30px","padding":"0","font-size":"14px"},"input":{"height":"30px"}}'
    ),
    cardHolderNameValid: ko.observable(true),
    errorMessageCardHolderName: ko.observable(''),
    checkedVault: ko.observable(false),

    initialize: function () {
      this._super();

      this.initMoneiPaymentVariables();
      this.initMoneiObservable();

      this.vaultEnabler = new VaultEnabler();
      this.vaultEnabler.setPaymentCode(this.getVaultCode());

      this.initCardBrands();
      // Save the base payment title before any modifications for brand display
      this.paymentMethodTitleBase = this.getTitle();

      return this;
    },

    initMoneiPaymentVariables: function () {
      this.language = window.checkoutConfig.moneiLanguage ?? this.language;
      this.isEnabledTokenization =
        window.checkoutConfig.payment[this.getCode()].isEnabledTokenization;
      this.failOrderStatus = window.checkoutConfig.payment[this.getCode()].failOrderStatus;
      this.accountId = window.checkoutConfig.payment[this.getCode()].accountId;
      this.jsonStyle = window.checkoutConfig.payment[this.getCode()].jsonStyle ?? this.jsonStyle;
    },

    initMoneiObservable: function () {
      var self = this,
        serviceUrl = urlBuilder.createUrl('/checkout/savemoneitokenization', {});

      this.checkedVault.subscribe(function (val) {
        var payload = {
          cartId: quote.getQuoteId(),
          isVaultChecked: val ? 1 : 0
        };

        storage
          .post(serviceUrl, JSON.stringify(payload))
          .done(function () {
            quote.setMoneiVaultChecked(val);
          })
          .fail(function (response) {
            self.checkedVault(quote.getMoneiVaultChecked() ?? false);
            self.handleApiError(JSON.parse(response.responseText));
          });
      });
      return this;
    },

    /**
     * @returns {Object}
     */
    getData: function () {
      var data = {
        method: this.getCode(),
        additional_data: {}
      };

      this.vaultEnabler.visitAdditionalData(data);

      return data;
    },

    /** Create a payment in monei when the type of connection is "insite" */
    createMoneiPayment: function () {
      if ($.trim($('#' + this.idCardInput).html()) === '') {
        fullScreenLoader.startLoader();
        this.isPlaceOrderActionAllowed(false);

        this.renderCard();

        fullScreenLoader.stopLoader();
      } else {
        // Ensure button is enabled if card input already exists
        this.isPlaceOrderActionAllowed(true);
      }
    },

    /** Render the card input */
    renderCard: function () {
      var self = this;
      this.container = document.getElementById(this.idCardInput);
      this.errorText = document.getElementById(this.idCardError);
      // Create an instance of the Card Input using payment_id.
      this.cardInput = monei.CardInput({
        // paymentId: paymentId,
        accountId: this.accountId,
        language: this.language,
        style: this.jsonStyle,
        onChange: function (event) {
          // Handle real-time validation errors.
          if (event.isTouched && event.error) {
            self.container.classList.add('is-invalid');
            self.errorText.innerText = event.error;
          } else {
            self.container.classList.remove('is-invalid');
            self.errorText.innerText = '';
          }

          // Handle card brand detection
          if (event.brand) {
            self.updateCardBrandDisplay(event.brand);
          }
        },
        onFocus: function () {
          self.container.classList.add('is-focused');
        },
        onBlur: function () {
          self.container.classList.remove('is-focused');
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
        fullScreenLoader.startLoader();
        monei
          .createToken(this.cardInput)
          .then(function (result) {
            fullScreenLoader.stopLoader();
            if (result.error) {
              // Inform the user if there was an error.
              self.container.classList.add('is-invalid');
              self.errorText.innerText = result.error;
              self.isPlaceOrderActionAllowed(true);
            } else {
              self.createOrderInMagento(result.token);
            }
          })
          .catch(function (error) {
            deferred.reject({
              message: $t('An error occurred with the payment. Please try again.')
            });
          });

        return false;
      }

      this.isPlaceOrderActionAllowed(true);

      return false;
    },

    validate: function () {
      var cardHolderName = $('#' + this.idCardHolderInput).val();
      quote.setMoneiCardholderName(cardHolderName);

      return this.validateCardHolderName(cardHolderName);
    },

    validateCardHolderName: function (cardHolderName) {
      if (cardHolderName === '' || cardHolderName === undefined) {
        this.errorMessageCardHolderName($.mage.__('Please enter the name on the card.'));
        this.cardHolderNameValid(false);

        return false;
      }
      var regExp = /^[A-Za-zÀ-ú- ]{5,50}$/;
      if (!regExp.test(cardHolderName)) {
        // Mostrar un mensaje de error si no cumple
        this.errorMessageCardHolderName(
          $.mage.__('Please enter the name exactly as it appears on the card.')
        );
        this.cardHolderNameValid(false);
        return false;
      }

      this.cardHolderNameValid(true);

      return true;
    },

    /** Confirm the payment in monei */
    moneiTokenHandler: function (paymentId, token) {
      // Use the common payment handler utility with card-specific options
      var options = {
        generatePaymentToken: !!quote.getMoneiVaultChecked(),
        paymentMethod: {
          card: {
            cardholderName: quote.getMoneiCardholderName()
          }
        }
      };

      return paymentHandler.moneiTokenHandler(this, paymentId, token, options);
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
    },

    /**
     * Initialize available card brands from the config
     */
    initCardBrands: function () {
      if (
        window.checkoutConfig.payment[this.getCode()] &&
        window.checkoutConfig.payment[this.getCode()].icons
      ) {
        var icons = window.checkoutConfig.payment[this.getCode()].icons;
        var brands = [];
        var cardImages = [];

        // Add available card brands
        for (var brand in icons) {
          if (icons.hasOwnProperty(brand) && brand !== 'default') {
            brands.push(brand);
            cardImages.push({
              url: icons[brand].url,
              width: icons[brand].width,
              height: icons[brand].height,
              title: icons[brand].title
            });
          }
        }

        this.availableCards(brands);
        this.cardImages(cardImages);
      }
    },

    /**
     * Get combined card brand icons as HTML
     * @returns {string}
     */
    getCardBrandsHtml: function () {
      var images = this.cardImages();
      var html = '';

      if (images.length > 0) {
        for (var i = 0; i < images.length; i++) {
          html +=
            '<img src="' +
            images[i].url +
            '" ' +
            'alt="' +
            images[i].title +
            '" ' +
            'class="card-brand-icon" ' +
            'style="height: 24px; margin-right: 5px;" />';
        }
      }

      return html;
    },

    /**
     * Get card icon container for display in the payment method
     * @returns {Object|null}
     */
    getCardIconContainer: function () {
      if (this.availableCards().length > 0) {
        return {
          html: this.getCardBrandsHtml()
        };
      }

      // Return null instead of falling back to the standard icon
      return null;
    },

    /**
     * Get payment icon configuration
     * @returns {Object|null}
     */
    getIcon: function () {
      if (
        window.checkoutConfig.payment[this.getCode()] &&
        window.checkoutConfig.payment[this.getCode()].icon
      ) {
        var iconDimensions = window.checkoutConfig.payment[this.getCode()].iconDimensions || {};
        return {
          url: window.checkoutConfig.payment[this.getCode()].icon,
          width: iconDimensions.width || 40,
          height: iconDimensions.height || 30
        };
      }
      return null;
    },

    /**
     * Update the display based on detected card brand
     *
     * @param {string} brand The detected card brand
     */
    updateCardBrandDisplay: function (brand) {
      if (!brand) {
        return;
      }

      // Convert the brand to lowercase for consistent matching
      brand = brand.toLowerCase();

      // Find the detected brand in our available cards
      var availableCards = this.availableCards();
      if (availableCards.indexOf(brand) >= 0) {
        // Get the icon for this brand
        var icons = window.checkoutConfig.payment[this.getCode()].icons;
        if (icons && icons[brand]) {
          // Update display to highlight this card brand
          var cardBrandIcons = document.querySelectorAll('.monei-card-brands img.card-brand-icon');
          if (cardBrandIcons && cardBrandIcons.length > 0) {
            for (var i = 0; i < cardBrandIcons.length; i++) {
              // Reset all icons to default opacity
              cardBrandIcons[i].style.opacity = '0.5';

              // Highlight the detected brand
              if (cardBrandIcons[i].alt.toLowerCase().indexOf(brand) >= 0) {
                cardBrandIcons[i].style.opacity = '1';
              }
            }
          }
        }
      }
    }
  });
});
