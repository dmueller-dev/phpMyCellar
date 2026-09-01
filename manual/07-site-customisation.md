# 07. Site Customisation & Static Pages

This guide outlines how to customise branding, theme colours, and manage static pages such as *Welcome*, *Impressum*, and *Privacy Policy*.

---

## 1. Website & Theme Settings

Administrators can adjust branding elements in real time without editing source files.

- **Navigate to:** `Backend > Site Settings`.
- **Configurable Options:**
  - **Site Name:** The main title of your cellar (e.g. *phpMyCellar*).
  - **Tagline:** Subtitle appearing in headings and meta descriptions.
  - **Site Base URL:** Canonical domain URL for links and OpenGraph sharing.
  - **Owner Name & Email:** Contact details displayed in legal notices and notifications.
  - **Currency Symbol:** Default currency used for valuation calculations (e.g. `€`, `$`, `£`, `CHF`).
  - **Rating Scale:** Preferred scoring methodology.
  - **Theme Accent Colours:** Primary (`#CD5C5C`), secondary (`#B22222`), and hover/active accents (`#8B0000`) dynamically injected across navigation menus and buttons.
  - **Logo URL:** Path to your custom header logo image.

---

## 2. Managing Static Pages

phpMyCellar stores core static pages directly in the database (`static_pages` table) with full WYSIWYG editing capabilities.

- **Navigate to:** `Backend > Manage Static Pages`.
- **Editable Pages Include:**
  - **Welcome (`welcome`):** Introductory narrative on the home landing page.
  - **About the Cellar (`about`):** Detailed background on the collector, philosophy, and storage conditions.
  - **Rating Scale Guide (`ratingscale`):** Explanatory guide detailing your scoring philosophy and criteria for readers.
  - **Impressum / Legal Notice (`impressum`):** Legal disclosures and contact identification.
  - **Privacy Policy (`privacy`):** Privacy declaration regarding cookies, account data, and contact logs.

### Editing a Static Page:
1. Click **Edit** next to the desired page key.
2. Update the Page Title, Meta Description (for SEO), and Page Content using the WYSIWYG editor.
3. Click **Save Changes** to publish immediately.
