/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */
define([
  'jquery',
  'moneijs',
  'mage/url',
  'Magento_Ui/js/model/messageList',
  'Magento_Customer/js/customer-data',
  'mage/translate',
  'mage/validation'
], function ($, monei, urlBuilder, globalMessageList, customerData, $t) {
  'use strict';

  var REST = {
    shippingOptions: 'rest/V1/monei-express/shipping-options',
    selectOption: 'rest/V1/monei-express/select-shipping-option',
    placeOrder: 'rest/V1/monei-express/place-order'
  };

  /**
   * POST JSON to one of the express endpoints.
   *
   * @param {String} path
   * @param {Object} payload
   * @returns {Promise<Object>}
   */
  function post(path, payload) {
    return $.ajax({
      url: urlBuilder.build(path),
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload)
    }).then(function (response) {
      // Results come back JSON-encoded: an untyped array return is serialised
      // positionally by Magento's webapi layer, which drops the keys.
      return JSON.parse(Array.isArray(response) ? response[0] : response);
    });
  }

  return function (config, element) {
    var container = element,
      location = config.location,
      components = [];

    // No amount means an empty cart. The SDK throws on a zero amount, and the
    // mini cart mounts from HTML that customer-data cached while the cart was
    // empty; it re-initialises with the real amount once the section refreshes.
    if (!config.amount || !config.currency) {
      return;
    }

    /**
     * Show a failure on the surface that started this payment.
     *
     * Ownership follows the button that opened the sheet, never Magento's notion
     * of an active payment method: an express payment can be started from the
     * mini cart while a different method is selected in checkout. A rejected
     * order must never be discarded silently - the shopper has already approved
     * it in the wallet.
     *
     * @param {String} message
     */
    function fail(message) {
      var text = message || $t('The payment could not be completed. Please try again.'),
        target = $(container).closest('.monei-express-shortcut'),
        node = target.find('.monei-express-error');

      if (!node.length) {
        node = $('<div class="monei-express-error message error"></div>').appendTo(target);
      }

      node.text(text).show();

      // Also surface it globally, so a shopper who has scrolled past the button
      // still sees why nothing happened.
      globalMessageList.addErrorMessage({message: text});
    }

    /**
     * Read a readable message out of a Magento webapi error response.
     *
     * @param {Object} xhr
     * @returns {String}
     */
    function messageFrom(xhr) {
      try {
        var body = JSON.parse(xhr.responseText),
          message = body.message || '';

        // The webapi sends a phrase and its parameters apart; %1 or %name in
        // the text is filled in here.
        $.each(body.parameters || {}, function (key, value) {
          var placeholder = typeof key === 'number' ? '%' + (key + 1) : '%' + key;

          message = message.split(placeholder).join(String(value));
        });

        return message;
      } catch (e) {
        return '';
      }
    }

    /**
     * The product page's add-to-cart form, or null on the other surfaces.
     *
     * @returns {jQuery|null}
     */
    function productForm() {
      var form = location === 'product' ? $('#product_addtocart_form') : null;

      return form && form.length ? form : null;
    }

    // On the product page the product is not in the cart when the sheet opens.
    // It is added once for the selection on the form, and every server call
    // waits for that, so the shipping options and the order are computed for
    // the cart the shopper meant. Reopening the sheet with the same selection
    // must not add it again.
    var cartReady = null,
      addedSelection = null;

    /**
     * Add the displayed product to the cart, once per form selection.
     *
     * @returns {Promise}
     */
    function ensureProductInCart() {
      var form = productForm(),
        selection = form ? form.serialize() : null;

      if (!form) {
        return $.Deferred().resolve().promise();
      }

      if (cartReady && selection !== addedSelection) {
        cartReady = null;
      }

      if (!cartReady) {
        addedSelection = selection;
        cartReady = $.ajax({
          url: form.attr('action'),
          type: 'POST',
          data: form.serialize(),
          dataType: 'json',
          headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (response) {
          // A failed add (out of stock, an option Magento rejects) answers with
          // this page as the place to go back to and the reason in the page's
          // message list. A back URL elsewhere is the "redirect to cart after
          // adding" setting, which is a success here.
          var backUrl = response && response.backUrl ? String(response.backUrl).replace(/\/$/, '') : '',
            here = window.location.href.split('#')[0].replace(/\/$/, ''),
            failed = (response && response.product) || (backUrl && backUrl === here);

          if (failed) {
            return $.Deferred()
              .reject(new Error($t('The product could not be added to the cart.')))
              .promise();
          }

          customerData.reload(['cart'], false);
        });

        // A failed add left nothing in the cart, so the next opening tries again.
        cartReady.fail(function () {
          cartReady = null;
        });
      }

      return cartReady;
    }

    /**
     * The amount the sheet opens with on the product page: the cart already held
     * plus the product at the quantity typed. Options that change the price are
     * not known here; the shipping callback repaints the total for those.
     *
     * @returns {Number}
     */
    function productAmount() {
      var form = productForm(),
        qty = form ? parseInt(form.find('[name="qty"]').val(), 10) : 1;

      return config.amount - config.productAmount + config.productAmount * (qty > 0 ? qty : 1);
    }

    var props = {
      // accountId, never paymentId: monei.js refuses requestShipping on the
      // paymentId flow, and express has no payment yet - it is created server
      // side once the final total is known.
      accountId: config.accountId,
      amount: config.amount,
      currency: config.currency,
      language: config.language,
      style: config.style,

      onBeforeOpen: function () {
        var form = productForm();

        $(container).closest('.monei-express-shortcut').find('.monei-express-error').hide();

        // A product with options left unchosen cannot be added; the form's own
        // validation shows which, and the sheet stays closed.
        if (form && !form.validation('isValid')) {
          return false;
        }

        // Started here, not awaited: the wallet button lives in an iframe and
        // this is the only signal that the sheet is opening, and it cannot wait.
        // Every later server call awaits the same promise, and a failed add
        // rejects them, so the sheet cannot complete without the product.
        ensureProductInCart().fail(function (error) {
          fail(error && error.message);
        });

        return true;
      },

      onSubmit: function (result) {
        if (result.error) {
          fail(result.error);

          return;
        }

        ensureProductInCart().then(
          function () {
            placeOrder(result);
          },
          function (error) {
            fail(error && error.message);
          }
        );
      },

      onError: function (error) {
        fail(error && error.message ? error.message : '');
      }
    };

    /**
     * Hand the wallet's result to the server and follow its redirect.
     *
     * @param {Object} result
     */
    function placeOrder(result) {
      // No amount is sent. The server recomputes it and refuses the order if
      // the wallet's own figure disagrees.
      post(REST.placeOrder, {
        payload: JSON.stringify({
          token: result.token,
          paymentMethod: result.paymentMethod,
          billingDetails: result.billingDetails,
          shippingDetails: result.shippingDetails,
          shippingOption: result.shippingOption,
          finalAmount: result.finalAmount,
          location: location
        })
      })
        .done(function (data) {
          if (data && data.redirectUrl) {
            window.location.replace(data.redirectUrl);

            return;
          }

          window.location.href = urlBuilder.build('checkout/onepage/success');
        })
        .fail(function (xhr) {
          fail(messageFrom(xhr));
        });
    }

    if (config.requestShipping) {
      props.requestShipping = true;

      /**
       * The sheet asks for options whenever the shopper picks an address, and
       * repaints its total from what we return. Returning an amount that does
       * not already include the selected option would show a figure nobody is
       * charged - the server applies the first option before answering.
       */
      props.onShippingAddressChange = function (address) {
        return ensureProductInCart()
          .then(function () {
            return post(REST.shippingOptions, {address: JSON.stringify(address)});
          })
          .then(function (data) {
            if (data.result !== 'success') {
              // Rejecting here makes the wallet refuse the address, rather than
              // showing a total the server will not honour.
              return Promise.reject(new Error(data.message));
            }

            return {
              shippingOptions: data.shippingOptions,
              amount: data.amount
            };
          });
      };

      props.onShippingOptionChange = function (option) {
        return post(REST.selectOption, {address: '{}', optionId: option.id}).then(function (data) {
          return {amount: data.amount};
        });
      };
    }

    /**
     * Mount one wallet into its own slot. A wallet this device cannot use hides
     * its slot, so the others close the gap.
     *
     * @param {String} slotClass
     * @param {Function} factory
     */
    function mount(slotClass, factory) {
      var slot = container.querySelector('.' + slotClass);

      if (!slot || typeof factory !== 'function') {
        return;
      }

      var instance = factory(
        $.extend({}, props, {
          onLoad: function (isSupported) {
            var shortcut = $(container).closest('.monei-express-shortcut');

            slot.classList.toggle('is-unavailable', isSupported === false);
            shortcut.removeClass('is-loading');
            // No usable wallet at all leaves nothing worth a title or a divider.
            shortcut.toggleClass(
              'is-unavailable',
              !$(container).find('.monei-express-button').not('.is-unavailable, :empty').length
            );
          }
        })
      );

      instance.render(slot);
      components.push(instance);
    }

    mount('monei-express-wallet', monei.PaymentRequest);

    // The quantity typed changes what the sheet opens with.
    if (productForm() && config.productAmount) {
      productForm().on('change', '[name="qty"]', function () {
        var amount = productAmount();

        components.forEach(function (instance) {
          if (instance && instance.updateProps) {
            instance.updateProps({amount: amount}).catch(function () {});
          }
        });
      });
    }

    if (config.paypal) {
      // PayPal takes the same shipping callbacks and returns the same result
      // shape; its token places a PayPal order server side.
      mount('monei-express-paypal', monei.PayPal);
    }

    return {
      destroy: function () {
        components.forEach(function (instance) {
          if (instance && instance.destroy) {
            try {
              instance.destroy();
            } catch (e) {
              // Already gone with its container.
            }
          }
        });
        components = [];
      }
    };
  };
});
