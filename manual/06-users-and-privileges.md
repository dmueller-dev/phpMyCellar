# 06. User Accounts, Roles & Dynamic Privileges

phpMyCellar features a granular Role-Based Access Control (RBAC) engine coupled with individual user privilege overrides.

---

## 1. System Roles

The system comes pre-configured with four standard roles:

| Role Code | Role Name | Description |
| :--- | :--- | :--- |
| **`public`** | Anonymous Visitor | Can browse public wine menus, published tasting notes, blog articles, and static pages. Cannot access the backend. |
| **`read`** | Reader / Guest | Logged-in user who can view internal bottle details, cellar storage locations, and subscribe to notifications. |
| **`write`** | Contributor / Sommelier | Can record tasting notes, create wines, log purchase orders, and draft blog articles. |
| **`admin`** | Super Administrator | Full access to all backend operations, user management, site branding, theme customisation, and database tools. |

---

## 2. Dynamic Privileges & Permissions

Privileges are defined at the granular operation level across several categories:

### Cellar & Wine Privileges
- `wine_view_private`: View private cellar notes and storage bins.
- `wine_create`: Add new wines, producers, and vintages.
- `wine_edit`: Modify existing wine records.
- `wine_delete`: Delete wine records.
- `order_manage`: View and upload merchant purchase orders and PDF invoices.

### Tasting Notes & Articles
- `note_create`: Author new tasting notes.
- `note_edit_own`: Edit notes authored by oneself.
- `note_edit_all`: Edit notes authored by any user.
- `blog_publish`: Publish and edit stories and articles.
- `comment_moderate`: Moderate public discussion comments.

### System & Administration
- `user_manage`: Create users and adjust permissions.
- `settings_manage`: Change site title, branding, accent colours, and static pages.

---

## 3. User-Level Privilege Overrides

In addition to role defaults, administrators can grant or revoke specific privileges on a per-user basis:

- **Navigate to:** `Backend > Manage Users > Edit User Privileges`.
- Toggle individual checkboxes to grant custom capabilities (e.g. allowing a `read` user to manage tasting notes without giving full write access).
