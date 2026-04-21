# Magento 2 Docker Development Environment

This directory contains the Docker configuration for the Magento 2 Zero to Hero training course.

## Prerequisites

- Docker Desktop installed and running
- At least 8GB RAM available
- Ports 8089, 3306, 6379, 9201, 8091 available

## IMPORTANT: Magento Authentication Keys

### Why do you need auth keys?

Magento's Composer repository (repo.magento.com) requires authentication to download Magento packages. This is **NOT** your Magento Admin or Adobe account - you need Access Keys from Magento Marketplace.

### Getting Your Access Keys

1. Go to https://account.magento.com and sign in
2. Click your name in the top-right corner → **My Profile**
3. In left menu, click **Access Keys**
4. In the **Magento 2** tab, click **Create A New Access Key**
5. Copy the **Public Key** (shorter) and **Private Key** (longer)

### Option 1: Configure globally (Recommended)

```bash
docker compose exec app composer global config http-basic.repo.magento.com <PUBLIC_KEY> <PRIVATE_KEY>
```

### Option 2: Create auth.json file

Create `src/auth.json` (next to composer.json) with:

```json
{
  "http-basic": {
    "repo.magento.com": {
      "username": "YOUR_PUBLIC_KEY",
      "password": "YOUR_PRIVATE_KEY"
    }
  }
}
```

⚠️ **Security Note**: Never commit `auth.json` to git. It's already in `.gitignore`.

## Quick Start: Complete Setup

```bash
# Step 1: Navigate to project directory
cd magento-training/courses/magento2-zero-to-hero

# Step 2: Start Docker services
docker compose up -d --build

# Khi bạn chỉ sửa code PHP, sửa file XML hoặc cấu hình Magento, bạn chỉ cần dùng lệnh:
docker compose up -d

# Step 3: Wait for services to be healthy
docker compose ps
# All services should show "healthy" before proceeding
# If not, wait 60 seconds and check again

# Step 4: Verify services
curl -s http://localhost:9201 | head -3   # Elasticsearch
docker compose exec db mysqladmin ping -h localhost -u root -prootpassword  # MySQL

# Step 5: Configure auth keys (see Prerequisites section above)
# Then install Composer dependencies
bin/composer install --no-interaction --prefer-dist

# Step 6: Disable Elasticsearch modules (required before install)
# This avoids OpenSearch connection issues
docker compose exec phpfpm php /var/www/html/vendor/magento/community-edition/bin/magento module:disable Magento_Elasticsearch Magento_Elasticsearch8 Magento_OpenSearch

# Step 7: Install Magento database and admin user
# Note: Elasticsearch modules are disabled for simplicity - MySQL used for search
docker compose exec phpfpm php /var/www/html/vendor/magento/community-edition/bin/magento setup:install \
  --db-host=db --db-name=magento --db-user=magento --db-password=magento \
  --base-url=http://localhost:8089/ \
  --admin-firstname=Admin --admin-lastname=User \
  --admin-email=admin@example.com \
  --admin-user=admin --admin-password=admin123 \
  --language=en_US --currency=USD --timezone=America/Chicago \
  --use-rewrites=1 --backend-frontname=admin \
  --session-save=redis --session-save-redis-host=redis --session-save-redis-db=1 \
  --cache-backend=redis --cache-backend-redis-server=redis --cache-backend-redis-db=2 \
  --page-cache=redis --page-cache-redis-server=redis --page-cache-redis-db=3

# # Step 8: Run setup commands (for existing database, skip step 7)
# $ docker compose exec phpfpm php //var/www/html/vendor/magento/community-edition/bin/magento setup:upgrade
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
bin/magento indexer:reindex

# Step 9: Set permissions
docker compose exec phpfpm chmod -R 777 var generated pub/static pub/media

# Step 10: Verify CLI works
bin/magento --version
# Expected: Magento CLI version 2.4.8-p3

# Step 11: For frontend development, copy pub files to host
# (Required because pub/ is mounted from src/pub/)
docker cp magento2-app:/var/www/html/vendor/magento/community-edition/pub/. ../src/pub/
```

## Importing Existing Database

If you have a database dump:

```bash
# Import SQL dump into MySQL
docker compose exec -T db mysql -umagento -pmagento magento < path/to/dump.sql

# Then import config and compile
bin/magento app:config:import
bin/magento setup:di:compile
bin/magento cache:flush
```

