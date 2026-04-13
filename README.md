# dmueller-com
Personal wine database

## Fix
### 1. Dropdown menu doesn't close on mobile
1.1 Ignore hover on mobile (open/close on click)

### 2. 'Write' users must be able to edit their own notes
2.1 Give access to editTastingNote.php - only where user_id matches

2.2 Give access to blind tasting note

2.3 Include edit/blind tasting note in 'Contribute' menu

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
