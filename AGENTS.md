# AGENTS.md — Agentic Programming Directives & Coding Standards

This document establishes the official instructions, standards, and conventions for AI coding agents operating within the **phpMyCellar** repository. All agents must follow these directives to maintain architectural integrity, code quality, and repository consistency.

---

## 1. Core Principles & Philosophy

- **Lightweight & Self-Hosted:** phpMyCellar is a self-hosted wine cellar notebook, tasting journal, and digital cellar menu. It avoids heavy third-party frameworks, node-based frontend build pipelines, and external telemetry.
- **Maintainability & Portability:** Keep solutions simple, robust, and portable across standard LAMP/LEMP and Docker hosting environments.
- **High-Signal, Succinct Communication:** State facts clearly, avoid conversational padding, and keep explanations and documentation crisp.

---

## 2. Language & Spelling Standards

- **British English (`en-GB`) Required:** All code comments, docblocks, documentation, user manuals, UI labels, error messages, and commit messages **must** use British English.
  - Examples: *colour* (not color), *customisation* (not customization), *standardise* (not standardize), *behaviour* (not behavior), *prioritise* (not prioritize), *synchroniser* (not synchronizer), *initialise* (not initialize), *licence* (noun) / *license* (verb), *catalogue* (not catalog).
- **Existing Database Fields:** Note that some legacy database columns (e.g. `site_settings.accent_color`) retain their historic spelling for backwards compatibility. Do not rename database columns without an approved migration plan. All new comments and documentation discussing them must use British English (*accent colour*).

---

## 3. Code Documentation Standards

- **Comprehensive PHPDoc Blocks:** Every new or modified function, class, and method must have a descriptive docblock documenting:
  - Purpose and architectural context.
  - Parameter types and descriptions (`@param string $var Description`).
  - Return types and potential nullability (`@return array|null Description`).
  - Exceptions or fatal conditions (`@throws Exception Description`).
  - Deprecations where applicable (`@deprecated 1.1.0 Scheduled for removal in v2.0.0. @see replacement()`).
- **Document RBAC Privilege Requirements:** Backend management scripts and helper functions handling sensitive cellar data must explicitly document required privileges at the top of the file:
  ```php
  /**
   * Add a new bottle to the cellar inventory.
   *
   * Required privilege: 'add_bottle'
   */
  ```
- **Explain the "Why", Not Just the "What":** Inline comments should explain non-obvious business logic, domain rationale (e.g. wine ageing curves, WSET qualitative criteria), or edge-case handling rather than narrating what the syntax already reveals.
- **Preserve Existing Documentation:** Do not delete existing comments, license notices, or historic annotations unless explicitly refactoring or replacing obsolete code.

---

## 4. Documentation Synchronisation & Conciseness

Agents must keep all repository documentation strictly in sync with code changes:

- **Mandatory Documentation Updates:** Whenever adding, modifying, deprecating, or fixing functionality:
  1. **`CHANGELOG.md`:** Update immediately under the active `[Unreleased]` section.
  2. **`README.md`:** Update the feature table, directory structure, or overview if applicable.
  3. **`manual/`:** Update the relevant chapter in the user manual (`manual/01` through `manual/08`).
  4. **`UPGRADE.md`:** Update when adding deprecations, migration steps, or database schema changes.
- **As Succinct as Possible:** All documentation updates must be **succinct**, high-signal, and to the point. Avoid verbose filler, redundant summaries, and repetitive narrative.

---

## 5. Changelog & Versioning Conventions

