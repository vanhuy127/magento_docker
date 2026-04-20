# Week 1: Development Environment & Magento Foundations

**Goal:** Set up your development environment and understand Magento's architecture well enough to create a working module by week's end.

---

## Topics Covered

- What Magento is, where it fits in e-commerce, request flow overview
- Magento directory structure — where your code lives
- Module anatomy — minimum files needed to create a module
- Docker-based development environment setup
- `bin/magento` CLI — essential commands you'll use daily
- Reading and writing Magento log files
- Module registration, enable/disable cycle

---

## Reference Exercises

- **Exercise 1.1:** Create your first `Training_HelloWorld` module from scratch
- **Exercise 1.2:** Set up Magento via Docker Compose
- **Exercise 1.3:** Verify route `helloworld/index/index` responds
- **Exercise 1.4:** Write a custom log entry to `var/log/`

---

## By End of Week 1 You Must Prove

- [ ] Docker environment running Magento (accessible from host)
- [ ] `Training_HelloWorld` module enabled, visible in `bin/magento module:status`
- [ ] Route `helloworld/index/index` returns a page (not 404)
- [ ] `bin/magento cache:disable` executed (developer mode ready)
- [ ] Custom log written to `var/log/` from your module
- [ ] `registration.php` and `module.xml` correct and minimal
- [ ] DoD assessment passed

---

## Assessment Criteria

| Test | Time | Criteria |
|------|------|----------|
| Docker Setup | 30 min | Magento running in Docker, accessible from host |
| Module Creation | 20 min | Create full `Training_HelloWorld` module from scratch |
| Route Test | 15 min | `helloworld/index/index` route responds with page |
| Logging | 10 min | Custom message written to `var/log/training_helloworld.log` |

---

## Topics

---

### Topic 1: What is Magento?

**What is Magento?**

Magento is a PHP-based e-commerce platform providing catalog management, checkout, pricing, promotions, and a highly customizable module architecture. It powers a significant share of global enterprise e-commerce.

**Editions:**

| Edition | Description | Cost |
|---------|-------------|------|
| Magento Open Source | Core platform | Free |
| Magento Commerce | Enterprise features (ACL, staging, advanced reporting) | Paid |
| Magento Commerce Cloud | Cloud-hosted Commerce | Paid |

This course uses **Magento Open Source 2.4.6+**.

**Request Flow — The Big Picture**

```
HTTP Request → Router → Controller → Result (Page, JSON, Redirect)
```

For page requests, the flow adds Block, Template, and Layout XML:

```
Controller → Block (PHP logic) → Template (PHTML → HTML) → Layout XML → HTTP Response
```

**Key Concepts:**

| Concept | What It Is | Example |
|---------|-----------|---------|
| Module | Package of code adding functionality | `Training_HelloWorld` |
| Theme | Controls visual appearance | Luma, Blank |
| Area | Part of Magento with specific config | `frontend`, `adminhtml`, `graphql` |
| Service Contract | Public API via interfaces | `CustomerRepositoryInterface` |
| Dependency Injection | Magento's way of providing dependencies | Constructor injection |

---

### Topic 2: Directory Structure

```
app/code/               ← Custom modules (your work goes here)
app/design/             ← Themes (frontend/adminhtml)
app/etc/                ← Global config (env.php, config.php)
pub/static/             ← Generated static assets (CSS, JS, images)
pub/index.php           ← Application entry point
var/generation/         ← Generated factory classes and proxies
var/log/                ← Log files (system.log, exception.log)
var/cache/              ← Cache files
vendor/                 ← Composer dependencies
```

**Module Structure:**

```
app/code/[Vendor]/[Module]/
├── registration.php        ← Registers module with Magento
├── etc/
│   ├── module.xml          ← Declares module name + version
│   ├── di.xml             ← Dependency injection config
│   ├── frontend/routes.xml ← Frontend routes
│   └── adminhtml/routes.xml ← Admin routes
├── Controller/            ← HTTP request handlers
├── Block/                 ← PHP view logic
├── Model/                 ← Business logic + data
└── View/                  ← Templates and layout XML
```

**Key Configuration Files:**

| File | Purpose |
|------|---------|
| `env.php` | Database + cache config (gitignored — never commit) |
| `config.php` | Module list + configuration (committed) |
| `di.xml` | Plugin preferences, virtual types, factories |
| `events.xml` | Event observers |
| `webapi.xml` | REST API route definitions |

---

### Topic 3: Your First Module

**Minimum Files Required:**

`registration.php` — registers the module with Magento's component system:

```php
<?php
use Magento\Framework\Component\ComponentRegistrar;
ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Training_HelloWorld',
    __DIR__
);
```

`etc/module.xml` — declares the module and its version:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Training_HelloWorld" setup_version="1.0.0"/>
</config>
```

**First Route — `etc/frontend/routes.xml`:**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:App/etc/routes.xsd">
    <router id="standard">
        <route id="helloworld" frontName="helloworld">
            <module name="Training_HelloWorld"/>
        </route>
    </router>
</config>
```

