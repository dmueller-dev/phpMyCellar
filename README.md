# dmueller-com
Personal wine database

## Fix
### 1. 'Write' users must be able to edit their own notes
1.1 Give access to editTastingNote.php - only where user_id matches

1.2 Give access to blind tasting note

1.3 Include edit/blind tasting note in 'Contribute' menu

## Improve
### 1. 'Write' users only allowed to add/edit drafts
1.1 Disable status field in forms, unless 'admin' user

## Add
### 1. Fuzzy search
1.1 Integrate into wine DB and tasting notes (PHP / MySQL)

### 2. Scan OCR text via handheld device
2.1 Recognise text labels on bottle

### 3. Store site settings/content in database
3.1 Allow modifications via GUI admin panel

### 4. Installation scripts
4.1 Create (empty) database with tables, keys, constraints etc. but no values

4.2 Create .env file with user-defined credentials

4.3 Create admin user

### 5. WYSIWYG editor
5.1 Editor for HTML tags (p, em, b, h1, h2, h3 ...)

### 6. Base functionality for wine.php, producers.php, tnote.php, blogpost.php
6.1 Redirect user to wines.php if no parameter given to wine.php

6.2 List all producers if no parameter given to producers.php

6.3 Redirect user to tnotes.php if no parameter given to tnote.php

6.4 Redirect user to blog.php if no parameter given to blogpost.php
