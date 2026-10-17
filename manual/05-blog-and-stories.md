# 05. Stories, Articles & Image Publishing

phpMyCellar features a built-in editorial publishing engine for writing wine articles, vintage reports, tasting flights, and travel logs.

---

## 1. Writing Articles

- **Navigate to:** Top navigation `Contribute > Write story` (`/backend/manageBlogposts.php`) or `Admin > Admin Hub > Tasting Notes & Editorial > Write new story`.
- **Key Fields:**
  - **Title:** Headline for the story or tasting report.
  - **Publication Date:** Date of article release (`pub_date`).
  - **Publication Status:** Save as `draft` or `publish` directly (depending on author privileges).
  - **Article Content:** Rich-text narrative formatted using the integrated WYSIWYG editor. Stories are accessed via canonical query URLs (`/blog.php?id=<id>`).

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

## 4. Reader Interaction & Discussion Subscriptions

- Authenticated readers with the `post_comments` privilege can participate in discussion threads beneath published stories.
- **Thread Subscriptions:** Users can click **🔔 Subscribe to discussion** on any article or wine to receive instant notifications when new comments are posted.
- **Notification Preferences:** Registered users can review and manage their active thread subscriptions and toggle email delivery from **My account > Settings** (`/accountSettings.php`).

---

## 5. Comment Moderation & Discussion Control

phpMyCellar operates on a **post-moderation** model for reader interactions:
- **Immediate Publication:** Comments submitted by registered members possessing the `post_comments` privilege publish live immediately with `approved` status. Anonymous visitors cannot post comments.
- **Retrospective Moderation:** Administrators and moderators (requiring the `moderate_comments` privilege) review, edit, or remove comments after publication.
- **Navigate to:** `Admin > Admin Hub > Tasting Notes & Editorial > Moderate comments` (`/backend/manageComments.php`) or `Contribute > Moderate comments`.
- **Status Filtering:** Filter comments by moderation status: `approved` (live and visible), `pending` (awaiting manual review), or `spam` (flagged as abusive or unwanted).
- **Inline Editing & Quick Actions:** Directly update comment text to fix typographical errors or inappropriate language, change moderation statuses, or permanently delete spam entries. Deletion automatically cleans up polymorphic association records (`x_comments_*`).
