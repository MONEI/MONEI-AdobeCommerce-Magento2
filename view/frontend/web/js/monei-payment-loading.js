define(['jquery', 'loader', 'moneijs', 'mage/translate'], function ($, _, monei, $t) {
  'use strict';

  return function (config) {
    var paymentId = config.paymentId;
    var orderId = config.orderId || '';
    var completeUrl = config.completeUrl;
    var maxAttempts = 20;
    var attempts = 0;
    var checkInterval = 1000; // 1 second

    // Initialize the loader widget on the container with custom template
    $('#monei-payment-loading-container').loader({
      icon: require.toUrl('images/loader-1.gif'),
      texts: {
        loaderText: $t('Checking payment status, it may take a few seconds...'),
        imgAlt: $t('Loading')
      }
    });

    // Start the loader
    $('#monei-payment-loading-container').trigger('processStart');

    function redirectToComplete() {
      if (this.successUrl) {
        window.location.href = this.successUrl;
      } else {
        window.location.reload();
      }
    }

    function checkPaymentStatus() {
      if (attempts >= maxAttempts) {
        // If we've exceeded max attempts, redirect to complete URL
        // with the payment ID so the server can check the final status
        redirectToComplete();
        return;
      }

      // Use Monei's API directly to check payment status
      monei.api
        .getPayment(paymentId)
        .then(function (result) {
          if (result && result.status) {
            if (result.status !== 'PENDING') {
              // Redirect to complete endpoint with payment ID
              redirectToComplete();
            } else {
              // Still pending, check again
              attempts++;
              setTimeout(checkPaymentStatus, checkInterval);
            }
          } else {
            // Invalid response, try again
            attempts++;
            setTimeout(checkPaymentStatus, checkInterval);
          }
        })
        .catch(function (error) {
          // Error getting status, try again
          attempts++;
          setTimeout(checkPaymentStatus, checkInterval);
        });
    }

    // Start checking payment status
    $(document).ready(function () {
      setTimeout(checkPaymentStatus, 1000); // Start checking after 1 second
    });
  };
});
