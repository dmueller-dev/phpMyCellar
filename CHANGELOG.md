# Changelog

All notable changes to **phpMyCellar** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Flexible WSET SAT operational modes (`public`, `logged_in`, `backend_only`, and `disabled`) in Site Settings and Installation Wizard.
- Support for internal backend-only WSET data entry for personal cellar tracking without public display.
- Configurable WSET display format (`standard` vs. `detailed` BLIC criteria breakdown) on tasting notes.
- Private indicator badge on tasting notes for ratings displayed under `logged_in` mode.
- Transparent auto-migration converting legacy `wset_enabled` setting to `wset_mode` on upgrade.
- Comprehensive `UPGRADE.md` migration guide detailing deprecations, database migrations, and upcoming breaking changes in v2.0.0.

### Changed
- Retired legacy `wset_enabled` setting from active codebase and seed database schema.
- Enhanced `buildKeywordsList()` with flexible arguments (supporting strings, arrays, or nested lists), HTML tag stripping, whitespace normalization, surrounding quote trimming, and robust case-insensitive deduplication.
- Modernized tasting note rating display in `backend/addBlogpost.php` to use scale-aware `formatNoteRatingBadge()`.

### Deprecated
- Helper `isWsetSATEnabled()` is deprecated in favor of `isWsetSATEntryEnabled()` and `isWsetSATVisibleToViewer()`. Scheduled for removal in v2.0.0.
- Legacy `site_settings.wset_enabled` setting key is deprecated in favor of `wset_mode`. It is automatically migrated on read and will be removed in v2.0.0.
- Standalone redirect stubs `tnote.php`, `wine.php`, and `blogpost.php` are deprecated in favor of direct requests to `tnotes.php`, `wines.php`, and `blog.php`. Scheduled for removal in v2.0.0.
- Legacy rating column alias `dmpts` and aggregate column `avg_dmpts` in SQL views (`view_vintage_region_colour_stats`, `view_vintage_top_wines`) and PHP helper query results are deprecated in favor of `pts_20` and `avg_pts_20`. Scheduled for removal in v2.0.0.
- Legacy backend form parameter `$_POST['dmpts']` is deprecated in favor of `pts_20` across tasting note editors. Scheduled for removal in v2.0.0.

### Fixed
- Fixed data preservation issue in `editTastingNote.php` where editing a note while WSET was disabled globally would overwrite previously recorded WSET criteria with `NULL`.
- Fixed duplicate meta keywords across all site pages by universally routing keyword resolution in `header.php` through `buildKeywordsList()`, eliminating double occurrences when site title and owner name are identical (e.g. on `index.php`, `impressum.php`, `privacy.php`).
- Added dedicated, deduplicated meta keywords for legal notice and policy pages (`impressum.php` and `privacy.php`).

## [1.0.1] - 2026-09-01

### Added
- Configurable primary rating scale system (20-point vs. 100-point) in Site Settings and Installation Wizard.
- Optional WSET Systematic Approach to Tasting (SAT) qualitative assessment toggle.
- Database CHECK constraint ensuring 100-point scores remain within 50–100.
- Reference lookup tables `scale_20` and `scale_100` with score tier descriptions.
- Scale-aware vintage statistics, regional averages, and rankings.

### Changed
- Standardized rating column name from legacy `dmpts` to `pts_20`.
- Generalized 20-point score descriptions across lookup tables and views.
- Updated documentation and user manual for rating scale options.

### Fixed
- Removed redundant `chk_pts_20` constraint from `install/schema.sql`.

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
