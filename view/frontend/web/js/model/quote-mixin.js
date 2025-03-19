/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */
define(['mage/utils/wrapper', 'ko'], function (wrapper, ko) {
  'use strict';

  var extender = {
    moneiCardholderName: ko.observable(null),
    moneiVaultChecked: ko.observable(null),

    /**
     * Get monei cardholder name
     */
    getMoneiCardholderName: function () {
      return this.moneiCardholderName();
    },
    /**
     * Set monei cardholder name
     */
    setMoneiCardholderName: function (val) {
      this.moneiCardholderName(val);
    },

    /**
     * Get monei vault checked
     */
    getMoneiVaultChecked: function () {
      return this.moneiVaultChecked();
    },
    /**
     * Set monei vault checked
     */
    setMoneiVaultChecked: function (val) {
      this.moneiVaultChecked(val);
    }
  };

  return function (target) {
    return wrapper.extend(target, extender);
  };
});
