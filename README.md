# dmueller-com
Personal wine database

## Fix
None

## Improve
### 1. Favourite wine
1.1 Turn five-star wines into favourite wines

1.2 Add filter to tasting notes and wine menu

## Add
### 1. Fuzzy search
1.1 Integrate into wine DB, tasting notes, and wine menu (PHP / MySQL)

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

### 7. Allow selection of rating system
7.1 Let admin choose which rating system to use (20 vs 100 points)

7.2 Let admin turn additional five-star system on/off

### 8. Add user initials to database table
8.1 Initials to display before points

### 9. Import Liv-Ex LWIN dataset
9.1 Add LWIN7 ID to 'wines_master' table

9.2 Add LWIN11 ID to 'wines' table

9.3 Import data for comprehensive wine database
