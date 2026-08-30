# Security Policy

## Supported Versions

Only the latest stable release of **phpMyCellar** receives active security updates and vulnerability patches.

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

---

## Reporting a Vulnerability

We take the security of phpMyCellar seriously. If you believe you have found a security vulnerability in this project, please report it to us responsibly before making it public.

### How to Report
- **Email:** Send details of the vulnerability to the project maintainer at `security@dmueller.com` (or use the site administrator contact email listed in your deployment).
- **GitHub Security Advisories:** If using GitHub, you may also report security issues privately via the **Security** tab -> **Advisories** -> **Report a vulnerability**.

### What to Include
To help us understand and resolve the issue quickly, please provide:
1. **Description**: Clear description of the vulnerability and its potential impact.
2. **Steps to Reproduce**: Detailed reproduction steps or Proof of Concept (PoC).
3. **Environment**: PHP version, web server (Apache/Nginx), and database version (MariaDB/MySQL).
4. **Proposed Fix**: Any suggested mitigations or patches (if available).

### Response Process
1. **Acknowledgement:** We will acknowledge receipt of your vulnerability report within 48 hours.
2. **Assessment:** We will confirm the issue, evaluate its severity, and determine an appropriate remediation plan.
3. **Fix & Release:** A fix will be developed, tested, and published in a new release as promptly as possible.
4. **Credit:** Unless you prefer to remain anonymous, we will gladly acknowledge and credit your contribution in the release notes.

---

## Security Architecture & Best Practices

phpMyCellar implements standard defensive measures:
- **Cross-Site Request Forgery (CSRF):** Synchronizer tokens are enforced on all state-changing `POST` operations and sensitive administrative workflows.
- **SQL Injection Prevention:** All database operations utilize parameterized queries (`mysqli::prepare`) and strict type binding.
- **Cross-Site Scripting (XSS):** Context-aware HTML entity encoding (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`) is applied to user-supplied text and comments. Rich-text markup is filtered against dangerous attributes and JavaScript schemes.
- **File Upload Protection:** The `uploads/` directory is hardened with `.htaccess` directives preventing script execution and directory browsing. MIME types, extensions, and file sizes are strictly validated server-side.
- **Authentication & RBAC:** Passwords are hashed using PHP's native `password_hash()` (bcrypt / ARGON2ID). Granular role-based access control and privilege gates protect backend management interfaces.
