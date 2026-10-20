# Changelog

All notable changes to **phpMyCellar** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Cross-device mobile layout transformations across all administrative management pages (`backend/manage*.php`):
  - Responsive card transformation for tables on viewports &le; 720px (`.admin-table-container`), converting tabular rows into touch-friendly cards with `data-label` keys, prominent primary item headers, and &ge; 44 &times; 44 px action targets.
  - Mobile segmented pane switchers (`.admin-mobile-pane-switch` and `switchAdminMobilePane()`) for dual-grid administration pages (terroir, cuvées, storage bins, grape varieties, formats, stores, attributes, vintages, and rating scales), allowing seamless switching between directory tables and add/edit forms without vertical scroll fatigue.
  - Horizontal momentum scrolling navigation tabs (`.admin-mode-tabs`, `.tab-nav`) preventing button wrapping or clipping on narrow screens.
  - Zero desktop layout regressions: all responsive rules strictly encapsulated within `@media screen and (max-width: 720px)`.
- Unified administrative dashboards in `/backend/` with cross-device responsive design, horizontal table scroll containment, touch-friendly targets, and dual Add/Edit modes:
  - `backend/manageBottles.php` (consolidates `addBottle.php` and `editBottle.php` with batch 1–12 bottle additions, bin capacity validation, and quick inventory ledger).
  - `backend/manageWines.php` (consolidates `addWine.php` and `editWine.php` with harvest vintage management and CellarTracker ID linking).
  - `backend/manageWineMasters.php` (consolidates `addWineMaster.php` and `editWineMaster.php` with live naming convention preview).
  - `backend/manageTastingNotes.php` (consolidates `addTastingNote.php`, `blindTasting.php`, and `editTastingNote.php` with standard and blind tasting modes, WSET SAT BLIC evaluation, bottle consumption prompts, and notes journal).
  - `backend/manageBlogposts.php` (consolidates `addBlogpost.php` and `editBlogpost.php` with editorial story authoring and wine/tasting note associations; aliased by `backend/manageStories.php`).
  - `backend/manageUsers.php` (consolidates `addUser.php` and `editUser.php` with user account creation, password reset generator, and RBAC role assignments).
  - `backend/manageCountries.php`, `backend/manageRegions.php`, `backend/manageSubregions.php`, `backend/manageAppellations.php`, `backend/manageVineyards.php`, and `backend/manageProducers.php` (terroir and geography management).
- Interactive client-side search filter boxes for selector dropdowns across administrative forms in `/backend/manage*.php` (including wine master selection when adding vintages, wine cuvée selection when editing bottles, user accounts, editorial stories, tasting notes, and terroir region and appellation dropdowns).
- Interactive WSET SAT Systematic Approach to Tasting rating recommendation card in `backend/manageTastingNotes.php`, calculating composite BLIC scores (0.0 to 4.0), mapping qualitative assessment tiers, offering suggested 100-point and 20-point scoring ranges, and providing an instant score population button.
- Absorbed purchase order creation, line-item calculator, and invoice uploads directly into `backend/manageOrders.php` (consolidates `addOrder.php`).
- Absorbed in-page static text editing directly into `backend/manageStaticPages.php` (consolidates `editStaticPage.php`).
- Responsive admin layout utility classes in `includes/styles.css` (`.admin-dual-manage-grid`, `.form-row-2col`, `.form-row-3col`, `.admin-table-container`, `.admin-mode-tabs`).
- Array privilege mapping support in `includes/init.php` script gatekeeper for granular RBAC across unified dual-mode scripts.
- Optional maximum capacity column (`max_capacity`) in table `storageBins` to configure bottle limits per storage bin.
- Dedicated cellar and storage bin management interface (`backend/manageStorageBins.php`) to create and edit cellars, storage bins, and maximum capacities.
- Cellar and storage bin capacity utilisation percentage indicators and progress bars in the Admin Hub storage widget (`backend/index.php`).
- Dedicated wine merchant and store management interface (`backend/manageStores.php`) with full CRUD operations, inventory usage tracking, and safe deletion checks.
- Grape variety catalogue interface (`backend/manageVarieties.php`) supporting creation, editing, and deletion of c&eacute;pages with wine count verification.
- Bottle format and standard volume management interface (`backend/manageFormats.php`) for managing bottle formats and capacities in millilitres.
- Harvest vintage and regional character report management interface (`backend/manageVintages.php`) for managing vintage years, regional vintage descriptions, and tasting summaries across wine regions.
- Wine attributes and classification management interface (`backend/manageWineAttributes.php`) for wine styles, colours, and display naming templates.
- Rating scales management interface (`backend/manageRatingScales.php`) for customising qualitative evaluation descriptors across 20-point, 100-point, and WSET scoring methodologies.
- Centralised comment moderation interface (`backend/manageComments.php`) for reviewing, editing, approving, and removing reader discussions across tasting notes and stories.
- Cellar editing and safe deletion functionality with zero-bin validation in `backend/manageStorageBins.php`.
- Eight new granular RBAC privileges: `manage_stores`, `manage_formats`, `manage_varieties`, `manage_vintages`, `manage_wine_attributes`, `manage_rating_scales`, `manage_static_pages`, and `moderate_comments`.
- Optional site setting (`show_storage_usage_pct`) in Site Settings (`backend/settings.php`) to toggle cellar utilisation percentage indicators in the Admin Hub.
- Dynamic storage location capacity annotations in bottle creation, editing, and order delivery dropdown menus.
- Role-based privilege `manage_storage_bins` for configuring storage locations and capacities.
- `restricted` boolean flag on the `bottles` table to mark private reserve or restricted-allocation bottles.
- Bottle restriction selector (`Restricted / Private reserve?`) in bottle management forms (`backend/addBottle.php` and `backend/editBottle.php`).
- Visual padlock indicator on `backend/browseBottles.php` for restricted inventory.
- Subtle status indicators on the Carte des vins (`winemenu.php`) vintage chips and popovers: padlock micro-icon for restricted bottles, clock micro-icon for bottles whose drinking window has not yet opened, and urgent alarm clock micro-icon for bottles that have passed their estimated drinking window.
- New Legend card in the sidebar of `winemenu.php` explaining all status micro-icons, favourites, and wine style colour indicators.
- Carte des Vins unready wines visibility setting (`winemenu_include_unready`) in Site Settings (`backend/settings.php`), enabling cellarmen to exclude (default) or display maturing wines.
- `AGENTS.md` coding directives and standards for agentic programming, including strict cookie minimisation directives restricting usage to anonymous session cookies for logged-in users.

