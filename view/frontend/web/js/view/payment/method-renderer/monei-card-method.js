/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
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
    cardGroup: null,
    cardGroupParts: [],
    idCardHolderInput: 'monei-insite-cardholder-name',
    idCardInput: 'monei-insite-card-input',
    idCardNumber: 'monei-insite-card-number',
    idCardExpiry: 'monei-insite-card-expiry',
    idCardCvc: 'monei-insite-card-cvc',
    idCardError: 'monei-insite-card-error',
    isEnabledTokenization: false,
    failOrderStatus: '',
    language: 'en',
    accountId: '',
    jsonStyle: JSON.parse('{"base":{"height":"30px","padding":"0","font-size":"14px"},"input":{"height":"30px"}}'),
    isSplitCardInput: ko.observable(true),
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
      this.isEnabledTokenization = window.checkoutConfig.payment[this.getCode()].isEnabledTokenization;
      this.failOrderStatus = window.checkoutConfig.payment[this.getCode()].failOrderStatus;
      this.accountId = window.checkoutConfig.payment[this.getCode()].accountId;
      this.jsonStyle = window.checkoutConfig.payment[this.getCode()].jsonStyle ?? this.jsonStyle;
      // Split fields are the default. An older store that never saved the setting
      // therefore gets the split layout on upgrade, which is intentional.
      this.isSplitCardInput(window.checkoutConfig.payment[this.getCode()].cardInputLayout !== 'single');
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

    /**
     * Element the SDK mounts into, which differs by layout. The single layout has
     * one container; the split layout has three, of which the number field is the
     * first to be rendered.
     *
     * @returns {String}
     */
    getMountElementId: function () {
      return this.isSplitCardInput() ? this.idCardNumber : this.idCardInput;
    },

    /** Create a payment in monei when the type of connection is "insite" */
    createMoneiPayment: function () {
      if ($.trim($('#' + this.getMountElementId()).html()) === '') {
        fullScreenLoader.startLoader();
        this.isPlaceOrderActionAllowed(false);

        this.renderCard();

        fullScreenLoader.stopLoader();
      } else {
        // Ensure button is enabled if card input already exists
        this.isPlaceOrderActionAllowed(true);
      }
    },

    /**
     * Amount in minor units, in the store's base currency.
     *
     * Must match Service/Checkout/AbstractCheckoutService.php, which creates the
     * payment from getBaseGrandTotal()/getBaseCurrencyCode(). Using the quote
     * currency here would show the shopper one figure and charge another on a
     * multi-currency store.
     *
     * @returns {Number}
     */
    getAmount: function () {
      return Math.round(quote.totals()['base_grand_total'] * 100);
    },

    /**
     * @returns {String}
     */
    getCurrencyCode: function () {
      return quote.totals()['base_currency_code'];
    },

    /** Render the card form in whichever layout the store is configured for */
    renderCard: function () {
      if (this.isSplitCardInput()) {
        this.renderCardGroup();
      } else {
        this.renderCardInput();
      }
    },

    /**
     * Split layout: one CardGroup carrying the payment details, plus three
     * presentation-only parts.
     *
     * amount and currency belong on the group only - a part throws when given
     * either. The parts must also be destroyed before the group they belong to.
     */
    renderCardGroup: function () {
      var self = this;

      // A checkout re-render replaces the mount containers. Without this the
      // previous group and its parts stay alive with their listeners attached.
      this.destroyCardGroup();

      this.errorText = document.getElementById(this.idCardError);
      this.container = document.getElementById(this.idCardNumber);

      var theme = this.themeTypography();

      var group = monei.CardGroup({
        accountId: this.accountId,
        amount: this.getAmount(),
        currency: this.getCurrencyCode(),
        language: this.language,
        style: theme.style,
        onChange: function (event) {
          if (event.isTouched && event.error) {
            self.errorText.innerText = event.error;
          } else {
            self.errorText.innerText = '';
          }

          if (event.brand) {
            self.updateCardBrandDisplay(event.brand);
          }
        },
        onEnter: function () {
          if (self.isPlaceOrderActionAllowed()) {
            self.placeOrder();
          }
        },
        onLoad: function () {
          self.isPlaceOrderActionAllowed(true);
        }
      });

      // Record the group and each part before rendering it. If a later part
      // throws, everything already mounted is tracked and torn down; otherwise
      // the number container stays populated, the re-render guard skips, and
      // submit() runs against a null group.
      this.cardGroup = group;
      this.cardGroupParts = [];

      try {
        [
          [monei.CardNumber, this.idCardNumber],
          [monei.CardExpiry, this.idCardExpiry],
          [monei.CardCvc, this.idCardCvc]
        ].forEach(function (part) {
          var instance = part[0]({group: group});
          self.cardGroupParts.push(instance);
          instance.render(document.getElementById(part[1]));
        });
      } catch (e) {
        this.destroyCardGroup();
        this.isPlaceOrderActionAllowed(false);
        console.error('Card fields failed to render', e);
        return;
      }

      this.inlineFonts(theme.fonts).then(function (fonts) {
        if (fonts.length && self.cardGroup === group) {
          group.updateProps({fonts: fonts});
        }
      });
    },

    /**
     * Style and fonts that make each part frame look like the cardholder-name
     * input beside it. Typography defaults come from the theme and the admin's
     * json_style keeps precedence over them. The frame's box does not: the
     * mount element is the visible field, so the frame inside it takes the
     * mount's inner height and the theme's text inset regardless of json_style.
     *
     * The frame is a document on another origin and cannot see the theme's
     * stylesheet, so the face the theme declares for its font is collected
     * from the page's @font-face rules and handed over as an explicit source.
     * Without it the frame falls back to whatever the family list offers.
     *
     * @returns {{style: Object, fonts: Array}}
     */
    themeTypography: function () {
      var reference = document.getElementById(this.idCardHolderInput);
      var style = $.extend(true, {}, this.jsonStyle);

      if (!reference) {
        return {style: style, fonts: []};
      }

      var computed = window.getComputedStyle(reference);
      var typography = {
        fontFamily: computed.fontFamily,
        fontSize: computed.fontSize,
        color: computed.color
      };
      // From computed style, not layout: the method block is still hidden
      // when the parts render, and a hidden element measures 0px.
      var box = {padding: computed.padding};
      var height = parseFloat(computed.height);
      if (computed.boxSizing === 'border-box') {
        height -=
          parseFloat(computed.borderTopWidth) +
          parseFloat(computed.borderBottomWidth) +
          parseFloat(computed.paddingTop) +
          parseFloat(computed.paddingBottom);
      }
      if (height > 0) {
        box.height = height + 'px';
      }
      style.base = $.extend({}, typography, style.base, box);
      style.input = $.extend({}, typography, style.input);

      return {
        style: style,
        fonts: this.fontFacesFor(computed.fontFamily, computed.fontWeight)
      };
    },

    /**
     * Replace each face's URL with the file itself as a data: URL. The frame
     * would fetch the URL cross-origin, and a store does not normally send
     * CORS headers on its fonts. The page already holds the file, so this is
     * a cache hit. A face that cannot be read is dropped.
     *
     * @param {Array<{family: string, src: string, weight: string}>} faces
     * @returns {Promise<Array>}
     */
    inlineFonts: function (faces) {
      return Promise.all(
        faces.map(function (face) {
          return fetch(face.src)
            .then(function (response) {
              if (!response.ok) {
                throw new Error(response.status);
              }
              return response.blob();
            })
            .then(function (blob) {
              return new Promise(function (resolve, reject) {
                var reader = new FileReader();
                reader.onload = function () {
                  resolve($.extend({}, face, {src: reader.result}));
                };
                reader.onerror = reject;
                reader.readAsDataURL(blob);
              });
            })
            .catch(function () {
              return null;
            });
        })
      ).then(function (results) {
        return results.filter(Boolean);
      });
    },

    /**
     * The @font-face sources declared on this page for the first family in a
     * font-family list, at one weight. Cross-origin stylesheets cannot be read
     * and are skipped.
     *
     * @param {string} fontFamily - a CSS font-family list
     * @param {string} fontWeight - computed weight, e.g. "400"
     * @returns {Array<{family: string, src: string, weight: string}>}
     */
    fontFacesFor: function (fontFamily, fontWeight) {
      var family = fontFamily
        .split(',')[0]
        .trim()
        .replace(/^["']|["']$/g, '');
      var faces = [];

      Array.prototype.forEach.call(document.styleSheets, function (sheet) {
        var rules;

        try {
          rules = sheet.cssRules;
        } catch (e) {
          return;
        }

        Array.prototype.forEach.call(rules || [], function (rule) {
          if (!(rule instanceof CSSFontFaceRule)) {
            return;
          }
          var ruleFamily = rule.style
            .getPropertyValue('font-family')
            .trim()
            .replace(/^["']|["']$/g, '');
          var ruleWeight = rule.style.getPropertyValue('font-weight').trim() || '400';
          var ruleStyle = rule.style.getPropertyValue('font-style').trim() || 'normal';
          if (ruleFamily !== family || ruleWeight !== fontWeight || ruleStyle !== 'normal') {
            return;
          }
          var match = /url\((["']?)([^"')]+)\1\)\s*format\((["']?)woff2\3\)/.exec(rule.style.getPropertyValue('src'));
          if (!match) {
            return;
          }
          faces.push({
            family: family,
            src: new URL(match[2], sheet.href || window.location.href).href,
            weight: ruleWeight
          });
        });
      });

      return faces;
    },

    /** Tear down the split layout. The SDK requires the parts to go before the group. */
    destroyCardGroup: function () {
      this.cardGroupParts.forEach(function (part) {
        if (part && part.destroy) {
          try {
            part.destroy();
          } catch (e) {
            // Silent cleanup: a part may already be gone with its container.
          }
        }
      });
      this.cardGroupParts = [];

      if (this.cardGroup && this.cardGroup.destroy) {
        try {
          this.cardGroup.destroy();
        } catch (e) {
          // Silent cleanup.
        }
      }
      this.cardGroup = null;
    },

    /**
     * The component that holds the card details and is asked for a token,
     * whichever layout is active.
     *
     * @returns {Object|null}
     */
    getCardComponent: function () {
      return this.isSplitCardInput() ? this.cardGroup : this.cardInput;
    },

    /** Single-field layout */
    renderCardInput: function () {
      var self = this;
      this.container = document.getElementById(this.idCardInput);
      this.errorText = document.getElementById(this.idCardError);
      // Create an instance of the Card Input using payment_id.
      this.cardInput = monei.CardInput({
        // paymentId: paymentId,
        accountId: this.accountId,
        // monei.js v3 rejects accountId without amount and currency, rendering no iframe.
        // Base currency, to match the amount the server creates the payment with.
        amount: this.getAmount(),
        currency: this.getCurrencyCode(),
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
        // submit() rather than monei.createToken(component): a CardGroup rejects
        // createToken with "Index is not registered", and submit() works for both
        // layouts.
        this.getCardComponent()
          .submit()
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
            console.error('Card Error', error);
            fullScreenLoader.stopLoader();
            //Enable the button of place order
            self.isPlaceOrderActionAllowed(true);
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
        this.errorMessageCardHolderName($.mage.__('Please enter the name exactly as it appears on the card.'));
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
      if (window.checkoutConfig.payment[this.getCode()] && window.checkoutConfig.payment[this.getCode()].icons) {
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
      if (window.checkoutConfig.payment[this.getCode()] && window.checkoutConfig.payment[this.getCode()].icon) {
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
