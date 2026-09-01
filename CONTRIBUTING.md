# Contributing to phpMyCellar

Thank you for your interest in contributing to **phpMyCellar**! As an open source project, we welcome contributions from developers, wine enthusiasts, and technical writers of all backgrounds and experience levels.

Please take a moment to review these guidelines before submitting an issue or pull request.

---

## Code of Conduct

All contributors and participants are expected to adhere to our [Code of Conduct](CODE_OF_CONDUCT.md). Please ensure all interactions remain constructive, respectful, and inclusive.

---

## How Can I Contribute?

### 1. Reporting Bugs
- Before opening a new issue, check the [issue tracker](https://github.com/dmueller-dev/phpMyCellar/issues) to ensure the bug has not already been reported.
- Use the **Bug Report** template and provide as much detail as possible:
  - PHP version, web server, and MariaDB/MySQL version.
  - Step-by-step reproduction steps.
  - Expected versus actual behaviour.
  - Relevant PHP error logs (ensure credentials and sensitive data are removed).

### 2. Suggesting Enhancements
- Check existing issues and discussions to see if the feature has already been proposed.
- Use the **Feature Request** template to describe the problem your idea solves, the proposed user workflow, and any alternative solutions you considered.

### 3. Improving Documentation
- Documentation improvements are always welcome! This includes:
  - Clarifications in `README.md` or user guides in `manual/`.
  - Additional server setup guides or Docker configuration tips.
  - Fixing typos, phrasing, or formatting issues.

### 4. Submitting Code Changes
- For significant new features or architectural changes, please open an issue first to discuss the design before implementing it.
- For bug fixes and small enhancements, you can proceed directly with a Pull Request.

---

## Development Environment Setup

### Option A: Using Docker (Recommended)

1. **Clone the repository:**
   ```bash
   git clone https://github.com/dmueller-dev/phpMyCellar.git
   cd phpMyCellar
   ```

2. **Start the containers:**
   ```bash
   docker compose up -d
   ```
   This will spin up a PHP 8.2 + Apache container and a MariaDB 10.11 container.

3. **Complete Installation:**
   Open `http://localhost:8080/install/index.php` in your browser and complete the web setup wizard using:
   - **Database Host:** `db`
   - **Database Name:** `phpmycellar`
   - **Database User:** `cellar_user`
   - **Database Password:** `cellar_secret`

### Option B: Local LAMP / LEMP Stack

1. **Prerequisites:**
   - PHP 7.4 or later (PHP 8.1 / 8.2+ recommended) with extensions: `mysqli`, `mbstring`, `gd` (or `imagick`), `session`, `curl`.
   - MariaDB 10.4+ or MySQL 8.0+.
   - Apache (with `mod_rewrite` and `mod_headers`) or Nginx.

2. **Configure Environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your local database and mail credentials
   ```

3. **Initialise Database:**
   Run the setup wizard via `http://localhost/install/index.php` or import the schema and seed files directly:
   ```bash
   mysql -u root -p phpmycellar < install/schema.sql
   mysql -u root -p phpmycellar < install/seed.sql
   ```

---

## Coding Standards & Architecture

phpMyCellar emphasizes simplicity, long-term maintainability, zero bloated frontend dependencies, and robust defensive security.

### 1. PHP Code Style
- Adhere to the **PSR-12** coding standard.
- Use 4 spaces for indentation (no tabs).
- Use clear, descriptive variable and function names.
- Always check PHP syntax before committing:
  ```bash
  find . -type f -name "*.php" -not -path "./.git/*" -exec php -l {} +
  ```

### 2. Database & SQL Security
- **Never interpolate variables directly into SQL queries.**
- Always use parameterized prepared statements with native `mysqli::prepare()`:
  ```php
  $stmt = $conn->prepare("SELECT wine_id, wine_name FROM wines WHERE producer_id = ? AND active = ?");
  $stmt->bind_param("ii", $producer_id, $is_active);
  $stmt->execute();
  $result = $stmt->get_result();
  ```
- Keep column and table naming consistent with existing snake_case conventions (`tasting_notes`, `bottle_status`, `order_date`).

### 3. Cross-Site Scripting (XSS) & Input Handling
- Always encode output when rendering user-controlled or database content into HTML:
  ```php
  echo htmlspecialchars($wine_name, ENT_QUOTES, 'UTF-8');
  ```
- For rich-text HTML stored via the WYSIWYG editor, ensure sanitisation is applied before rendering (see `includes/functions.php`).

### 4. Cross-Site Request Forgery (CSRF) Protection
- All state-changing `POST` requests and administrative forms must include and validate a CSRF token:
  ```php
  // In HTML form:
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

  // In form handler:
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
      die("CSRF validation failed.");
  }
  ```

### 5. Frontend & CSS
- Write clean, semantic HTML5.
- Maintain responsive layouts using vanilla CSS with CSS Custom Properties defined in `includes/styles.css`.
- Keep JavaScript vanilla and dependency-free. Do not introduce large external frontend frameworks (e.g. React/Vue) unless approved via discussion.

---

## Commit & Branching Workflow

1. **Fork and Branch:**
   Create a new branch from `main`:
   ```bash
   git checkout -b feature/my-feature-name
   # or
   git checkout -b fix/issue-description
   ```

2. **Commit Messages:**
   Write concise, descriptive commit messages following the [Conventional Commits](https://www.conventionalcommits.org/) format:
   - `feat: add CSV export for tasting notes`
   - `fix: resolve vintage filter pagination bug`
   - `docs: clarify Nginx fastcgi_param configuration`
   - `refactor: clean up cellar menu database queries`

3. **Verify Locally:**
   - Ensure all PHP files pass linting (`php -l`).
   - Test your changes thoroughly across desktop and mobile screen viewports.
   - Verify that no sensitive configuration or `.env` files are tracked.

---

## Pull Request Checklist

When opening a Pull Request, please ensure:
- [ ] The PR branch is based on the latest `main` branch.
- [ ] Code follows the project's coding standards and security practices.
- [ ] All new and modified PHP files pass syntax checks.
- [ ] If changing functionality or adding features, relevant user manual pages in `manual/` have been updated.
- [ ] Clear description of changes and motivation is provided in the PR description.
- [ ] Any related issues are linked (e.g. `Closes #42`).
