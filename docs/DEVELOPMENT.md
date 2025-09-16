# Development Guide

This document provides comprehensive information for developers working with the MONEI Payment module for Adobe Commerce (Magento 2).

## Local Development Setup

For local development, we recommend using [markshust/docker-magento](https://github.com/markshust/docker-magento) which provides a robust Docker setup for Magento 2 development.

1. First, set up docker-magento:

```bash
# Download the setup script
curl -s https://raw.githubusercontent.com/markshust/docker-magento/master/lib/onelinesetup | bash -s -- magento.test 2.4.6-p3 community
```

2. Clone the MONEI Payment module into the correct directory:

```bash
# Navigate to the module directory
cd src/app/code
mkdir -p Monei/MoneiPayment
git clone https://github.com/MONEI/MONEI-AdobeCommerce-Magento2.git MoneiPayment
cd MoneiPayment
```

3. Install module dependencies and enable it:

```bash
# Install MONEI SDK
bin/composer require monei/monei-php-sdk:^2.8.3

# Enable module and run setup
bin/magento module:enable Monei_MoneiPayment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
bin/magento cache:clean
```

4. The module should now be installed in your local Magento instance at https://magento.test

## Available Commands

For development, you can use the following commands:

```bash
# Code quality
bin/composer cs:lint                   # Run PHPCS code sniffer
bin/composer cs:fix                    # Fix coding standards with PHPCBF
bin/composer analyze                   # Run PHPStan static analysis
bin/composer format:check             # Check formatting with pretty-php
bin/composer format:fix               # Fix formatting with pretty-php
bin/composer fix:all                  # Run all fixers (cs:fix and format:fix)
bin/composer check:all               # Run all code quality checks (cs:lint, analyze, format:check)

# Frontend
yarn format                        # Format frontend code with prettier

# Magento commands (using helper script)
bin/magento setup:di:compile      # Compile dependency injection
bin/magento setup:upgrade         # Run module upgrades
bin/magento cache:clean           # Clean caches
bin/magento cache:flush           # Flush all caches
bin/magento module:enable Monei_MoneiPayment  # Enable module
```

## Setting Up Cloudflare Tunnel for Payment Callbacks

MONEI sends payment callbacks to your store to update order statuses and process payments. During local development, your server isn't publicly accessible. Cloudflare Tunnel creates a secure tunnel to expose your local environment to the internet, allowing MONEI to send callbacks to your development environment.

### Step 1: Create a Cloudflare Tunnel

1. Log in to the [Cloudflare Zero Trust Dashboard](https://one.dash.cloudflare.com/)
2. Navigate to **Access > Tunnels**
3. Click **Create a tunnel**
4. Name your tunnel (e.g., `monei-magento`) and click **Save tunnel**
5. Copy the tunnel token that is displayed

### Step 2: Configure docker-magento

1. Create a `cloudflare.env` file in the `env` directory of your docker-magento installation:

```bash
# From your docker-magento root directory
mkdir -p env
echo "TUNNEL_TOKEN=your-tunnel-token-here" > env/cloudflare.env
```

2. Add the Cloudflare Tunnel section to your `compose.yaml` file:

```yaml
# Cloudflare tunnel
tunnel:
  container_name: cloudflared-tunnel
  image: cloudflare/cloudflared:latest
  command: tunnel run
  env_file: env/cloudflare.env
```

3. Restart the containers:

```bash
bin/restart
```

### Step 3: Configure Cloudflare Tunnel Service

1. Back in the Cloudflare Zero Trust Dashboard, configure the tunnel:

   - For Service Type, select **HTTPS** from the dropdown
   - For URL, enter your `app.container_name` and port: `monei-magento2-dev:8443`
   - Enable the **No TLS Verify** option (since local certificates are self-signed)

2. Set up a public hostname:
   - Enter a subdomain (e.g., `magento`)
   - Select your domain (e.g., `monei-dev-tunnel.com`)
   - Leave Path empty (optional)
   - Save the configuration

Your local environment will now be accessible at `https://magento.monei-dev-tunnel.com`

### Security Considerations

- **Important**: Do not leave instances with Cloudflare Tunnel enabled running long-term, as your instance is publicly available to the world. Turn off the tunnel container once testing is finished.

## Troubleshooting

If you encounter issues with the module:

1. Ensure your Magento and PHP versions meet the requirements
2. Check that the module is properly installed and enabled
3. Verify your MONEI API credentials are correct
4. Clear your Magento cache and run setup:upgrade again
5. Check the Magento logs for any error messages

### Common Issues

- **Payment form not loading**: Verify your API key is correctly set in the admin configuration
- **Webhook errors**: Check your server's firewall settings and ensure Cloudflare Tunnel is properly configured
- **Compilation errors**: Run `bin/magento setup:di:compile` and check the error log for detailed information
