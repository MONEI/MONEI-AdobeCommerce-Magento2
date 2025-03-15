/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define([], function () {
  'use strict';

  /**
   * Card type patterns
   * These patterns help identify the card type based on the card number prefix
   */
  var cardTypePatterns = {
    visa: {
      pattern: /^4/,
      length: [13, 16, 19]
    },
    mastercard: {
      pattern: /^(5[1-5]|2[2-7])/,
      length: [16]
    },
    amex: {
      pattern: /^3[47]/,
      length: [15]
    },
    discover: {
      pattern: /^(6011|65|64[4-9]|622)/,
      length: [16, 19]
    },
    diners: {
      pattern: /^(36|38|30[0-5])/,
      length: [14, 16, 19]
    },
    jcb: {
      pattern: /^35/,
      length: [16, 19]
    },
    unionpay: {
      pattern: /^62/,
      length: [16, 17, 18, 19]
    },
    maestro: {
      pattern: /^(5[06-8]|6)/,
      length: [12, 13, 14, 15, 16, 17, 18, 19]
    }
  };

  return {
    /**
     * Detects the card type based on card number
     *
     * @param {String} cardNumber The card number to check
     * @return {String|null} The detected card type or null if not detected
     */
    detectCardType: function (cardNumber) {
      if (!cardNumber) {
        return null;
      }

      // Remove any spaces or dashes
      cardNumber = cardNumber.replace(/[\s-]/g, '');

      // Check against each pattern
      for (var cardType in cardTypePatterns) {
        if (cardTypePatterns.hasOwnProperty(cardType)) {
          var pattern = cardTypePatterns[cardType].pattern;

          if (pattern.test(cardNumber)) {
            // Check if length is valid for this card type
            var validLengths = cardTypePatterns[cardType].length;

            // If we don't have enough digits yet, return the card type anyway
            // This is for early detection while typing
            if (cardNumber.length < 12) {
              return cardType;
            }

            // Otherwise check if the length is valid for this card type
            if (validLengths.indexOf(cardNumber.length) !== -1) {
              return cardType;
            }
          }
        }
      }

      return null;
    }
  };
});
