# Magento 2 Zero to Hero — Intern Training Program

## Backend Developer Track

**8 Weeks | Junior Developer Level | PHP/Magento 2.4+ Required**

---

## What This Course Is

A **backend-focused** Magento 2 internship that takes you from zero to production-ready developer. You will master module development, data layer, customization, Admin UI, APIs, data operations, performance, and deployment.

**Out of scope:** Storefront frontend (themes, LESS, Knockout.js storefront components, jQuery widgets).

---

## Program Overview

| Attribute | Details |
|-----------|---------|
| **Duration** | 8 Weeks |
| **Prerequisites** | Basic PHP OOP, HTML, CSS, Git, SQL |
| **Target Level** | Junior Developer |
| **Final Goal** | Build production-ready Magento 2 modules |
| **Philosophy** | Mental model first, hands-on second |

---

## 8-Week Curriculum

| Week | Topic | Doc |
|------|-------|-----|
| 1 | Development Environment, Docker, Magento Architecture, Hello World Module | [📚](docs/week-01-dev-environment/README.md) |
| 2 | Controllers, Blocks, Templates, Layout XML, PHPCS | [📚](docs/week-02-controllers-routing/README.md) |
| 3 | Data Layer — Models, db_schema, Service Contracts, Repositories | [📚](docs/week-03-data-layer/README.md) |
| 4 | Plugins, Observers, Events, di.xml, Preferences | [📚](docs/week-04-plugins-observers/README.md) |
| 5 | Admin UI — Routes, Menus, Config, ACL, UI Grids | [📚](docs/week-05-admin-ui/README.md) |
| 6 | REST API, GraphQL, Webhooks, External APIs | [📚](docs/week-06-apis/README.md) |
| 7 | Import/Export, Indexing, Cron, Async Queue | [📚](docs/week-07-data-ops/README.md) |
| 8 | Caching, Indexing, Profiling, Deployment, Capstone | [📚](docs/week-08-performance/README.md) |

---

## Progress Gates

> ⚠️ Interns **MUST** pass each week's DoD before advancing to the next week.

| Gate | Requirement |
|------|-------------|
| Week 1 → Week 2 | Docker + Xdebug working, Magento installed |
| Week 2 → Week 3 | Working route module, PHPCS zero errors |
| Week 3 → Week 4 | Custom table via db_schema, Repository working |
| Week 4 → Week 5 | Plugin + Observer implemented |
| Week 5 → Week 6 | Admin route + menu + ACL + Grid working |
| Week 6 → Week 7 | REST endpoint + API integration working |
| Week 7 → Week 8 | Full module capstone started |
| Week 8 | Capstone module complete |

---

## Optional Post-Course Modules

| Module | Description |
|--------|-------------|
| [Email Templates](docs/OPTIONAL-email-templates/README.md) | Transactional emails, CSS inlining |
| [Module Packaging](docs/OPTIONAL-module-packaging/README.md) | composer.json, SemVer, CI/CD |
| [Unit Testing](docs/OPTIONAL-unit-testing/README.md) | PHPUnit, integration tests |
| [Sales & Orders](docs/OPTIONAL-sales-orders/README.md) | Order state machine, invoice, shipment, credit memo, programmatically create orders |
| [Checkout](docs/OPTIONAL-checkout/README.md) | Quote lifecycle, shipping/payment methods, totals collectors, order placement flow |
| [Advanced Performance](docs/OPTIONAL-advanced-performance/README.md) | Varnish, Elasticsearch, Redis cluster, profiling with Tideways/XHGui |
| [Customer Account](docs/OPTIONAL-customer-account/README.md) | Customer EAV attributes, registration, address management, groups, security |

---

## Getting Started

### Quick Setup

```bash
# 1. Clone the repository
git clone <repo-url> magento-training

# 2. Navigate to the course
cd magento-training/courses/magento2-zero-to-hero

# 3. Start Docker environment
cd docker && docker compose up -d --build

# 4. Wait for healthy services (30-60 seconds)
docker compose ps

# 5. Configure Magento auth keys (see docker/README.md)
# Then run composer install inside container

# 6. Install Magento
docker compose exec app php bin/magento setup:install ...
```

See [docker/README.md](docker/README.md) for complete setup instructions.

---

## Required Reading

- [Docker Documentation](https://docs.docker.com/get-started/) — containers, networking, volumes
- [Magento 2 Architecture](https://developer.adobe.com/commerce/php/architecture/)
- [Magento 2 Development](https://developer.adobe.com/commerce/php/development/)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
- [Magento Coding Standard](https://github.com/magento/magento-coding-standard)

---

*Last Updated: 2026-04-15*
