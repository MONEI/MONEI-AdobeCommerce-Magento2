define(['jquery'], function ($) {
  'use strict';

  return {
    /**
     * Ensure that the Monei SDK is loaded and ready to use
     *
     * @returns {Promise} Promise that resolves when Monei SDK is ready
     */
    ensureMonei: function () {
      return new Promise(function (resolve, reject) {
        // Check if Monei is already loaded
        if (typeof window.monei !== 'undefined') {
          resolve(window.monei);
          return;
        }

        // If not loaded yet, check every 100ms up to 5 seconds
        var maxAttempts = 50;
        var attempts = 0;
        var interval = setInterval(function () {
          attempts++;
          if (typeof window.monei !== 'undefined') {
            clearInterval(interval);
            resolve(window.monei);
          } else if (attempts >= maxAttempts) {
            clearInterval(interval);
            reject(new Error('Timed out waiting for Monei SDK to load'));
          }
        }, 100);
      });
    }
  };
});
