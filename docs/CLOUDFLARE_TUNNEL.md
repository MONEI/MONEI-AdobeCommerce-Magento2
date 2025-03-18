# Configuring Cloudflare Tunnel for Payment Callbacks

This document provides instructions for setting up Cloudflare Tunnel to receive payment callbacks from MONEI during local development using markshust/docker-magento.

## Overview

MONEI sends payment callbacks to your store to update order statuses and process payments. During local development, your server isn't publicly accessible. Cloudflare Tunnel creates a secure tunnel to expose your local environment to the internet, allowing MONEI to send callbacks to your development environment.

## Setup with markshust/docker-magento

The markshust/docker-magento setup includes built-in support for Cloudflare Tunnel, making it easy to expose your local development environment.

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

2. Uncomment the Cloudflare Tunnel section in the main `compose.yaml` file:

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
   - For URL, enter your container name and port: `monei-magento2-dev:8443`
   - Enable the **No TLS Verify** option (since local certificates are self-signed)

2. Set up a public hostname:
   - Enter a subdomain (e.g., `magento`)
   - Select your domain (e.g., `monei-dev-tunnel.com`)
   - Leave Path empty (optional)
   - Save the configuration

Your local environment will now be accessible at `https://magento.monei-dev-tunnel.com`

## Security Considerations

- **Important**: Do not leave instances with Cloudflare Tunnel enabled running long-term, as your instance is publicly available to the world. Turn off the tunnel container once testing is finished.
