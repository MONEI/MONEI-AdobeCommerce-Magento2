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

        moneiCardPaymentId: ko.observable(null),
        moneiVaultPaymentId: ko.observable(null),
        moneiBizumPaymentId: ko.observable(null),
        moneiCardToken: ko.observable(null),
        moneiBizumToken: ko.observable(null),
        moneiCardholderName: ko.observable(null),
        moneiVaultChecked: ko.observable(null),

        /**
         * Get monei card payment id
         */
        getMoneiCardPaymentId: function () {
            return this.moneiCardPaymentId();
        },
        /**
         * Set monei card payment id
         */
        setMoneiCardPaymentId: function (val) {
            this.moneiCardPaymentId(val);
        },

        /**
         * Get monei vault payment id
         */
        getMoneiVaultPaymentId: function () {
            return this.moneiVaultPaymentId();
        },

        /**
         * Set monei vault payment id
         */
        setMoneiVaultPaymentId: function (val) {
            this.moneiVaultPaymentId(val);
        },

        /**
         * Get monei bizum payment id
         */
        getMoneiBizumPaymentId: function () {
            return this.moneiBizumPaymentId();
        },

        /**
         * Set monei bizum payment id
         */
        setMoneiBizumPaymentId: function (val) {
            this.moneiBizumPaymentId(val);
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
         * Get monei bizum token
         */
        getMoneiBizumToken: function () {
            return this.moneiBizumToken();
        },
        /**
         * Set monei bizum token
         */
        setMoneiBizumToken: function (val) {
            this.moneiBizumToken(val);
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
