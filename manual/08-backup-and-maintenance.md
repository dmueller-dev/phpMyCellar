# 08. Maintenance, Backups & Security

This guide outlines routine operational procedures, database backups, disaster recovery, and security best practices.

---

## 1. Database Backups

Because your entire cellar registry and tasting history reside in MariaDB / MySQL, scheduled database dumps are essential.

### Creating a Manual Backup via CLI:
```bash
# Export the database to a compressed SQL archive
mysqldump -u cellar_user -p --single-transaction --quick phpmycellar | gzip > phpmycellar_backup_$(date +%F).sql.gz
```

### Automated Nightly Cron Job:
Add the following entry to your server's crontab (`crontab -e`):
```cron
# Daily backup at 03:00 AM
0 3 * * * mysqldump -u cellar_user -p'your_db_password' --single-transaction phpmycellar | gzip > /backups/phpmycellar_$(date +\%F).sql.gz
```

---

## 2. File & Upload Backups

In addition to the database, back up the contents of the `uploads/` directory and your `.env` configuration file:

```bash
# Archive user uploads and configuration
tar -czvf phpmycellar_files_$(date +%F).tar.gz uploads/ .env
```

---

## 3. Database Restoration

To restore your cellar on a fresh server or after hardware maintenance:

```bash
# 1. Create database if it does not exist
mysql -u root -p -e "CREATE DATABASE phpmycellar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Import backup archive
gunzip < phpmycellar_backup_YYYY-MM-DD.sql.gz | mysql -u cellar_user -p phpmycellar
```

---

## 4. Security Hardening Checklist

- [ ] **Verify `.htaccess` or Nginx blocks:** Ensure `.env`, `includes/`, and SQL schemas cannot be fetched directly via HTTP.
- [ ] **Enforce HTTPS:** Ensure all web traffic is served over TLS/SSL using Let's Encrypt or your SSL certificate provider.
- [ ] **Verify `install/installed.lock`:** Confirm the installer lock file is present to block setup wizard access.
- [ ] **Disable Unused PHP Functions:** In `php.ini`, ensure `exec`, `shell_exec`, `passthru`, and `system` are disabled if not needed.
- [ ] **File Permissions:** Ensure uploaded files are non-executable and web root files are read-only for PHP processes wherever possible.