### Changed
- Configured quick inventory ledger in `backend/manageBottles.php` to display active in-cellar bottles by default with full directory search inclusion by default and configurable limit controls (100, 250, 500, 1000, all), accompanied by an interactive status toggle checkbox, dynamic status badges, and an adaptive count label displaying "Y bottles" when out-of-cellar inventory is inspected.
- Streamlined the top navigation 'Admin' dropdown menu (`includes/header.php`) to focus strictly on essential, high-frequency actions (Browse bottles, Manage bottles, Manage orders; Browse wines, Add wine, Add wine master; Site settings, Manage users) grouped into clear semantic sections.
- Deduplicated all links across the Admin Hub (`backend/index.php`) domain cards and Terroir table, wrapping each entry in a single full-row anchor element with unambiguous naming and distinct destination URLs.
- Converted all 27 legacy standalone `add*.php` and `edit*.php` files in `/backend/` into backwards-compatible HTTP 301 redirect shims with query parameter preservation.
- Updated all internal links across navigation drawers, Admin Hub cards, quick actions bar, browse screens, and manage dashboards to point directly to unified `manage*.php` scripts.
- Enforced strict maximum capacity validation when adding bottles (`backend/manageBottles.php`), editing bottle locations, and accepting order deliveries (`backend/manageOrders.php`).
- Enhanced `getStorageLocations()` to query maximum capacities and active bottle counts per bin with backwards-compatible schema fallback.
- Simplified Carte des vins status badges in storage popovers (`winemenu.php`): trimmed verbose "Drink soon · Past yyyy" to "Drink soon" and "Aging · From yyyy" to British English "Ageing", preserving full drinking window details in hover tooltips.
- Refactored storage bin popover lists (`.vintage-menu-bins`) to use responsive flex wrapping on mobile viewports (&le; 720px) in `includes/styles.css`, preventing bottle count labels from overshooting card boundaries.
- Standardised British English spelling from "aging" to "ageing" across UI strings, setting options, docblocks, and documentation.
- Harmonised Carte des vins Legend card vertical margin and internal top/bottom spacing with the site-wide card standard.
- Harmonised typography and font sizing on vintage charts and reports (`vintages.php`) with site-wide Georgia serif standards, eliminating sub-12px text, aligning card introductions with site styling, and standardising sidebar rating scale presentation.
- Clarified post-moderation policy and workflow across user manual (`manual/05-blog-and-stories.md`), `README.md`, and comment moderation dashboard (`backend/manageComments.php`).

### Deprecated
- Twenty-seven legacy backend scripts deprecated in favour of unified `manage*.php` scripts; scheduled for permanent removal in version 2.0.0:
  `addBottle.php`, `editBottle.php`, `addOrder.php`, `addWine.php`, `editWine.php`, `addWineMaster.php`, `editWineMaster.php`, `addTastingNote.php`, `editTastingNote.php`, `blindTasting.php`, `addBlogpost.php`, `editBlogpost.php`, `addUser.php`, `editUser.php`, `editStaticPage.php`, `addCountry.php`, `editCountry.php`, `addRegion.php`, `editRegion.php`, `addSubregion.php`, `editSubregion.php`, `addAppellation.php`, `editAppellation.php`, `addVineyard.php`, `editVineyard.php`, `addProducer.php`, `editProducer.php`.
