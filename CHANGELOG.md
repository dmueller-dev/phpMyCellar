# Changelog

All notable changes to **phpMyCellar** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

## [1.0.0] - 2026-09-01

### Added
- **Interactive Installation Wizard (`install/index.php`)**:
  - Prerequisite and PHP extension checks (`mysqli`, `mbstring`, `session`, `json`, `fileinfo`, `gd`/`imagick`).
  - Directory write permission validation.
  - Interactive database installer with automatic schema and seed execution.
  - Primary administrator account creation (`user_id = 1`) with `password_hash()`.
  - Initial site branding, currency, and rating scale configuration.
  - Automatic `.env` generation and `install/installed.lock` installer security protection.
- **Dynamic Site Branding & Theme Customisation**:
  - Database-driven `site_settings` for site title, tagline, base URL, owner contact, and currency symbol.
  - Real-time CSS accent colour customisation with primary, secondary, and hover states.
  - Editable static pages (`welcome`, `about`, `ratingscale`, `impressum`, `privacy`) managed via the backend.
- **Dynamic Role-Based Access Control (RBAC)**:
  - Role management for Public, Read, Write, and Admin users.
  - Granular privilege definition matrix and user-level privilege overrides.
- **Security Hardening**:
  - Synchronizer CSRF tokens across all state-changing backend forms and actions.
  - Hardened upload architecture in `uploads/` with `.htaccess` script execution prevention and MIME validation.
  - Public images relocated to `uploads/img/` with search crawler allow directives in `robots.txt`.
  - Comprehensive `SECURITY.md` vulnerability disclosure policy.
- **Deployment & Server Portability**:
  - Sample Apache `.htaccess.example` with directory protection, security headers, and asset caching.
  - Sample `nginx.conf.example` server block configuration for PHP-FPM.
  - Production-ready `Dockerfile` (PHP 8.2 Apache) and `docker-compose.yml` local orchestration stack.
- **Documentation**:
  - Complete `README.md` revamp with architecture diagrams and feature tables.
  - Modular User & Administrator Manual in `manual/` (01 to 08).