**First Controller — `Controller/Index/Index.php`:**

```php
<?php
namespace Training\HelloWorld\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Index implements HttpGetActionInterface
{
    protected $resultFactory;

    public function __construct(ResultFactory $resultFactory)
    {
        $this->resultFactory = $resultFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        return $resultPage;
    }
}
```

**Result:** Visit `http://localhost/helloworld/index/index`

**Enable Module:**

```bash
bin/magento module:enable Training_HelloWorld
bin/magento setup:upgrade
bin/magento module:status  # verify listed as enabled
```

---

### Topic 4: Docker Setup

**Why Docker?**

| Without Docker | With Docker |
|---------------|-------------|
| "It works on my machine" | Consistent across team |
| Manual PHP/MySQL/ES install | One command starts everything |
| Version conflicts | Same versions for all |

**Docker Compose File:**

> ⚠️ **IMPORTANT:** The Docker configuration is in the `docker/` directory. Make sure you are in that directory when running Docker commands.

```yaml
version: '3.8'
services:
  app:
    build:
      context: ..
      dockerfile: docker/Dockerfile
    container_name: magento2-app
    volumes:
      - ./src/app/code:/var/www/html/app/code:cached
      - ./src/app/etc:/var/www/html/app/etc:cached
      - ./src/composer.json:/var/www/html/composer.json:cached
      - ./src/generated:/var/www/html/generated:cached
      - ./src/var:/var/www/html/var:cached
      - magento-vendor:/var/www/html/vendor
    ports:
      - "80:80"
    environment:
      - PHP_MEMORY_LIMIT=2G
      - PHP_MAX_EXECUTION_TIME=1800
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_started
      elasticsearch:
        condition: service_healthy

  db:
    image: mysql:8.0
    container_name: magento2-db
    environment:
      MYSQL_DATABASE: magento
      MYSQL_USER: magento
      MYSQL_PASSWORD: magento
      MYSQL_ROOT_PASSWORD: rootpassword
    volumes:
      - db-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-prootpassword"]
      interval: 10s
      timeout: 5s
      retries: 10

  redis:
    image: redis:7-alpine

  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:7.17.16
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
      - ES_JAVA_OPTS=-Xms512m -Xmx512m

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    environment:
      - PMA_HOST=db
      - PMA_PORT=3306
      - PMA_USER=magento
      - PMA_PASSWORD=magento
    ports:
      - "8080:80"

volumes:
  db-data:
  magento-vendor:
```

**Setup Commands (Step-by-Step):**

> ⚠️ **IMPORTANT:** Run each command one at a time. Wait for each to complete before running the next.

```bash
# Step 1: Navigate to docker directory
cd docker

# Step 2: Start services (this builds the Docker image first time, takes 3-5 minutes)
docker compose up -d --build

# Step 3: Wait for all services to be ready
# Check that all services show "healthy" or "running"
docker compose ps

# If any service shows "restarting" or "unhealthy", wait 30 seconds and check again
# MySQL takes 30-60 seconds on first start

# Step 4: Verify services are listening on expected ports
curl -s http://localhost:9200 | head -5   # Should return JSON from Elasticsearch
docker compose exec db mysqladmin ping -h localhost -u root -prootpassword  # Should say "mysqld is alive"

# Step 5: Install Composer dependencies (takes 5-15 minutes first time)
docker compose exec app composer install --no-interaction --prefer-dist

# If composer install fails with auth error:
# Create auth.json with your Magento marketplace credentials
# docker compose exec app composer config -g http-basic.repo.magento.com <public_key> <private_key>

# Step 6: Install Magento (creates database and admin user)
docker compose exec app bin/magento setup:install \
  --db-host=db --db-name=magento --db-user=magento --db-password=magento \
  --base-url=http://localhost/ \
  --admin-firstname=Admin --admin-lastname=User \
  --admin-email=admin@example.com \
  --admin-user=admin --admin-password=admin123

# Step 7: Enable developer mode
docker compose exec app bin/magento deploy:mode:set developer

# Step 8: Disable cache during development (speeds up development)
docker compose exec app bin/magento cache:disable

# Step 9: Fix permissions (required for file write operations)
docker compose exec app chmod -R 777 var generated pub/static

# Step 10: Verify installation succeeded
docker compose exec app bin/magento --version
# Expected output: Magento CLI version 2.4.6 (or similar)
```

---

### Troubleshooting Setup Issues

| Error | Cause | Solution |
|-------|-------|----------|
| `Connection refused` to MySQL | MySQL not ready yet | Wait 60 seconds, run `docker compose ps` |
| `Access denied` for magento user | Wrong password in env.php | Check docker-compose.yml has `MYSQL_PASSWORD=magento` |
| `Elasticsearch not ready` | ES taking long to start | Run `curl http://localhost:9200` to check |
| `Composer install timeout` | Slow network | Try again, or use `--no-dev` flag |
| `Permission denied` on var/ | User mismatch | Run `docker compose exec app chown -R www-data:www-data /var/www/html` |
| `Route returns 404` | Module not enabled | Run `bin/magento setup:upgrade` then `bin/magento cache:flush` |