## Access Points

| Service          | URL                         | Credentials           |
| ---------------- | --------------------------- | --------------------- |
| Magento CLI      | bin/magento                 | Uses phpfpm container |
| Magento Frontend | http://localhost:8089/      | -                     |
| Admin Panel      | http://localhost:8089/admin | admin / admin123      |
| PHPMyAdmin       | http://localhost:8091       | magento / magento     |
| MySQL            | localhost:3306              | magento / magento     |
| Redis            | localhost:6379              | -                     |
| Elasticsearch    | http://localhost:9201       | -                     |

## Important Notes

### Elasticsearch Disabled

The default installation uses MySQL for search (simpler for development). Elasticsearch modules are disabled:

```bash
# To enable Elasticsearch later:
bin/magento module:enable Magento_Elasticsearch Magento_Elasticsearch8
```

### MySQL Configuration

To allow Magento triggers during installation, add this to mysql.cnf:

```ini
log_bin_trust_function_creators=1
```

### Post-Install Commands (for existing database)

```bash
# Always run these after setup:install
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
bin/magento indexer:reindex
```

## Daily Commands

```bash
# Start environment
docker compose up -d

# Stop environment
docker compose down

# Access container shell
bin/bash

# View logs
docker compose logs -f phpfpm
docker compose logs -f db

# Restart services
docker compose restart phpfpm

# Re-run composer install
bin/composer install --prefer-dist --no-interaction

# Run Magento commands
bin/magento setup:upgrade
bin/magento cache:flush
#docker compose exec app php bin/magento cache:flush

```

## Troubleshooting

## Troubleshooting

### "Connection refused" to MySQL

```bash
# Solution: Wait for MySQL to be ready
docker compose ps
# If showing "starting", wait 60 more seconds
docker compose logs db | tail -20
```

### "Elasticsearch not ready"

```bash
# Check ES status
curl http://localhost:9201

# If failing, restart ES
docker compose restart elasticsearch
sleep 30
curl http://localhost:9201
```

### "Permission denied" errors

```bash
# Fix permissions
docker compose exec app chmod -R 777 var generated pub/static pub/media
```

### Module not showing after creating files

```bash
# Run setup:upgrade to register module
docker compose exec app php bin/magento setup:upgrade

# Clear cache
docker compose exec app php bin/magento cache:flush
```

### Composer auth error (401/403)

```bash
# Configure auth keys (see Prerequisites section)
docker compose exec app composer global config http-basic.repo.magento.com <PUBLIC_KEY> <PRIVATE_KEY>
```

### "Vendor directory not found"

```bash
# Run composer install inside container
docker compose exec app composer install --prefer-dist --no-interaction
```

### Frontend shows 500 error (MediaStorage circular dependency)

This is a known Magento 2.4.7 bug affecting frontend only. **CLI commands work fine for backend development.**

To proceed with frontend development, you need to patch Magento core:

```bash
# Run inside container as root
docker compose exec -u root app bash

# Patch File.php to make storageHelper nullable
sed -i 's/\Magento\\MediaStorage\\Helper\\File\\Storage\\Database \$storageHelper,/\Magento\\MediaStorage\\Helper\\File\\Storage\\Database \$storageHelper = null,/' \
  /var/www/html/vendor/magento/community-edition/app/code/Magento/MediaStorage/Model/File/Storage/File.php

# Patch Database.php to make fileStorage nullable
sed -i 's/\Magento\\MediaStorage\\Model\\File\\Storage\\File \$fileStorage,/\Magento\\MediaStorage\\Model\\File\\Storage\\File \$fileStorage = null,/' \
  /var/www/html/vendor/magento/community-edition/app/code/Magento/MediaStorage/Helper/File/Storage/Database.php

# Exit container and clear cache
exit
docker compose exec app php bin/magento cache:flush
```

## Understanding the Setup

### Why separate volumes?

- `src/app/code` → Your custom modules (committed to git)
- `vendor` → Magento code (not committed, installed via composer)
- `var` → Logs, cache (can be cleared anytime)
- `generated` → Generated factories (regenerated via bin/magento)

### Service Dependencies

```
app → phpfpm → db → elasticsearch → redis
```

All services must be healthy before Magento can install properly.
