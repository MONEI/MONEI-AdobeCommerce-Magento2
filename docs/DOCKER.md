# Docker Setup for Development

This document provides detailed instructions for setting up and using the Docker environment for MONEI Payment module development.

## Overview

The MONEI Payment module can be developed using Docker in two ways:

1. As part of a complete Magento installation using [markshust/docker-magento](https://github.com/markshust/docker-magento) (recommended)
2. Using a standalone Docker setup for isolated module development

## Recommended Setup with markshust/docker-magento

[markshust/docker-magento](https://github.com/markshust/docker-magento) provides a complete Docker environment for Magento 2 development, which we recommend for the most realistic testing environment.

### Prerequisites

- Docker Desktop with at least 6GB RAM allocated
- Dual-core processor or better
- SSD hard drive

### Setup

1. Create your project directory:

```bash
mkdir -p ~/Sites/magento
cd $_
```

2. Run the automated one-liner setup script:

```bash
curl -s https://raw.githubusercontent.com/markshust/docker-magento/master/lib/onelinesetup | bash -s -- magento.test community 2.4.7-p3
```

This will set up a complete Magento environment at `https://magento.test`.

3. Install the MONEI Payment module:

```bash
# Navigate to the Magento code directory
cd src/app/code

# Create the module directory
mkdir -p Monei/MoneiPayment

# Clone the module
git clone https://github.com/MONEI/MONEI-AdobeCommerce-Magento2.git MoneiPayment
cd MoneiPayment

# Install and enable
bin/magento module:enable Monei_MoneiPayment
bin/magento setup:upgrade
bin/magento cache:flush
```

### Using Docker Helper Scripts

markshust/docker-magento provides several helper scripts in the `bin` directory:

```bash
bin/start         # Start the containers
bin/stop          # Stop the containers
bin/restart       # Restart the containers
bin/bash          # Access the bash shell in the app container
bin/cli           # Run Magento CLI commands
bin/composer      # Run Composer commands
bin/magento       # Shorthand for bin/cli bin/magento
bin/setup-grunt   # Set up Grunt for frontend development
bin/grunt         # Run Grunt commands
bin/xdebug        # Enable/disable/status of Xdebug
```

## Standalone Docker Setup

For isolated module development, you can use a standalone Docker setup.

### Prerequisites

- Docker and Docker Compose installed
- Basic Docker knowledge

### Getting Started

1. Navigate to the module's directory and start the containers:

```bash
cd /path/to/MoneiPayment
docker-compose up -d
```

2. Access the PHP container:

```bash
docker-compose exec php bash
```

### Available Services

The standalone setup includes:

- **PHP 8.3**: The main application container
- **MySQL**: Database service
- **Redis**: Cache and session storage

## Common Docker Commands

```bash
# View running containers
docker ps

# View logs
docker-compose logs

# Rebuild containers after configuration changes
docker-compose up -d --build

# Stop containers
docker-compose down

# Stop and remove volumes
docker-compose down -v
```

## Troubleshooting

### Container Won't Start

If containers fail to start:

- Check Docker logs: `docker-compose logs`
- Check for port conflicts: `docker ps -a`
- Check Docker Desktop resources allocation

### Permission Issues

If you encounter permission problems:

```bash
docker-compose exec php chown -R www-data:www-data /var/www/html
```

### Performance Issues

- Increase Docker Desktop resource allocation
- Use volume mounts carefully, especially on macOS
- Consider using Docker Volume or Docker Sync for better performance
