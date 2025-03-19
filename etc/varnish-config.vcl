# Varnish configuration for MONEI Payment Module
# This file needs to be included in your main VCL file

# Ensure payment processing endpoints are never cached
sub vcl_recv {
    # Skip caching for all MONEI payment processing URLs
    if (req.url ~ "^/monei/payment/") {
        return (pass);
    }

    # Skip caching for callback URLs (which are POST requests)
    if (req.url ~ "^/monei/payment/callback") {
        return (pass);
    }
    
    # Handle MONEI-specific headers
    if (req.http.MONEI-Signature) {
        return (pass);
    }
}

# Ensure payment-related cookies are properly handled
sub vcl_hash {
    # Include cookies for payment pages in cache key
    if (req.url ~ "^/checkout/") {
        hash_data(req.http.cookie);
    }
} 