/**
 * @copyright Copyright © Monei (https://monei.com)
 */
define(['jquery', 'mage/translate'], function ($, $t) {
  'use strict';

  return function (validator) {
    // Ensure the validate-json rule is available
    $.validator.addMethod(
      'validate-json',
      function (value) {
        if (value === '') {
          return true;
        }

        try {
          JSON.parse(value);
          return true;
        } catch (e) {
          console.error('JSON validation error:', e.message);
          return false;
        }
      },
      $.mage.__(
        'Please enter valid JSON. Examples: {"height":"45px"} or {"base":{"height":"30px"}}'
      )
    );

    // Return the original validator with our additions
    return validator;
  };
});