- Helper function `getCommentsTableMetadata()` and transitional runtime database schema fallbacks for legacy `comments` table columns (`comment` vs `content`, `comment_date` vs `pub_time`, missing `status` column); scheduled for permanent removal in version 2.0.0 when schema standardisation is enforced.
- Transitional RBAC permission fallbacks (e.g. `manage_privileges` falling back to `moderate_comments` or `manage_rating_scales`) in `includes/init.php` and `getUserEffectivePrivileges()`; scheduled for removal in v2.0.0 in favour of strict granular role assignments.

### Fixed
- Fixed SQL query preparation failures across terroir management dashboards (`manageCountries.php`, `manageRegions.php`, `manageSubregions.php`, `manageAppellations.php`, and `manageVineyards.php`) by updating errant references from non-existent table `x_wine_master` to `wines_master`, restoring wine cuvée statistics and records display.
- Fixed mobile segmented pane switcher concealment in `includes/styles.css`: encapsulated desktop hiding rules within `@media screen and (min-width: 721px)`, restoring visibility of the Add Bottle Format form on `manageFormats.php` and dual-pane controls on mobile viewports.
- Fixed table search filtering inoperability on mobile devices across administration dashboards (`manageBlogposts.php`, `manageBottles.php`, and directory tables) caused by CSS `!important` display rules overriding JavaScript row hiding; added `oninput` event listeners for mobile virtual keyboard responsiveness.
- Fixed drinking window and dropdown field overflow in `manageBottles.php` on narrow viewports: applied flex containment (`min-width: 0`, `flex: 1 1 0`, and `box-sizing: border-box`) and reset multi-column grid spans on mobile.
- Enhanced comment moderation search (`manageComments.php` and `includes/functions.php`) to query wine cuvée names, producers, and story titles alongside comment content and authors, adding instant live client-side filtering as users type.
- Restored missing search filter inputs on form dropdowns across unified `manage*.php` scripts that were lost during CRUD consolidation (including wine master selection when adding vintages, wine cuvée selection when editing bottles, user selection, story selection, tasting note and wine selection, and terroir region and appellation dropdowns).
- Restored discrete BLIC score inputs and dynamic WSET score recommendation logic in `backend/manageTastingNotes.php`, including suggested 100-point and 20-point ranges and an instant score population button.
- Replaced double-escaped HTML dash entities (`&mdash;`) in backend dropdown options and item labels across `manageBottles.php`, `manageWineMasters.php`, `manageWines.php`, `manageTastingNotes.php`, `manageBlogposts.php`, `manageStaticPages.php`, and `settings.php` with native UTF-8 em dashes (`—`), preventing literal `&mdash;` strings from displaying in dropdown lists.
- Resolved immediate redirects to `/backend/index.php` in `manageWines.php`, `manageWineMasters.php`, `manageStaticPages.php`, `manageUsers.php`, and `manageOrders.php` caused by non-canonical privilege checks:
  - Replaced non-existent `manage_wines` check with dual granular `add_wine` and `edit_wine` verification in `manageWines.php`.
  - Replaced non-existent `manage_master` check with dual granular `add_wine_master` and `edit_wine_master` verification in `manageWineMasters.php`.
  - Replaced non-existent `add_user` and `edit_user` checks with canonical `manage_users` check in `manageUsers.php`.
  - Added dual check for `manage_orders` and `add_order` in `manageOrders.php`, allowing order creators to enter the dashboard.
  - Protected `manageStaticPages.php` with dedicated `manage_static_pages` privilege and transitional `manage_privileges` fallback.
- Fixed HTTP 500 error when accessing comment moderation (`backend/manageComments.php`):
  - Added schema introspection helper `getCommentsTableMetadata()` with dynamic column aliasing and automatic fallback between historic schema (`pub_time`, `content`) and modern moderation schema (`pub_time`, `content`, `status`).
  - Added automatic column migration attempting to provision `comments.status` if missing, with graceful fallback to virtual approved status for existing entries.
  - Implemented documented RBAC fallback in `includes/init.php` and `getUserEffectivePrivileges()` granting `manage_privileges` administrators access to `moderate_comments` and `manage_rating_scales`.
  - Harmonised `install/schema.sql` `comments` table definition to use `pub_time` and `content` consistent with frontend and tasting note interactions.
  - Wrapped backend comment database queries in exception handlers and ensured null-safe rendering of comment bodies and dates.
