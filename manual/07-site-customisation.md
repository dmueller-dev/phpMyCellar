# 07. Site Customisation & Static Pages

This guide outlines how to customise branding, theme colours, and manage static pages such as *Welcome*, *Impressum*, and *Privacy Policy*.

---

## 1. Website & Theme Settings

Administrators can adjust branding elements in real time without editing source files.

- **Navigate to:** Top navigation `Admin > Site settings` (`/backend/settings.php`) or `Admin > Admin Hub > System & Settings > Site settings & branding`.
- **Configurable Options:**
  - **Site Name:** The main title of your cellar (e.g. *phpMyCellar*).
  - **Tagline:** Subtitle appearing in headings and meta descriptions.
  - **Site Base URL:** Canonical domain URL for links and OpenGraph sharing.
  - **Owner Name & Email:** Contact details displayed in legal notices and notifications.
  - **Currency Symbol:** Default currency used for valuation calculations (e.g. `€`, `$`, `£`, `CHF`).
  - **Rating Scale:** Preferred primary scoring methodology (`20-point` or `100-point`).
  - **WSET SAT Assessment Mode:** Configure WSET Systematic Approach to Tasting evaluation mode (`Public`, `Logged In`, `Backend Only`, or `Disabled`).
  - **WSET Display Format:** Choose between `Standard` (overall score and qualitative level) or `Detailed` (overall score plus Balance, Length, Intensity, Complexity criteria breakdown).
  - **Carte des Vins — Unready Wines Visibility:** Choose whether wines whose minimum drinking window year (`Drink from`) is in the future are hidden (`Exclude`, default) or displayed on the public wine menu with an ageing clock icon (`Include`).
  - **Theme Accent Colours:** Primary (`#CD5C5C`), secondary (`#B22222`), and hover/active accents (`#8B0000`) dynamically injected across navigation menus and buttons.
  - **Logo URL:** Path to your custom header logo image.

---

## 2. Managing Static Pages & Notices

phpMyCellar stores core static pages and section sidebar notices directly in the database (`static_pages` table) with full WYSIWYG editing capabilities.

- **Navigate to:** `Admin > Admin Hub > System & Settings > Static content pages` (`/backend/manageStaticPages.php`).
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

---

## 3. Wine Styles, Colours & Display Naming Templates

Customise the core taxonomic attributes that classify your wine catalogue:
- **Navigate to:** `Admin > Admin Hub > Wine Catalogue & Masters > Wine styles & attributes` (`/backend/manageWineAttributes.php`).
- **Wine Styles (`styles`):** Manage styles (e.g. `still (dry)`, `still (sweet)`, `sparkling`, `fortified`). Safe deletion checks prevent deleting styles currently assigned to active wine masters.
- **Wine Colours (`colours`):** Manage wine colours (`red`, `white`, `ros&eacute;`, `orange`). Active colour assignments are protected against deletion.
- **Display Options (`displayoptions`):** Configure wine title assembly templates used across navigation, catalogue indexes, and headings (e.g. `[Vintage] [Producer] [Vineyard] [Name]`).

---

## 4. Rating Scales & Qualitative Assessment Descriptors

Tailor the descriptive language and qualitative score tiers used in tasting notes and vintage charts:
- **Navigate to:** `Admin > Admin Hub > System & Settings > Rating scales & assessment` (`/backend/manageRatingScales.php`).
- **20-Point Scale Descriptors (`scale_20`):** Edit qualitative labels for each point integer (0–20) on the European scale (e.g. *19–20: Outstanding, legendary wine*).
- **100-Point Scale Descriptors (`scale_100`):** Customise score ranges and qualitative summaries on the 100-point American/international scale (e.g. *95–100: Classic: a great wine*).
- **WSET Qualitative Criteria (`wsetpts`):** Customise criteria definitions for the WSET Systematic Approach to Tasting levels (*Poor*, *Acceptable*, *Good*, *Very Good*, *Outstanding*).
