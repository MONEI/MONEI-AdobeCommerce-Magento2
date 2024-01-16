/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define([
    'mage/utils/wrapper',
    'ko'
], function (wrapper, ko) {
    'use strict';

    var extender = {

        moneiPaymentId: ko.observable(null),
        moneiCardToken: ko.observable(null),
        moneiCardholderName: ko.observable(null),
        moneiVaultChecked: ko.observable(null),

        /**
         * Get monei payment id
         */
        getMoneiPaymentId: function () {
            return this.moneiPaymentId();
        },
        /**
         * Set monei payment id
         */
        setMoneiPaymentId: function (val) {
            this.moneiPaymentId(val);
        },

        /**
         * Get monei card token
         */
        getMoneiCardToken: function () {
            return this.moneiCardToken();
        },
        /**
         * Set monei card token
         */
        setMoneiCardToken: function (val) {
            this.moneiCardToken(val);
        },

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
        },
    };

    return function (target) {
        return wrapper.extend(target, extender);
    };
});