---

**Daily Docker Commands:**

```bash
cd docker
docker compose up -d              # Start
docker compose down                # Stop
docker compose exec app bash       # Shell into container
docker compose logs -f app         # Watch app logs
docker compose restart app         # Restart app only
```

---

### Topic 5: bin/magento CLI & Logging

**Essential Commands:**

```bash
# Module management
bin/magento module:enable Vendor_Module
bin/magento module:disable Vendor_Module
bin/magento module:status

# Cache
bin/magento cache:enable
bin/magento cache:disable
bin/magento cache:flush           # Clear all cache
bin/magento cache:clean           # Clean cache types

# DI compilation
bin/magento setup:di:compile     # Generate factories, proxies
bin/magento setup:upgrade        # Run DB upgrades + regenerate

# Indexer
bin/magento indexer:reindex
bin/magento indexer:status
```

**Reading Logs:**

```bash
# Watch system log in real-time
tail -f var/log/system.log

# Watch exceptions
tail -f var/log/exception.log
```

**Writing Custom Logs:**

```php
<?php
use Psr\Log\LoggerInterface;

class MyClass
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function doSomething(int $id): void
    {
        $this->logger->info('Processing review', ['review_id' => $id]);
        try {
            // ... work
        } catch (\Exception $e) {
            $this->logger->error('Failed to process review', [
                'review_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
```

Log appears in `var/log/training_helloworld.log` (auto-generated from namespace).

### Topic 6: Xdebug Setup for Debugging

**Why Xdebug?** Allows step-by-step debugging, breakpoints, variable inspection.

**Enable Xdebug in Docker:**

```bash
# In docker-compose.yml, add to app service:
environment:
  - XDEBUG_MODE=develop,debug
  - XDEBUG_CLIENT_HOST=host.docker.internal
  - XDEBUG_START_WITH_REQUEST=1
```

**PHPStorm Configuration:**

1. PHP → Servers: Add new (Name: magento2, Host: localhost, Port: 80, Debugger: Xdebug)
2. Use path mappings: `/var/www/html` → your local `src/` directory
3. Enable "Can accept external connections"

**Debug Commands:**

```bash
# Enable Xdebug
docker compose exec app php -d xdebug.mode=develop,debug -r "..."

# Or use browser extension: Xdebug Helper
# Add ?XDEBUG_SESSION=1 to URL to start debugging
```

---

### Topic 7: Composer Basics

**What is Composer?** PHP's dependency manager. Magento uses it to manage modules and libraries.

**Key Commands:**

```bash
composer install      # Install dependencies from composer.lock
composer update       # Update to latest versions (may break things!)
composer require vendor/package  # Add new package
composer show         # List installed packages
composer dump-autoload  # Regenerate autoload files
```

**composer.json Structure:**

```json
{
    "name": "vendor/module-name",
    "type": "magento2-module",
    "require": {
        "php": ">=8.1",
        "magento/framework": "103.0.*"
    },
    "autoload": {
        "files": ["registration.php"],
        "psr-4": {"Vendor\\Module\\": ""}
    }
}
```

**Important:** Never run `composer update` in production. It may update dependencies and break compatibility.

---

## Reading List

- [Magento 2 Architecture](https://developer.adobe.com/commerce/php/architecture/) — Directory structure, module layout
- [Magento 2 Development](https://developer.adobe.com/commerce/php/development/) — Request flow, dependency injection
- [Docker Get Started](https://docs.docker.com/get-started/) — Containers, networking, volumes

---

## Edge Cases & Troubleshooting

| Issue | Symptom | Solution |
|-------|---------|----------|
| Module not showing | `module:status` doesn't list it | Check `registration.php` spelling and path |
| Route 404 | Page returns 404 | Check `routes.xml` frontName matches controller |
| Blank page | White screen | Check `var/log/exception.log` |
| Permission denied | Cannot write to var/ | `chmod -R 777 var generated` |
| Docker not starting | Port 8080 already in use | Stop other services using port 8080 |
| MySQL connection fail | Cannot connect to DB | Docker services running? `docker compose ps` |
| Cache stale | Old content showing | `bin/magento cache:flush` |

---

## Common Mistakes to Avoid

1. ❌ Forgetting `bin/magento setup:upgrade` after creating a new module → Module doesn't load
2. ❌ Skipping `bin/magento cache:flush` after changes → Old content persists
3. ❌ Editing files outside `app/code/` → Changes lost on `composer install`
4. ❌ Using production mode during development → Slow, can't override templates
5. ❌ Committing `env.php` → Contains secrets, never commit this file

---

*Week 1 of Magento 2 Zero to Hero Training Program*  
*For: Interns*  
*Language: English*
