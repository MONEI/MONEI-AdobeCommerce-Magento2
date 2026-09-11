/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */
define(['jquery', 'moneijs', 'mage/url', 'Magento_Ui/js/model/messageList', 'mage/translate'], function (
  $,
  monei,
  urlBuilder,
  globalMessageList,
  $t
) {
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
      location = config.location;

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
        return JSON.parse(xhr.responseText).message;
      } catch (e) {
        return '';
      }
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
        $(container).closest('.monei-express-shortcut').find('.monei-express-error').hide();

        return true;
      },

      onSubmit: function (result) {
        if (result.error) {
          fail(result.error);

          return;
        }

        // No amount is sent. The server recomputes it and refuses the order if
        // the wallet's own figure disagrees.
        post(REST.placeOrder, {
          payload: JSON.stringify({
            token: result.token,
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
      },

      onError: function (error) {
        fail(error && error.message ? error.message : '');
      }
    };

    if (config.requestShipping) {
      props.requestShipping = true;

      /**
       * The sheet asks for options whenever the shopper picks an address, and
       * repaints its total from what we return. Returning an amount that does
       * not already include the selected option would show a figure nobody is
       * charged - the server applies the first option before answering.
       */
      props.onShippingAddressChange = function (address) {
        return post(REST.shippingOptions, {address: JSON.stringify(address)}).then(function (data) {
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

    var paymentRequest = monei.PaymentRequest(props);

    paymentRequest.render(container);
  };
});
