# Varnish Integration for MONEI Payment Module

This document provides instructions on how to properly integrate the MONEI Payment Module with Varnish cache.

## Overview

The MONEI Payment Module requires specific Varnish configurations to work correctly:

1. Payment processing endpoints should never be cached
2. Payment callbacks need to pass through Varnish without caching
3. MONEI-specific headers need to be handled correctly

## Installation

### 1. Include the MONEI VCL Configuration

Add the following line to your main Varnish configuration file (default.vcl):

```vcl
include "path/to/monei/varnish-config.vcl";
```

Make sure to replace "path/to/monei" with the actual path to the module's etc directory.

### 2. Restart Varnish

After updating the configuration, restart Varnish:

```bash
systemctl restart varnish
```

Or using the appropriate restart command for your system.

## Verification

To verify that Varnish is correctly configured for MONEI Payment Module:

1. Make a test payment
2. Check that the payment process completes without errors
3. Verify that callbacks are received and processed correctly

## Troubleshooting

If you experience issues with the payment process:

1. Check Varnish logs for any cache-related errors
2. Ensure all payment-related URLs are passing through Varnish without caching
3. Verify that your Varnish configuration includes the MONEI VCL file

For additional assistance, contact MONEI support at support@monei.com.
