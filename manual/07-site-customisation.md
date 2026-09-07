# 07. Site Customisation & Static Pages

This guide outlines how to customise branding, theme colours, and manage static pages such as *Welcome*, *Impressum*, and *Privacy Policy*.

---

## 1. Website & Theme Settings

Administrators can adjust branding elements in real time without editing source files.

- **Navigate to:** Top navigation `Admin > Site settings` (`/backend/settings.php`) or `Admin > Dashboard > Administration > Site settings`.
- **Configurable Options:**
  - **Site Name:** The main title of your cellar (e.g. *phpMyCellar*).
  - **Tagline:** Subtitle appearing in headings and meta descriptions.
  - **Site Base URL:** Canonical domain URL for links and OpenGraph sharing.
  - **Owner Name & Email:** Contact details displayed in legal notices and notifications.
  - **Currency Symbol:** Default currency used for valuation calculations (e.g. `€`, `$`, `£`, `CHF`).
  - **Rating Scale:** Preferred primary scoring methodology (`20-point` or `100-point`).
  - **WSET SAT Assessment Mode:** Configure WSET Systematic Approach to Tasting evaluation mode (`Public`, `Logged In`, `Backend Only`, or `Disabled`).
  - **WSET Display Format:** Choose between `Standard` (overall score and qualitative level) or `Detailed` (overall score plus Balance, Length, Intensity, Complexity criteria breakdown).
  - **Theme Accent Colours:** Primary (`#CD5C5C`), secondary (`#B22222`), and hover/active accents (`#8B0000`) dynamically injected across navigation menus and buttons.
  - **Logo URL:** Path to your custom header logo image.

---

## 2. Managing Static Pages & Notices

phpMyCellar stores core static pages and section sidebar notices directly in the database (`static_pages` table) with full WYSIWYG editing capabilities.

- **Navigate to:** Top navigation `Admin > Static pages` (`/backend/manageStaticPages.php`) or `Admin > Dashboard > Administration > Static pages`.
- **Editable Content Areas:**
  - **Full Informational Pages:**
    - **Impressum (`impressum`):** Mandatory legal notice, operator details, postal address, and contact information (`/impressum.php`).
    - **Privacy Policy (`privacy`):** Data privacy declaration, session cookie policy, and user rights (`/privacy.php`).
  - **Homepage Content Cards:**
    - **Welcome Card (`welcome`):** Main introductory narrative displayed on the homepage (`/index.php`).
    - **Get in Touch Card (`get_in_touch`):** Contact invitation and cellar notebook access request details on the homepage (`/index.php`).
  - **Section Sidebars & Notices:**
    - **Tasting Notes Sidebar (`tnotes_sidebar`):** Introduction and 20-point rating philosophy guide shown on the tasting notes index (`/tnotes.php`).
    - **Wine Database Notice (`wines_sidebar`):** Notice clarifying that listed wines are bottles reviewed across notes and stories (`/wines.php`).
    - **Wine Menu Invitation (`winemenu_sidebar`):** Invitation welcoming guests to select and open ready cellar bottles (`/winemenu.php`).
    - **Vintage Reports Notice (`vintages_sidebar`):** Explanatory note on how vintage scores and reports are dynamically compiled (`/vintages.php`).
    - **Stories Sidebar (`blog_sidebar`):** Introduction to tastings, horizontals/verticals, and travel articles (`/blog.php`).

### Editing a Static Page:
1. Click **Edit** next to the desired page key in `/backend/manageStaticPages.php`.
2. Update the Page Title, Meta Description (for SEO), and Page Content using the WYSIWYG editor.
3. Click **Save Changes** to publish immediately.
