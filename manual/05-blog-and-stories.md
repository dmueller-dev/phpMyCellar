# 05. Stories, Articles & Image Publishing

phpMyCellar features a built-in editorial publishing engine for writing wine articles, vintage reports, tasting flights, and travel logs.

---

## 1. Writing Articles

- **Navigate to:** `Backend > Blog / Stories > Add Article`.
- **Key Fields:**
  - **Title:** Headline for the article.
  - **Slug / Permalink:** Clean URL identifier (e.g. `2016-bordeaux-horizontal-retrospective`).
  - **Category / Tags:** E.g. *Producer Profile*, *Vintage Report*, *Travel*.
  - **Publication Date & Status:** Draft or Published.
  - **Hero Image:** Featured banner image displayed at the top of the article.

---

## 2. Using the Integrated WYSIWYG Editor

phpMyCellar includes a lightweight, distraction-free rich-text editor (`includes/wysiwyg.js`):

- **Formatting Toolbar:**
  - Headings (`H2`, `H3`, `H4`)
  - Bold, Italic, Strikethrough, Underline
  - Blockquotes, Ordered/Unordered Lists, Horizontal Rules
  - Hyperlinks with security attributes (`target="_blank"` and `rel="noopener"`)

---

## 3. Inserting & Managing Images

Images uploaded for articles and tasting notes are stored under `uploads/img/`.

### How to Embed Images in Articles:
1. Click the **Image** icon in the WYSIWYG editor toolbar.
2. In the modal, specify the filename located in `uploads/img/` (e.g. `chateau-margaux-2015.webp`).
3. Set an accessible **Alt Text** and optional caption.
4. Choose image alignment:
   - **Inline Left:** Floats left with text wrapping.
   - **Inline Right:** Floats right with text wrapping.
   - **Block Centre:** Displays centered as a full-width figure with caption.

---

## 4. Public Reader Interaction & Email Subscriptions

- Readers can post comments on published articles.
- The administrator can moderate, edit, or delete comments via `Backend > Manage Comments`.
- Registered users can toggle email notifications in their account settings to receive updates when new articles are published.
