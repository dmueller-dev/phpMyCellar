# 06. User Accounts, Roles & Dynamic Privileges

phpMyCellar features a granular Role-Based Access Control (RBAC) engine coupled with individual user privilege overrides.

---

## 1. System Roles

The system comes pre-configured with four standard roles:

| Role Code | Role Name | Description | Default Privileges |
| :--- | :--- | :--- | :--- |
| **`public`** | Anonymous Visitor | Unauthenticated visitors to the website. Can browse published tasting notes, vintage reports, wine database records, public stories, and static pages. **Cannot access the cellar wine menu (*Carte des vins*) or the backend.** | `view_tnotes`, `view_stories` |
| **`read`** | Reader / Member | Registered members. Can read notes and stories, view and post discussion comments, and access the private *Carte des vins* (`winemenu.php`). Cannot access the backend. | `view_tnotes`, `view_stories`, `view_comments`, `post_comments`, `view_cellar_menu` |
| **`write`** | Contributor / Writer | Contributing authors and sommeliers. Inherits all member reading capabilities and can draft/edit tasting notes and stories via the `Contribute` menu. Cannot modify cellar inventory, create wines, or log orders. | All `read` privileges plus `add_tasting_note`, `edit_tasting_note`, `add_blogpost`, `edit_blogpost` |
| **`admin`** | Administrator | Full access to backend inventory management, wine master definitions, purchase orders, site customisation, and user/privilege administration. Account ID 1 has immutable superuser access. | All 38 system privileges |

---

## 2. Dynamic Privileges & Permissions

Privileges are defined at the granular operation level across eight functional categories:

### Viewing & Reading
- `view_tnotes`: Browse and read tasting notes and vintage reports.
- `view_stories`: Read full blog posts and stories.
- `view_comments`: View discussions and comments across the site.
- `view_cellar_menu`: Access the interactive cellar menu (*Carte des vins*) for friends and guests.

### Community
- `post_comments`: Submit comments on tasting notes, stories, and wine pages.
- `moderate_comments`: Review, edit, approve, and delete reader comments (`manageComments.php`).

### Tasting Notes
- `add_tasting_note`: Create regular and blind tasting notes (`manageTastingNotes.php`).
- `edit_tasting_note`: Edit tasting notes authored by oneself (`manageTastingNotes.php`).
- `edit_all_tasting_notes`: Edit tasting notes authored by any user (`manageTastingNotes.php`).
- `publish_tasting_note`: Publish tasting notes directly (without requiring draft review).

### Blog Stories
- `add_blogpost`: Create new blog stories and articles (`manageBlogposts.php`).
- `edit_blogpost`: Edit stories authored by oneself (`manageBlogposts.php`).
- `edit_all_blogposts`: Edit stories authored by any author (`manageBlogposts.php`).
- `publish_blogpost`: Publish stories directly (without requiring draft review).

### Cellar & Orders
- `browse_bottles`: View bottles and storage locations in the backend (`browseBottles.php`).
- `manage_storage_bins`: Manage cellars, storage bins, and maximum capacities (`manageStorageBins.php`).
- `manage_stores`: Manage wine merchants, retailers, and order sources (`manageStores.php`).
- `manage_formats`: Manage bottle formats and standard liquid volumes (`manageFormats.php`).
- `add_bottle`: Add new bottles to the cellar (`manageBottles.php`).
- `edit_bottle`: Update bottle details, drink windows, and statuses (`manageBottles.php`).
- `add_order`: Create wine purchasing orders (`manageOrders.php?action=create`).
- `manage_orders`: Manage open wine orders and accept deliveries into cellar bins (`manageOrders.php`).

### Wines & Masters
- `browse_wines`: Search and view wines in the backend database (`browseWines.php`).
- `add_wine`: Add new individual wine vintage entries (`manageWines.php`).
- `edit_wine`: Edit individual wine vintage details (`manageWines.php`).
- `add_wine_master`: Create new wine master profiles (`manageWineMasters.php`).
- `edit_wine_master`: Edit wine master profiles and naming conventions (`manageWineMasters.php`).
- `manage_varieties`: Manage grape varieties and c&eacute;page profiles (`manageVarieties.php`).
- `manage_vintages`: Manage harvest vintages and regional character reports (`manageVintages.php`).
- `manage_wine_attributes`: Manage wine styles, colours, and display templates (`manageWineAttributes.php`).

### Geography & Producers
- `manage_producers`: Add and edit wine producers (`manageProducers.php`).
- `manage_countries`: Add and edit countries (`manageCountries.php`).
- `manage_regions`: Add and edit regions (`manageRegions.php`).
- `manage_subregions`: Add and edit subregions (`manageSubregions.php`).
- `manage_appellations`: Add and edit appellations (`manageAppellations.php`).
- `manage_vineyards`: Add and edit vineyards (`manageVineyards.php`).

### Administration
- `manage_users`: Add and edit user accounts (`manageUsers.php`).
- `manage_rating_scales`: Configure qualitative score descriptors for rating scales (`manageRatingScales.php`).
- `manage_static_pages`: Edit site copy, legal notices, homepage welcome blocks, and section sidebars (`manageStaticPages.php`).
- `manage_privileges`: Configure role permissions, custom roles, and user privilege overrides (`managePrivileges.php`).

---

## 3. Privilege Administration & User Overrides

Administrators can manage baseline role permissions, introduce custom roles, and fine-tune per-user privilege overrides:

- **Navigate to:** Top navigation `Admin > User & role privileges` (`/backend/managePrivileges.php`).
- **Management Tabs:**
  1. **User Privileges (`?tab=user`):** Select any user account, view their effective permissions, and assign individual overrides:
     - `Inherit`: Inherit the default grant or denial from their assigned base role.
     - `Grant`: Explicitly grant the privilege regardless of base role.
     - `Deny`: Explicitly deny the privilege regardless of base role.
  2. **Role Privileges (`?tab=role`):** Select any system or custom role to toggle default privilege grants on or off.
  3. **Create Custom Role (`?tab=create_role`):** Establish custom roles (e.g. *Cellar Assistant*, *Sommelier Intern*) tailored to your specific household or group requirements.
