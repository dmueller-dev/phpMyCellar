# Upgrade & Migration Guide

This guide provides instructions for upgrading **phpMyCellar** between releases, documents deprecated features, and prepares administrators and developers for breaking changes scheduled for version **2.0.0**.

---

## Table of Contents

1. [Versioning & Deprecation Policy](#versioning--deprecation-policy)
2. [Upcoming Breaking Changes in v2.0.0](#upcoming-breaking-changes-in-v200)
3. [Feature & Component Migration Details](#feature--component-migration-details)
   - [1. WSET SAT Operational Mode (`wset_enabled` → `wset_mode`)](#1-wset-sat-operational-mode-wset_enabled--wset_mode)
   - [2. Rating Scale Column Standardisation (`dmpts` → `pts_20`)](#2-rating-scale-column-standardisation-dmpts--pts_20)
   - [3. Legacy Singular URL Stubs (`tnote.php`, `wine.php`, `blogpost.php`)](#3-legacy-singular-url-stubs-tnotephp-winephp-blogpostphp)
   - [4. Helper Functions & API Query Result Keys](#4-helper-functions--api-query-result-keys)
4. [Step-by-Step Version Upgrade Instructions](#step-by-step-version-upgrade-instructions)
   - [Upgrading to 1.1.0 (Unreleased)](#upgrading-to-110-unreleased)
   - [Upgrading to 1.0.1](#upgrading-to-101)
   - [Upgrading to 1.0.0](#upgrading-to-100)

---

## Versioning & Deprecation Policy

phpMyCellar strictly adheres to [Semantic Versioning (SemVer 2.0.0)](https://semver.org/):

* **Patch releases (`1.0.x`)**: Bug fixes and security patches. No backward-incompatible changes or new features.
* **Minor releases (`1.x.0`)**: New features, non-breaking schema additions, and formal deprecation notices. Existing APIs, configuration keys, and URL endpoints continue to function with transparent fallbacks and silenced runtime notices (`E_USER_DEPRECATED`).
* **Major releases (`2.0.0`)**: Breaking changes, removal of deprecated functions, dropping of legacy database columns/aliases, and deletion of compatibility redirect stubs.

### How Deprecations are Handled in 1.x
1. **In Code**: Obsolete functions and stubs are flagged with PHPDoc `@deprecated <version> Scheduled for removal in v2.0.0`, `@see <replacement>`, and trigger `@trigger_error(..., E_USER_DEPRECATED)`.
2. **In CHANGELOG.md**: Listed under the dedicated `### Deprecated` section following [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
3. **In UPGRADE.md**: Detailed here with migration instructions and database DDL snippets.
4. **Transparent Fallbacks**: The application automatically translates legacy settings (e.g. `wset_enabled` &rarr; `wset_mode`), accepts legacy POST parameters, and aliases database query columns so existing setups do not break.

---

## Upcoming Breaking Changes in v2.0.0

The following table summarizes all components deprecated in the `1.x` series that are **scheduled for permanent removal in version 2.0.0**:

| Deprecated Item | Type | Deprecated In | Removal In | Replacement / Action |
| :--- | :--- | :--- | :--- | :--- |
| `isWsetSATEnabled()` | PHP Function | `1.1.0` | `2.0.0` | Replace with `isWsetSATEntryEnabled()` or `isWsetSATVisibleToViewer()` |
| `site_settings.wset_enabled` | DB Setting | `1.1.0` | `2.0.0` | Replaced by `site_settings.wset_mode` (`public`, `logged_in`, `backend_only`, `disabled`) |
| `tnote.php` | Page / File | `1.0.0` | `2.0.0` | Use `tnotes.php` directly; configure web server rewrite if external links exist |
| `wine.php` | Page / File | `1.0.0` | `2.0.0` | Use `wines.php` directly; configure web server rewrite if external links exist |
| `blogpost.php` | Page / File | `1.0.0` | `2.0.0` | Use `blog.php` directly; configure web server rewrite if external links exist |
| `dmpts` (view column) | SQL View Alias | `1.0.1` | `2.0.0` | Use `pts_20` column in `view_vintage_top_wines` and query results |
| `avg_dmpts` (view column) | SQL View Alias | `1.0.1` | `2.0.0` | Use `avg_pts_20` or `avg_score` in `view_vintage_region_colour_stats` |
| `$_POST['dmpts']` | Form Input | `1.0.1` | `2.0.0` | Submit rating using `$_POST['pts_20']` in tasting note forms |
| `$row['dmpts']` / `$row['avg_dmpts']` | Array Keys | `1.0.1` | `2.0.0` | Update custom themes or scripts to use `$row['pts_20']` / `$row['avg_score']` |

---

## Feature & Component Migration Details

### 1. WSET SAT Operational Mode (`wset_enabled` → `wset_mode`)

In phpMyCellar 1.1.0, the binary toggle `wset_enabled` (`0` or `1`) has been replaced by a flexible 4-state operational mode `wset_mode`:
* `public`: WSET SAT criteria and overall scores are visible to all visitors and editable in backend.
* `logged_in`: WSET scores are only visible to authenticated users; public visitors see only primary ratings.
* `backend_only`: WSET data can be recorded in the cellar backend for personal tracking, but is never displayed on the public frontend.
* `disabled`: WSET evaluation is completely turned off throughout the application.

#### Transparent Auto-Migration
When loading site settings, `getWsetSATMode()` automatically converts any existing `wset_enabled` record to `wset_mode` and deletes the old key.

#### Manual Database Migration (for DBAs)
```sql
-- Convert legacy wset_enabled to wset_mode if present
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_group`)
SELECT 'wset_mode', 
       CASE 
         WHEN `setting_value` IN ('0', 'no', 'false') THEN 'disabled'
         ELSE 'public'
       END,
       'general'
FROM `site_settings`
WHERE `setting_key` = 'wset_enabled'
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

DELETE FROM `site_settings` WHERE `setting_key` = 'wset_enabled';
```

---

### 2. Rating Scale Column Standardisation (`dmpts` → `pts_20`)

In version 1.0.1, phpMyCellar introduced support for dual rating scales (20-point vs. 100-point). The original column `dmpts` in table `tnotes` was renamed to `pts_20` to reflect its specific scale.

#### Upgrading pre-1.0.1 Database Installations
If upgrading a database from before version 1.0.1:
```sql
-- 1. Rename column dmpts to pts_20 in tnotes
ALTER TABLE `tnotes` CHANGE `dmpts` `pts_20` TINYINT(2) UNSIGNED DEFAULT NULL;

-- 2. Add pts_100 column if missing
ALTER TABLE `tnotes` ADD COLUMN `pts_100` TINYINT(3) UNSIGNED DEFAULT NULL AFTER `pts_20`;
ALTER TABLE `tnotes` ADD CONSTRAINT `chk_pts_100` CHECK (`pts_100` IS NULL OR (`pts_100` >= 50 AND `pts_100` <= 100));

-- 3. Update SQL Views to include both pts_20 and backward-compatible dmpts aliases
DROP VIEW IF EXISTS `view_vintage_region_colour_stats`;
CREATE VIEW `view_vintage_region_colour_stats` AS
SELECT
  `wines`.`vintage` AS `vintage`,
  `regions`.`country` AS `country`,
  `regions`.`region` AS `region`,
  `wines_master`.`region_id` AS `region_id`,
  `wines_master`.`colour` AS `colour`,
  CONCAT(`regions`.`country`, ': ', `regions`.`region`, ' (', `wines_master`.`colour`, ')') AS `country_region_colour`,
  COUNT(`tnotes`.`note_id`) AS `note_count`,
  ROUND(AVG(CASE WHEN `tnotes`.`flawed_yn` = 'no' AND `tnotes`.`pts_20` IS NOT NULL THEN `tnotes`.`pts_20` END), 1) AS `avg_pts_20`,
  ROUND(AVG(CASE WHEN `tnotes`.`flawed_yn` = 'no' AND `tnotes`.`pts_20` IS NOT NULL THEN `tnotes`.`pts_20` END), 1) AS `avg_dmpts`,
  ROUND(AVG(CASE WHEN `tnotes`.`flawed_yn` = 'no' AND `tnotes`.`pts_100` IS NOT NULL THEN `tnotes`.`pts_100` END), 1) AS `avg_pts_100`,
  `xvr`.`vintage_desc` AS `vintage_desc`
FROM `tnotes`
JOIN `wines` ON `tnotes`.`wine_id` = `wines`.`wine_id`
JOIN `wines_master` ON `wines`.`master_id` = `wines_master`.`master_id`
JOIN `regions` ON `wines_master`.`region_id` = `regions`.`region_id`
LEFT JOIN `x_vintage_region` `xvr` ON `wines`.`vintage` = `xvr`.`vintage` AND `wines_master`.`region_id` = `xvr`.`region_id`
WHERE `tnotes`.`status` = 'published'
GROUP BY `wines`.`vintage`, `regions`.`country`, `regions`.`region`, `wines_master`.`region_id`, `wines_master`.`colour`, `xvr`.`vintage_desc`;

DROP VIEW IF EXISTS `view_vintage_top_wines`;
CREATE VIEW `view_vintage_top_wines` AS
SELECT
  `tnotes`.`note_id` AS `note_id`,
  `tnotes`.`wine_id` AS `wine_id`,
  `tnotes`.`user_id` AS `user_id`,
  `tnotes`.`tasting_date` AS `tasting_date`,
  `tnotes`.`pts_20` AS `pts_20`,
  `tnotes`.`pts_20` AS `dmpts`,
  `tnotes`.`pts_100` AS `pts_100`,
  `tnotes`.`flawed_yn` AS `flawed_yn`,
  `tnotes`.`favourite` AS `favourite`,
  `tnotes`.`status` AS `status`,
  `users`.`initials` AS `initials`,
  `wines`.`vintage` AS `vintage`,
  `wines_master`.`master_id` AS `master_id`,
  `wines_master`.`name` AS `name`,
  `wines_master`.`nameconvention` AS `nameconvention`,
  `wines_master`.`grape` AS `grape`,
  `wines_master`.`colour` AS `colour`,
  `wines_master`.`style` AS `style`,
  `producers`.`producer_id` AS `producer_id`,
  `producers`.`producer` AS `producer`,
  `vineyards`.`vineyard_id` AS `vineyard_id`,
  `vineyards`.`vineyard` AS `vineyard`,
  `regions`.`region_id` AS `region_id`,
  `regions`.`region` AS `region`,
  `countries`.`country` AS `country`,
  `appellations`.`appellation_id` AS `appellation_id`,
  `appellations`.`appellation` AS `appellation`
FROM `tnotes`
JOIN `users` ON `tnotes`.`user_id` = `users`.`user_id`
JOIN `wines` ON `tnotes`.`wine_id` = `wines`.`wine_id`
JOIN `wines_master` ON `wines`.`master_id` = `wines_master`.`master_id`
JOIN `producers` ON `wines_master`.`producer_id` = `producers`.`producer_id`
LEFT JOIN `vineyards` ON `wines_master`.`vineyard_id` = `vineyards`.`vineyard_id`
JOIN `regions` ON `wines_master`.`region_id` = `regions`.`region_id`
JOIN `countries` ON `regions`.`country` = `countries`.`country`
LEFT JOIN `appellations` ON `wines_master`.`appellation_id` = `appellations`.`appellation_id`
WHERE `tnotes`.`status` = 'published' AND `tnotes`.`flawed_yn` = 'no';
```

---

### 3. Legacy Singular URL Stubs (`tnote.php`, `wine.php`, `blogpost.php`)

In earlier iterations, individual detail pages were accessed via `tnote.php?note_id=X`, `wine.php?wine_id=Y`, and `blogpost.php?blog_id=Z`. These have been consolidated into the pluralized controllers:
* `tnote.php` &rarr; `tnotes.php`
* `wine.php` &rarr; `wines.php`
* `blogpost.php` &rarr; `blog.php`

The stub files currently issue an HTTP 301 Permanent Redirect preserving query parameters. In **v2.0.0**, these PHP files will be removed.

#### Transitioning to Web Server Rewrites
If you receive external traffic or backlinks to the old URLs, configure redirects in your web server so removal in v2.0.0 will not impact visitors.

**Apache (`.htaccess`)**:
```apache
<IfModule mod_rewrite.c>
    RewriteRule ^tnote\.php$ /tnotes.php [R=301,L,QSA]
    RewriteRule ^wine\.php$ /wines.php [R=301,L,QSA]
    RewriteRule ^blogpost\.php$ /blog.php [R=301,L,QSA]
</IfModule>
```

**Nginx**:
```nginx
location = /tnote.php {
    return 301 /tnotes.php$is_args$args;
}
location = /wine.php {
    return 301 /wines.php$is_args$args;
}
location = /blogpost.php {
    return 301 /blog.php$is_args$args;
}
```

---

### 4. Helper Functions & API Query Result Keys

#### Replacing `isWsetSATEnabled()`
```php
// BEFORE (Deprecated in 1.1.0, removal in 2.0.0):
if (isWsetSATEnabled()) { ... }

// AFTER - For backend data entry forms:
if (isWsetSATEntryEnabled()) { ... }

// AFTER - For frontend visitor display:
if (isWsetSATVisibleToViewer()) { ... }
```

#### Replacing Array Keys in Custom Scripts
```php
// BEFORE:
$score = $row['dmpts'];
$avg = $vintageStats['avg_dmpts'];

// AFTER:
$score = $row['pts_20'];
$avg = $vintageStats['avg_score'] ?? $vintageStats['avg_pts_20'];
```

---

## Step-by-Step Version Upgrade Instructions

### Upgrading to 1.1.0 (Unreleased)
1. **Pull the latest codebase**:
   ```bash
   git pull origin main
   ```
2. **Review Site Settings**:
   - Navigate to **Backend > Site Settings**.
   - Review the new **WSET SAT Operational Mode** dropdown (`public`, `logged_in`, `backend_only`, `disabled`).
   - Choose your preferred **WSET Display Format** (`standard` vs `detailed`).
   - Save settings to execute the automatic migration from `wset_enabled` to `wset_mode`.
3. **Verify Error Logs**:
   - Check your PHP error logs to confirm no custom code or external calls are relying on deprecated helpers or query keys.

### Upgrading to 1.0.1
1. **Apply Schema Updates**:
   - Ensure the `scale_20` and `scale_100` reference lookup tables are present.
   - Run the column rename from `dmpts` to `pts_20` if upgrading from a pre-1.0.1 database.
2. **Review Primary Rating Scale**:
   - Navigate to **Backend > Site Settings** and confirm whether your cellar uses the `20-point` or `100-point` scale.

### Upgrading to 1.0.0
1. Follow the interactive installation wizard at `/install/index.php` or import `install/schema.sql` followed by `install/seed.sql`.
2. Configure `.env` with database credentials.
3. Ensure write permissions for `uploads/` and `install/installed.lock`.
