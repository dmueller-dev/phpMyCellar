# 02. Getting Started & Installation

This guide walks you through system requirements, web server configuration, and the first-time installation wizard.

---

## 1. System Requirements

To run phpMyCellar, your hosting environment must meet the following minimum requirements:

### Server Environment
- **PHP Version:** PHP 7.4.0 or higher (PHP 8.1 / 8.2+ recommended).
- **Database:** MariaDB 10.4+ or MySQL 8.0+ with `utf8mb4` support.
- **Web Server:** Apache 2.4+ (with `mod_rewrite`, `mod_headers`, `mod_expires`) or Nginx 1.18+.

### Required PHP Extensions
- `mysqli` (Database connectivity)
- `mbstring` (Multibyte character handling)
- `session` (User authentication and session state)
- `json` (Data serialization and JSON-LD structured data)
- `fileinfo` (MIME-type validation for invoice and image uploads)
- `gd` or `imagick` (Image processing and thumbnail generation)

---

## 2. Directory Permissions

Ensure your web server process (e.g. `www-data`, `apache`, `nginx`) has write permissions to:

```bash
# Set write permissions on configuration and upload directories
chmod 775 uploads uploads/invoices uploads/img install .
```

---

## 3. Installation Methods

### Method A: Standard Web Hosting / VPS (Interactive Web Wizard)

1. Upload the phpMyCellar files to your web root directory (e.g. `/var/www/html`).
2. Copy the sample Apache configuration:
   ```bash
   cp .htaccess.example .htaccess
   ```
   *(Or configure Nginx using `nginx.conf.example`)*.
3. Open your browser and navigate to:
   ```text
   http://your-domain.com/
   ```
4. The interactive installer will run automatically and guide you through 5 simple steps:
   - **Step 1: System Checks** — Verifies extensions and directory permissions.
   - **Step 2: Database Setup** — Connects to MySQL/MariaDB and executes table schemas and seed lookups.
   - **Step 3: Administrator Account** — Configures your super-admin account (`user_id = 1`).
   - **Step 4: Site Branding** — Sets the cellar name, currency symbol, and default rating scale.
   - **Step 5: Finalisation** — Automatically writes `.env` and locks the installer.

---

### Method B: Docker Compose

For local development or containerised production servers:

1. Clone the repository:
   ```bash
   git clone https://github.com/dmueller-dev/phpMyCellar.git
   cd phpMyCellar
   ```
2. Launch the container stack:
   ```bash
   docker compose up -d --build
   ```
3. Open [http://localhost:8080/install/](http://localhost:8080/install/) to complete the setup wizard.

---

## 4. Post-Installation Verification

Once installation is complete:
1. Verify that `install/installed.lock` exists in your installation directory.
2. Log in to the administrative backend at `/login.php`.
3. Proceed to [03. Wine Cellar & Order Management](03-cellar-management.md) to add your first producers and wines.