- Fixed single vintage report rendering failure on `vintages.php` when clicking on a vintage year:
  - Guarded against fatal `fetch_assoc() on bool` TypeErrors by verifying `mysqli_result` instances across `getVintageSummary()`, `getVintageRegionStats()`, `getVintageCountryStats()`, `getVintageTopWines()`, and `getAdjacentVintages()`.
  - Resolved `ONLY_FULL_GROUP_BY` and unindexed `TEXT` column grouping incompatibilities in `view_vintage_region_colour_stats` and fallback query by utilising `MAX(xvr.vintage_desc)` aggregate.
  - Hardened top wines date formatting in `vintages.php` to verify `DateTimeInterface` objects before calling `date_format()`.

## [1.1.0] - 2026-09-08

### Added
- Flexible WSET SAT operational modes (`public`, `logged_in`, `backend_only`, and `disabled`) in Site Settings and Installation Wizard.
- Support for internal backend-only WSET data entry for personal cellar tracking without public display.
- Configurable WSET display format (`standard` vs. `detailed` BLIC criteria breakdown) on tasting notes.
- Private indicator badge on tasting notes for ratings displayed under `logged_in` mode.
- Transparent auto-migration converting legacy `wset_enabled` setting to `wset_mode` on upgrade.
- Comprehensive `UPGRADE.md` migration guide detailing deprecations, database migrations, and upcoming breaking changes in v2.0.0.
- Unified blind tasting workflow and cellar bottle selection into `backend/addTastingNote.php`, featuring concealed bottle selection, wine reveal disclosure, and post-submission bottle consumption tracking.
- Modern, responsive **Admin Hub** control center (`backend/index.php`) featuring at-a-glance KPI metric cards (bottles in cellar, ready-to-drink percentage, catalog vintages/masters, tasting notes, pending orders), touch-friendly quick action shortcuts, domain cards, compact terroir matrix, and interactive storage location breakdown.
- Section headers (`.submenu-header`) in dropdown navigation menus for clearer visual grouping of administrative tools.
- Intermediate responsive navigation rules (721px–1060px viewports) providing comfortable padding and font-sizing on tablet screens without navbar wrapping.

### Changed
- Streamlined Admin navigation dropdown across desktop and mobile viewports: removed redundant `Browse wines` and `Add wine` links, and consolidated separate `Add user` and `Edit user` links into a single `Users` management interface (`backend/editUser.php`).
- Added responsive mobile scroll containment (`overflow-y: auto; max-height: calc(100vh - 75px)`) to topnav menu to prevent menu clipping and ensure comfortable scrolling on small screens.
- Renamed legacy "Dashboard" to **"Admin Hub"** across application navigation, page titles, quick links, and user manual.
- Flattened the single-item "For friends" dropdown into an immediate top-level **"Carte des vins"** navigation link.
- Structured the "Admin" dropdown menu into clear visual sections (`Admin Hub`, `Cellar & Catalog`, and `Administration`).
- Replaced outdated external profile text on `backend/index.php` with a live System Status card displaying PHP runtime, database status, and cellar inventory metrics.
- Retired legacy `wset_enabled` setting from active codebase and seed database schema.
- Enhanced `buildKeywordsList()` with flexible arguments (supporting strings, arrays, or nested lists), HTML tag stripping, whitespace normalization, surrounding quote trimming, and robust case-insensitive deduplication.
- Modernized tasting note rating display in `backend/addBlogpost.php` to use scale-aware `formatNoteRatingBadge()`.

### Deprecated
- Standalone `backend/blindTasting.php` is deprecated in favor of `backend/addTastingNote.php?mode=blind`. Scheduled for removal in v2.0.0.
- Helper `isWsetSATEnabled()` is deprecated in favor of `isWsetSATEntryEnabled()` and `isWsetSATVisibleToViewer()`. Scheduled for removal in v2.0.0.
- Legacy `site_settings.wset_enabled` setting key is deprecated in favor of `wset_mode`. It is automatically migrated on read and will be removed in v2.0.0.
- Standalone redirect stubs `tnote.php`, `wine.php`, and `blogpost.php` are deprecated in favor of direct requests to `tnotes.php`, `wines.php`, and `blog.php`. Scheduled for removal in v2.0.0.
- Legacy rating column alias `dmpts` and aggregate column `avg_dmpts` in SQL views (`view_vintage_region_colour_stats`, `view_vintage_top_wines`) and PHP helper query results are deprecated in favor of `pts_20` and `avg_pts_20`. Scheduled for removal in v2.0.0.
- Legacy backend form parameter `$_POST['dmpts']` is deprecated in favor of `pts_20` across tasting note editors. Scheduled for removal in v2.0.0.

### Fixed
- Corrected inaccurate and obsolete descriptions in user manual (`manual/01` through `manual/07`), fixing Carte des vins access permissions (member-only by default), aligning navigation paths with the application interface, detailing the 29 actual system privileges, documenting the unified blind tasting workflow, and correcting static page database keys.
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
