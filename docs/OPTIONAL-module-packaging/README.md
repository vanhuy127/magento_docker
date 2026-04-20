# Module: Module Packaging & Release

**Duration:** 2 Days  
**Philosophy:** Take your module from working code to production-ready release

---

## Overview

You've built a module — now learn how to package it properly for distribution via Composer, prepare it for the Magento Marketplace, and manage versions professionally.

---

## Prerequisites

- [ ] Week 4 completed (module structure, di.xml, service contracts)
- [ ] Working module with Service Contracts
- [ ] Git repo set up

---

## Learning Objectives

By end of this module, you will be able to:

- [ ] Write a proper `composer.json` for a Magento module
- [ ] Configure autoload for PSR-4 and file mappings
- [ ] Apply semantic versioning to module releases
- [ ] Prepare a module for Magento Marketplace submission
- [ ] Write a README with installation instructions
- [ ] Set up basic CI/CD for module testing

---

## Day 1: Composer Packaging

### Content

#### 1.1 Module composer.json Structure

```json
{
    "name": "vendor/module-name",
    "description": "Short description of what the module does",
    "type": "magento2-module",
    "license": [
        "OSL-3.0",
        "AFL-3.0"
    ],
    "autoload": {
        "files": [
            "registration.php"
        ],
        "psr-4": {
            "Vendor\\ModuleName\\": ""
        }
    },
    "require": {
        "php": ">=8.1",
        "magento/framework": "103.0.*"
    },
    "require-dev": {
        "magento/magento-coding-standard": "*"
    },
    "scripts": {
        "post-install-cmd": [
            "Magento\\CodingStandard\\Hook\\Handler\\PostInstall::run"
        ],
        "test": "phpcs --standard=Magento2 ./src"
    }
}
```

#### 1.2 Key Fields Explained

| Field | Required | Purpose |
|-------|----------|---------|
| `name` | Yes | `vendor/module-name` — must be unique on Packagist |
| `type` | Yes | Must be `magento2-module` for Magento to recognize |
| `description` | Yes | Short clear description |
| `license` | Yes | OSL-3.0 or AFL-3.0 for Magento modules |
| `autoload.psr-4` | Yes | PSR-4 namespace mapping |
| `require` | Yes | PHP version and Magento dependencies |

#### 1.3 Marketplace Requirements

- [ ] README with: description, installation steps, screenshots, changelog
- [ ] `composer.json` must declare correct version constraints
- [ ] Module must not override core files (use plugins/observers)
- [ ] Must pass Marketplace Technical Review (PHPCS, security scan)

### Exercise 1.1: Write composer.json

Convert your training module into a properly packaged Composer module.

---

## Day 2: Versioning, Release & CI/CD

### Content

#### 2.1 Semantic Versioning (SemVer)

Given a version number `MAJOR.MINOR.PATCH`:

| Part | Increment when | Example |
|------|---------------|---------|
| **MAJOR** | Breaking changes to API | 1.0.0 → 2.0.0 |
| **MINOR** | New functionality (backward compatible) | 1.0.0 → 1.1.0 |
| **PATCH** | Bug fixes (backward compatible) | 1.0.0 → 1.0.1 |

**Magento-specific rules:**
- Always match `major.minor` with Magento framework version (e.g., `103.0.*` for Magento 2.4.6)
- Never break Service Contracts in MINOR updates

#### 2.2 Release Workflow

```bash
# 1. Create release branch
git checkout -b release/1.1.0

# 2. Update composer.json version
# 3. Update CHANGELOG.md
git add CHANGELOG.md composer.json
git commit -m "Release 1.1.0"

# 4. Tag
git tag -a 1.1.0 -m "Release 1.1.0"
git push origin 1.1.0

# 5. Create GitHub Release
gh release create 1.1.0 --title "Release 1.1.0" --notes "See CHANGELOG"
```

#### 2.3 Simple CI Pipeline

```yaml
# .github/workflows/ci.yml
name: CI

on: [push, pull_request]

jobs:
  phpcs:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Install Composer
        run: composer install
      - name: Run PHPCS
        run: vendor/bin/phpcs --standard=Magento2 app/code/Vendor/Module
```

### Exercise 2.1: Release Your Module

1. Write proper composer.json
2. Tag version 1.0.0
3. Push to GitHub
4. Create a GitHub Release

---

## Module Checklist

Before releasing your module, verify:

- [ ] `composer.json` is valid (`composer validate`)
- [ ] `registration.php` present and correct
- [ ] `etc/module.xml` has correct version
- [ ] CHANGELOG.md updated
- [ ] README has installation instructions
- [ ] PHPCS passes zero errors
- [ ] No hardcoded values (use di.xml / config.yaml)
- [ ] Service Contracts defined in `Api/` folder
- [ ] Tests written (if applicable)

---

## Reference Links

- [Composer.json schema](https://getcomposer.org/doc/04-schema.md)
- [Magento Marketplace](https://marketplace.magento.com/)
- [Semantic Versioning](https://semver.org/)
- [Magento coding standard](https://github.com/magento/magento-coding-standard)

---

*Module: Packaging & Release — Magento 2 Zero to Hero Training Program*