### Keep a Changelog
- All changes must be recorded in [`CHANGELOG.md`](CHANGELOG.md) adhering strictly to [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
- Standard section headers:
  - `### Added` for new features or capabilities.
  - `### Changed` for changes in existing functionality.
  - `### Deprecated` for soon-to-be-removed features.
  - `### Removed` for now removed features.
  - `### Fixed` for any bug fixes.
  - `### Security` in case of vulnerabilities.
- Place new entries under `## [Unreleased]` until an official release is prepared.

### Semantic Versioning
- Project versioning adheres strictly to [Semantic Versioning (SemVer 2.0.0)](https://semver.org/spec/v2.0.0.html):
  - **Major (`X.0.0`):** Incompatible API changes, removal of deprecated components, breaking schema changes.
  - **Minor (`x.Y.0`):** New functionality in a backward-compatible manner, formal deprecation notices.
  - **Patch (`x.y.Z`):** Backward-compatible bug fixes, performance improvements, and security patches.

---

## 6. Architecture & Security Invariants

### Database Access & Prepared Statements
- **Never interpolate variables directly into SQL queries.**
- Always use parameterized prepared statements with native `mysqli::prepare()`:
  ```php
  $stmt = $conn->prepare("SELECT wine_id, wine_name FROM wines WHERE producer_id = ? AND active = ?");
  $stmt->bind_param("ii", $producer_id, $isActive);
  $stmt->execute();
  $result = $stmt->get_result();
  ```
- Always check that statements prepare successfully before binding or executing.

### Cross-Site Scripting (XSS) Prevention
- Always escape dynamic output rendered into HTML:
  ```php
  echo htmlspecialchars($wineName, ENT_QUOTES, 'UTF-8');
  ```
- Rich-text stored from the WYSIWYG editor must pass through HTML sanitisation helpers before display.

### Cross-Site Request Forgery (CSRF) Protection
- All state-changing `POST` requests and administrative forms must include and validate a CSRF token:
  ```php
  // In HTML form:
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

  // In request handler:
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
      http_response_code(403);
      die("CSRF token validation failed.");
  }
  ```

### Role-Based Access Control (RBAC)
- All backend pages and operations must enforce privilege checks using `hasPrivilege($conn, 'privilege_name')`.
- Restrict public views according to `site_settings` (e.g. Carte des vins visibility, WSET display mode).

### Frontend Standards
- Pure semantic HTML5, responsive CSS in `includes/styles.css`, and vanilla JavaScript.
- Do not introduce external frontend frameworks, bundlers, or npm dependencies.
- Mobile breakpoint is **720px** (`@media screen and (max-width: 720px)`).
- Ensure mobile navigation drawers include scroll containment (`overflow-y: auto; max-height: calc(100vh - 75px)`).
- Ensure all interactive touch targets meet accessibility guidelines (minimum 44 &times; 44 px).

---

## 7. Git Workflow & Commit Guidelines

- **Atomic Commits:** Each commit must represent a single, self-contained logical change or cohesive unit of work.
  - Do not combine refactoring, unrelated bug fixes, and feature additions into a single commit.
  - Separate code modifications from documentation overhauls when feasible.
- **Succinct Commit Messages:** Commit messages must be **succinct**, direct, and describe the change being made in the imperative mood or concise title format.
  - Examples of good commit messages:
    - `Fixed top navigation display on mobile`
    - `Added WSET SAT operational modes and BLIC breakdown`
    - `Merged blindTasting.php into addTastingNote.php`
    - `Added AGENTS.md for coding directives`
    - `Updated manual and CHANGELOG for v1.1.0`
- **Clean Working Tree:** Always ensure the git working tree is clean after completing changes. Remove temporary test scripts or artifacts.

---

## 8. Agent Verification Checklist

Before completing any task or declaring work ready for review, verify:
- [ ] **Syntax:** Ran `php -l` on all modified or newly created PHP files.
- [ ] **Language:** British English used in all code comments, docblocks, UI strings, and documentation.
- [ ] **Documentation Sync:** `CHANGELOG.md`, `README.md`, and relevant `manual/` files updated succinctly.
- [ ] **Security:** CSRF validated on forms, output escaped with `htmlspecialchars`, prepared statements used for all SQL queries.
- [ ] **Responsiveness:** Validated on both desktop (>720px) and mobile (&le;720px) screen viewports.
- [ ] **Git Integrity:** Commits are atomic with succinct, descriptive messages.
