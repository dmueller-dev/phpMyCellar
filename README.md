# phpMyCellar

> A lightweight, self-hosted fine wine cellar management notebook, tasting notes journal, and interactive cellar menu built with modern PHP and MariaDB.

[![Licence: MIT](https://img.shields.io/badge/Licence-MIT-blue.svg)](LICENSE)
[![CI](https://github.com/dmueller-dev/phpMyCellar/actions/workflows/ci.yml/badge.svg)](https://github.com/dmueller-dev/phpMyCellar/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%207.4%20%7C%208.x-8892BF.svg)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MariaDB%20%7C%20MySQL-003545.svg)](https://mariadb.org/)

---

## Overview

**phpMyCellar** is designed for wine enthusiasts, collectors, and sommeliers who want full ownership over their wine collection data and tasting impressions. Unlike closed commercial platforms, phpMyCellar provides a private or public-facing digital cellar notebook with zero tracking, external telemetry, or cloud subscriptions.

### What phpMyCellar Is:
- **A Personal Cellar Manager:** Track individual bottles, bottle formats, storage locations, purchase costs, merchant orders, and delivery statuses.
- **A Detailed Tasting Journal:** Record tasting notes with support for multiple rating scales (20-point DM scale, 100-point, 5-star, and WSET SAT criteria), blind tastings, and high-resolution photography.
- **An Interactive Wine Menu (*Carte des vins*):** Generate an elegant, shareable wine menu for family, dinner guests, and tasting groups showing what is ready to drink.
- **A Wine Blog & Story Publishing System:** Write long-form tasting horizontal/vertical reports, winery visits, and vintage overviews.
- **Privacy-First & Self-Hosted:** Run on any standard PHP hosting provider, VPS, or container environment with full data ownership.

### What phpMyCellar Is Not:
- An e-commerce marketplace or POS point-of-sale terminal.
- A commercial wine rating aggregator or barcode-scraping mobile application.

---

## Key Features

| Category | Highlights |
| :--- | :--- |
| **Cellar Management** | • Track producers, wine masters, appellations, vineyards, and vintages.<br>• Multiple bottle formats (375ml half-bottles, 750ml, 1500ml magnums, and large formats).<br>• Storage location bin tracking (e.g. Rack A1, Shelf 3).<br>• Purchase order management with delivery receipts and PDF invoice uploads. |
| **Tasting Notes** | • Detailed sensory evaluations with drinking window forecasting.<br>• Configurable rating scales: 20-point (DM scale), 100-point, 5-star, and WSET SAT.<br>• Blind tasting mode allowing notes to be drafted before revealing the wine.<br>• Automatic SEO metadata, OpenGraph tags, and JSON-LD structured data. |
| **Interactive Menu** | • Live *Carte des vins* highlighting ready-to-drink wines for guests.<br>• Filter by colour, style, vintage, grape variety, and producer.<br>• Sortable by producer, vintage, or wine style with visual colour badges. |
| **Stories & Articles** | • Integrated WYSIWYG editor with captioned image insertion.<br>• In-depth vintage report overviews dynamically aggregated from tasting history.<br>• Public commenting system with email subscription notifications. |
| **Security & RBAC** | • Granular Role-Based Access Control (Public, Reader, Contributor, Admin).<br>• Granular user privilege overrides.<br>• Synchronizer CSRF tokens on all state-changing actions.<br>• Hardened `uploads/` directory with script execution restrictions. |
| **Customisation** | • Dynamic branding: Site name, tagline, base URL, currency symbol, and owner details.<br>• Real-time theme accent colour customisation.<br>• Database-managed static pages (Welcome, Impressum, Privacy Policy, Rating Scale guide). |

---

## Technology Stack

- **Backend:** PHP 7.4+ (PHP 8.1 / 8.2+ recommended) with native `mysqli` prepared statements.
- **Database:** MariaDB 10.4+ / MySQL 8.0+ (`utf8mb4` character set).
- **Frontend:** Semantic HTML5, Vanilla JavaScript, and responsive CSS (zero bulky frontend build pipelines).
- **Architecture:** Lightweight MVC-inspired structure with standalone server portability.

---

## Quick Start Installation

### Option 1: Web Setup Wizard (Standard Hosting / VPS)

1. **Upload Files:** Upload the phpMyCellar codebase to your web server document root (e.g. `/var/www/html` or `public_html`).
2. **Server Configuration:**
   - For **Apache**, copy `.htaccess.example` to `.htaccess`.
   - For **Nginx**, configure your server block using `nginx.conf.example`.
3. **Set Permissions:** Ensure the web server has write permissions to:
   - Root directory `.` (for `.env` creation)
   - `install/` (for `installed.lock` creation)
   - `uploads/`, `uploads/invoices/`, and `uploads/img/`
4. **Launch Wizard:** Navigate to `http://your-domain.com/` in your web browser. The setup wizard will guide you through:
   - System requirement & extension checks.
   - Database connection testing and automatic schema/seed execution.
   - Super-administrator (`user_id = 1`) account creation.
   - Initial site branding and currency configuration.
5. **Done!** The wizard writes your `.env` configuration and locks the installer.

---

### Option 2: Docker Compose (Local Development & Container Deployments)

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/dmueller-dev/phpMyCellar.git
   cd phpMyCellar
   ```

2. **Start the Stack:**
   ```bash
   docker compose up -d --build
   ```

3. **Complete Installation:**
   Open your browser at [http://localhost:8080/install/](http://localhost:8080/install/) to complete the web wizard.

---

## Directory Structure

```text
phpMyCellar/
├── backend/               # Administrative management interfaces
├── includes/              # Shared helper functions, styles, scripts, and init
│   ├── functions.php      # Business logic, SEO schema, and database helpers
│   ├── header.php         # Global HTML header, navigation, and theme loader
│   ├── footer.php         # Global footer
│   ├── init.php           # Database initialization and privilege middleware
│   ├── styles.css         # Responsive typography and layout stylesheets
│   └── wysiwyg.js         # Rich-text editor integration
├── install/               # Installation wizard & database migration scripts
│   ├── index.php          # Interactive setup wizard
│   ├── schema.sql         # Database table definitions
│   └── seed.sql           # Lookup tables, default privileges, and static content
├── manual/                # Comprehensive User & Administrator Manual
├── uploads/               # User upload directories (hardened with .htaccess)
│   ├── img/               # Wine bottle photos and blog post imagery
│   └── invoices/          # Order invoice PDFs and merchant receipts
├── Dockerfile             # Production-ready PHP 8.2 Apache container
├── docker-compose.yml     # Local orchestration stack with MariaDB
├── .env.example           # Environment template
├── .htaccess.example      # Apache server configuration template
├── nginx.conf.example     # Nginx server block configuration template
└── SECURITY.md            # Security policy and vulnerability disclosure
```

---

## Documentation & Manual

Full user guides and administrator manuals are available in the [manual/](manual/) directory:

- [01. Welcome & Core Philosophy](manual/01-welcome.md)
- [02. Getting Started & Installation](manual/02-getting-started.md)
- [03. Wine Cellar & Order Management](manual/03-cellar-management.md)
- [04. Tasting Notes, Ratings & Blind Tastings](manual/04-tasting-notes.md)
- [05. Stories, Articles & Image Publishing](manual/05-blog-and-stories.md)
- [06. User Accounts, Roles & Privileges](manual/06-users-and-privileges.md)
- [07. Site Customisation & Static Pages](manual/07-site-customisation.md)
- [08. Maintenance, Backups & Security](manual/08-backup-and-maintenance.md)

---

## Contributing

We welcome contributions of all kinds! Please read our [Contributing Guidelines](CONTRIBUTING.md) and [Code of Conduct](CODE_OF_CONDUCT.md) before submitting pull requests or opening issues.

---

## Security

Please review [SECURITY.md](SECURITY.md) for information regarding supported versions and responsible vulnerability disclosure.

---

## Licence

This project is licensed under the terms of the **MIT Licence**. See the [LICENSE](LICENSE) file for details.
