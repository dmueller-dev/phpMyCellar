-- ==============================================================================
-- phpMyCellar Database Initial Seed Data
-- ==============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET foreign_key_checks = 0;

-- --------------------------------------------------------
-- Default Site Settings
-- --------------------------------------------------------

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('site_name', 'phpMyCellar', 'general'),
('site_tagline', 'Fine Wine Cellar & Tasting Notes', 'general'),
('site_url', 'http://localhost:8000', 'general'),
('owner_name', 'Cellar Master', 'general'),
('owner_email', 'cellar@example.com', 'general'),
('currency_symbol', '€', 'general'),
('rating_scale', '20-point', 'general'),
('meta_description', 'Personal wine cellar management, tasting notes, and ratings notebook.', 'general'),
('theme_accent_color', '#7B1113', 'theme'),
('theme_accent_hover', '#5c0d0e', 'theme'),
('logo_url', '/img/logo_web.webp', 'theme');

-- --------------------------------------------------------
-- Default Static Pages
-- --------------------------------------------------------

INSERT INTO `static_pages` (`page_key`, `page_title`, `page_content`, `meta_description`) VALUES
('welcome', 'Welcome to phpMyCellar', '<p>Welcome to <strong>phpMyCellar</strong>! This is an open-source fine wine cellar notebook and management system. Here, you can catalog your wine bottles, keep track of storage locations and orders, and record thorough tasting notes and ratings.</p><p>You can edit this content directly from your admin backend under <em>Site Settings &gt; Static Pages</em>.</p>', 'Welcome to phpMyCellar fine wine notebook.'),
('impressum', 'Impressum / Imprint', '<section><h3>Impressum / Imprint</h3><p>Information pursuant to legal notice requirements:</p><address><strong>Website Owner:</strong><br>Cellar Master<br>Sample Street 1<br>12345 City<br>Country<br><br>E-Mail: cellar@example.com</address></section>', 'Legal notice and imprint.'),
('privacy', 'Privacy Policy', '<section><h3>Privacy Policy</h3><p>This website uses a session cookie only when an authorised user logs in. The session cookie is essential for authentication and ensures you remain logged in during your session. The cookie does not store any personal information and is automatically deleted when you close your browser.</p><p><strong>No</strong> cookies or tracking scripts are used for non-logged-in visitors. We do not use third-party analytics, advertisements, or tracking services.</p><p>For registered members, only minimal personal information (name, email address) is stored on the server for authentication and notification purposes.</p></section>', 'Privacy policy for phpMyCellar.'),
('rating_guide', 'Wine Rating & Evaluation Guide', '<section><h3>Our Rating Philosophy</h3><p>We evaluate wines using an independent 20-point scoring system and the WSET systematic approach to tasting. The aim is to assess balance, intensity, length, complexity, and ageability with transparency and consistency.</p></section>', 'Guide explaining our wine tasting rating scale and methodology.');

-- --------------------------------------------------------
-- Roles & Dynamic Privileges
-- --------------------------------------------------------

INSERT INTO `roles` (`role_name`, `display_name`, `description`, `is_system`) VALUES
('admin', 'Administrator', 'Full system access and user/privilege administration', 1),
('public', 'Public / Guest (Logged Out)', 'Unauthenticated visitors to the website', 1),
('read', 'Reader / Member', 'Registered members who can read notes, stories, and participate in discussions', 1),
('write', 'Contributor / Writer', 'Contributors who can write tasting notes and stories in addition to reading', 1);

INSERT INTO `privileges` (`privilege_code`, `privilege_name`, `category`, `description`, `is_admin_only`, `sort_order`) VALUES
('view_tnotes', 'View Tasting Notes & Vintages', 'Viewing & Reading', 'Browse and read tasting notes and vintage reports', 0, 10),
('view_stories', 'Read Stories / Blog', 'Viewing & Reading', 'Read full blog posts and stories', 0, 20),
('view_comments', 'View Comments', 'Viewing & Reading', 'View discussions and comments across the site', 0, 30),
('view_cellar_menu', 'View Carte des vins', 'Viewing & Reading', 'Access the interactive cellar menu for friends', 0, 40),
('post_comments', 'Post Comments', 'Community', 'Submit comments on tasting notes, stories, and wines', 0, 50),
('add_tasting_note', 'Write Tasting Notes', 'Tasting Notes', 'Create regular and blind tasting notes', 0, 60),
('edit_tasting_note', 'Edit Own Tasting Notes', 'Tasting Notes', 'Edit tasting notes created by yourself', 0, 70),
('edit_all_tasting_notes', 'Edit All Tasting Notes', 'Tasting Notes', 'Edit tasting notes created by any user', 0, 80),
('publish_tasting_note', 'Publish Tasting Notes', 'Tasting Notes', 'Publish tasting notes directly (without forcing draft status)', 0, 90),
('add_blogpost', 'Write Stories', 'Blog Stories', 'Create new blog stories and articles', 0, 100),
('edit_blogpost', 'Edit Own Stories', 'Blog Stories', 'Edit stories created by yourself', 0, 110),
('edit_all_blogposts', 'Edit All Stories', 'Blog Stories', 'Edit stories created by any author', 0, 120),
('publish_blogpost', 'Publish Stories', 'Blog Stories', 'Publish stories directly (without forcing draft status)', 0, 130),
('browse_bottles', 'Browse Bottles', 'Cellar & Orders', 'View bottles and storage locations in backend', 0, 140),
('add_bottle', 'Add Bottle', 'Cellar & Orders', 'Add new bottles to the cellar', 0, 150),
('edit_bottle', 'Edit Bottle', 'Cellar & Orders', 'Update bottle details, drink windows, and status', 0, 160),
('add_order', 'Create Order', 'Cellar & Orders', 'Create wine purchasing orders', 0, 170),
('manage_orders', 'Manage Orders', 'Cellar & Orders', 'Manage open wine orders and accept deliveries', 0, 180),
('browse_wines', 'Browse Wines', 'Wines & Masters', 'Search and view wines in the backend database', 0, 190),
('add_wine', 'Add Wine', 'Wines & Masters', 'Add new individual wine vintage entries', 0, 200),
('edit_wine', 'Edit Wine', 'Wines & Masters', 'Edit individual wine vintage details', 0, 210),
('add_wine_master', 'Add Wine Master', 'Wines & Masters', 'Create new wine master profiles', 0, 220),
('edit_wine_master', 'Edit Wine Master', 'Wines & Masters', 'Edit wine master profiles and naming conventions', 0, 230),
('manage_producers', 'Manage Producers', 'Geography & Producers', 'Add and edit wine producers', 0, 240),
('manage_countries', 'Manage Countries', 'Geography & Producers', 'Add and edit countries', 0, 250),
('manage_regions', 'Manage Regions', 'Geography & Producers', 'Add and edit regions', 0, 260),
('manage_subregions', 'Manage Subregions', 'Geography & Producers', 'Add and edit subregions', 0, 270),
('manage_appellations', 'Manage Appellations', 'Geography & Producers', 'Add and edit appellations', 0, 280),
('manage_vineyards', 'Manage Vineyards', 'Geography & Producers', 'Add and edit vineyards', 0, 290),
('manage_users', 'Manage Users', 'Administration', 'Add and edit user accounts', 1, 300),
('manage_privileges', 'Manage Privileges', 'Administration', 'Configure role permissions and user privilege overrides', 1, 310);

INSERT INTO `role_privileges` (`role_name`, `privilege_code`) VALUES
('admin', 'add_blogpost'),
('admin', 'add_bottle'),
('admin', 'add_order'),
('admin', 'add_tasting_note'),
('admin', 'add_wine'),
('admin', 'add_wine_master'),
('admin', 'browse_bottles'),
('admin', 'browse_wines'),
('admin', 'edit_all_blogposts'),
('admin', 'edit_all_tasting_notes'),
('admin', 'edit_blogpost'),
('admin', 'edit_bottle'),
('admin', 'edit_tasting_note'),
('admin', 'edit_wine'),
('admin', 'edit_wine_master'),
('admin', 'manage_appellations'),
('admin', 'manage_countries'),
('admin', 'manage_orders'),
('admin', 'manage_privileges'),
('admin', 'manage_producers'),
('admin', 'manage_regions'),
('admin', 'manage_subregions'),
('admin', 'manage_users'),
('admin', 'manage_vineyards'),
('admin', 'post_comments'),
('admin', 'publish_blogpost'),
('admin', 'publish_tasting_note'),
('admin', 'view_cellar_menu'),
('admin', 'view_comments'),
('admin', 'view_stories'),
('admin', 'view_tnotes'),
('public', 'view_stories'),
('public', 'view_tnotes'),
('read', 'post_comments'),
('read', 'view_cellar_menu'),
('read', 'view_comments'),
('read', 'view_stories'),
('read', 'view_tnotes'),
('write', 'add_blogpost'),
('write', 'add_tasting_note'),
('write', 'edit_blogpost'),
('write', 'edit_tasting_note'),
('write', 'post_comments'),
('write', 'view_cellar_menu'),
('write', 'view_comments'),
('write', 'view_stories'),
('write', 'view_tnotes');

-- --------------------------------------------------------
-- Lookups: Display Options, Colours, Styles, Formats & Sizes
-- --------------------------------------------------------

INSERT INTO `displayoptions` (`nameconvention`, `description`) VALUES
('vintage_name', 'E.g. 2014 Le Marquis de Garraud'),
('vintage_producer', 'E.g. 2018 Château Siran'),
('vintage_producer_grape_name', 'E.g. 2017 Schloss Johannisberg Riesling Bronzelack trocken'),
('vintage_producer_name', 'E.g. 2018 Markus Molitor Haus Klosterberg Cuvée Maximilian'),
('vintage_producer_vineyard_grape_name', 'E.g. 2018 Markus Molitor Ürziger Würzgarten Riesling Kabinett (Goldkapsel)'),
('vintage_producer_vineyard_name', 'E.g. 2005 Joh. Jos. Prüm Wehlener Sonnenuhr Spätlese');

INSERT INTO `colours` (`colour`) VALUES
('orange'),
('red'),
('rosé'),
('white');

INSERT INTO `styles` (`style`, `style_desc`) VALUES
('fortified', 'Fortified wines such as Port, Sherry, and Madeira'),
('sparkling', 'Traditional method or Charmat method sparkling wines'),
('still (dry)', 'Dry still wines (<4g/l residual sugar or up to 9g/l with balanced acidity)'),
('still (off-dry)', 'Off-dry / demi-sec wines with perceptible residual sweetness'),
('still (sweet)', 'Dessert and noble sweet wines (late harvest, botrytis, icewine)');

INSERT INTO `sizes` (`size`) VALUES
('375ml'),
('500ml'),
('750ml'),
('1000ml'),
('1500ml'),
('3000ml');

INSERT INTO `bottle_formats` (`format`, `format_desc`) VALUES
('375ml', 'half bottle'),
('500ml', 'half-litre bottle'),
('750ml', 'standard bottle'),
('1000ml', 'one-litre bottle'),
('1500ml', 'magnum'),
('3000ml', 'double magnum/Jéroboam'),
('6000ml', 'Impériale/Methuselah'),
('9000ml', 'Salmanazar'),
('12000ml', 'Balthazar'),
('15000ml', 'Nebuchadnezzar'),
('18000ml', 'Melchior'),
('20000ml', 'Solomon'),
('27000ml', 'Goliath/Primat');

-- --------------------------------------------------------
-- Rating Scale Descriptions
-- --------------------------------------------------------

INSERT INTO `dmpts` (`pts`, `dmpts_desc`, `dmpts_class`) VALUES
(0, 'Wines with a DM0 rating have serious faults or impurities that stand in the way of any enjoyment. An undrinkable wine.', 'poor'),
(1, 'Wines with a DM1 to DM2 rating are subpar on a fine wine level. They have no technical flaws, but are one-dimensional or lack balance.', 'subpar'),
(2, 'Wines with a DM1 to DM2 rating are subpar on a fine wine level. They have no technical flaws, but are one-dimensional or lack balance.', 'subpar'),
(3, 'Wines with a DM3 to DM4 rating are average on a fine wine level. Well-made without apparent flaws, but lacking distinct intensity or depth.', 'passable'),
(4, 'Wines with a DM3 to DM4 rating are average on a fine wine level. Well-made without apparent flaws, but lacking distinct intensity or depth.', 'passable'),
(5, 'Wines with a DM5 to DM8 rating are good wines. Pure, balanced, and expressive of grape variety or regional typicity.', 'good'),
(6, 'Wines with a DM5 to DM8 rating are good wines. Pure, balanced, and expressive of grape variety or regional typicity.', 'good'),
(7, 'Wines with a DM5 to DM8 rating are good wines. Pure, balanced, and expressive of grape variety or regional typicity.', 'good'),
(8, 'Wines with a DM5 to DM8 rating are good wines. Pure, balanced, and expressive of grape variety or regional typicity.', 'good'),
(9, 'Wines with a DM9 to DM12 rating are very good wines, showing complexity, distinctive terroir character, and great balance.', 'very good'),
(10, 'Wines with a DM9 to DM12 rating are very good wines, showing complexity, distinctive terroir character, and great balance.', 'very good'),
(11, 'Wines with a DM9 to DM12 rating are very good wines, showing complexity, distinctive terroir character, and great balance.', 'very good'),
(12, 'Wines with a DM9 to DM12 rating are very good wines, showing complexity, distinctive terroir character, and great balance.', 'very good'),
(13, 'Wines with a DM13 to DM16 rating are excellent wines. Impressive harmony, remarkable depth, and high aging potential.', 'excellent'),
(14, 'Wines with a DM13 to DM16 rating are excellent wines. Impressive harmony, remarkable depth, and high aging potential.', 'excellent'),
(15, 'Wines with a DM13 to DM16 rating are excellent wines. Impressive harmony, remarkable depth, and high aging potential.', 'excellent'),
(16, 'Wines with a DM13 to DM16 rating are excellent wines. Impressive harmony, remarkable depth, and high aging potential.', 'excellent'),
(17, 'Wines with a DM17 to DM19 rating are grand wines amongst the finest in the world, showing breathtaking complexity and longevity.', 'grand vin'),
(18, 'Wines with a DM17 to DM19 rating are grand wines amongst the finest in the world, showing breathtaking complexity and longevity.', 'grand vin'),
(19, 'Wines with a DM17 to DM19 rating are grand wines amongst the finest in the world, showing breathtaking complexity and longevity.', 'grand vin'),
(20, 'A monumental, flawless masterpiece of wine history.', 'grand vin');

INSERT INTO `wsetpts` (`pts`, `wset_desc`) VALUES
(0.0, 'Wines with a 0.0 WSET rating are not enjoyable on any level (poor).'),
(0.5, 'Lower end of acceptable; achieves at least a half point across Balance, Intensity, Length, and Complexity (BILC).'),
(1.0, 'Average wine; achieves 1 point across BILC criteria.'),
(1.5, 'Between average and good across BILC criteria.'),
(2.0, 'Good wine with solid balance, pleasant fruit intensity, and typicity.'),
(2.5, 'Good to very good wine with notable complexity or finish.'),
(3.0, 'Very good wine; high standard across BILC criteria.'),
(3.5, 'Very good to outstanding wine, displaying great expression of terroir.'),
(4.0, 'Outstanding wine; exceptional balance, intensity, length, and complexity.');

-- --------------------------------------------------------
-- Grape Varieties
-- --------------------------------------------------------

INSERT INTO `variety` (`grape`, `grape_desc`) VALUES
('Albariño', 'Aromatic white grape variety originating from Galicia in north-western Spain and northern Portugal (Alvarinho).'),
('Aligoté', 'White grape variety found predominantly in Burgundy. Typically dry, crisp, and fresh with vibrant acidity.'),
('Assyrtiko', 'Indigenous Greek white grape famed for high natural acidity and mineral tension, especially on Santorini.'),
('Barbera', 'Classic red grape of Piedmont, Italy. Vibrant acidity, bright red cherry fruit, and supple tannins.'),
('Blaufränkisch', 'Prestigious Central European red variety producing structured, peppery, age-worthy wines with dark fruit.'),
('Cabernet Blanc', 'Fungus-resistant PIWI white variety with expressive Sauvignon-like aromatics.'),
('Cabernet Carbon', 'Fungus-resistant PIWI red variety developed for cool climate resilience.'),
('Cabernet Cortis', 'Deeply coloured, tannic PIWI red variety with Cabernet Sauvignon lineage.'),
('Cabernet Franc', 'Classic parent of Cabernet Sauvignon and Merlot, offering leafy, violet, and red currant complexity.'),
('Cabernet Sauvignon', 'The world’s benchmark red grape for structured, cassis-scented, long-lived fine wines.'),
('Carménère', 'Historic Bordeaux variety now thriving in Chile, prized for its savoury paprika, cocoa, and plush plum character.'),
('Chardonnay', 'The noble white grape of Burgundy and Champagne, capable of extraordinary minerality, richness, and elegance.'),
('Chasselas', 'Delicate, terroir-reflective white variety traditional in Switzerland, Alsace, and Baden (Gutedel).'),
('Chelva', 'Traditional Iberian white variety producing crisp, lighter white wines.'),
('Chenin Blanc', 'Versatile noble white grape of the Loire Valley and South Africa, ranging from bone dry to luscious sweet.'),
('Cortese', 'Crisp, mineral white grape from Piedmont, most famous for Gavi DOCG.'),
('Corvina', 'Key red grape in Veneto for Valpolicella and Amarone della Valpolicella.'),
('Fiano', 'Ancient southern Italian white grape producing aromatic, textured, age-worthy wines in Campania.'),
('Furmint', 'Noble Hungarian white grape famous for Tokaji sweet wines as well as vibrant, volcanic dry whites.'),
('Gamay', 'Expressive red grape of Beaujolais, yielding juicy, floral, and vibrant wines.'),
('Garganega', 'Delicate, almond-scented white variety behind the great dry whites of Soave in the Veneto.'),
('Gewürztraminer', 'Highly aromatic, lychee and rose-perfumed white grape of Alsace, Germany, and Alto Adige.'),
('Grenache', 'World-class red grape of the southern Rhône (Châteauneuf-du-Pape) and Spain (Garnacha).'),
('Grüner Veltliner', 'Austria’s signature white grape, renowned for white pepper, citrus freshness, and ageability.'),
('Lagrein', 'Indigenous red grape of Alto Adige, producing deeply coloured, velvet-textured wines with plum and chocolate notes.'),
('Malbec', 'Dark, rich red grape celebrated in Cahors and Argentina for its plush blackberry and spice.'),
('Merlot', 'Plush, generous noble red grape central to the great right bank wines of Bordeaux.'),
('Meunier', 'Traditional Champagne red variety providing fruitiness, roundness, and approachable charm.'),
('Monarch', 'Fungus-resistant PIWI red grape variety.'),
('Mourvèdre', 'Sturdy, savoury, and gamey red grape of Bandol and the southern Rhône (Monastrell in Spain).'),
('Müller-Thurgau', 'Early-ripening, floral white cross popular throughout Germany, Austria, and northern Italy.'),
('Muscat', 'Ancient aromatic family of grapes with exuberant grapey, floral, and citrus aromas.'),
('Nebbiolo', 'The noble Italian grape behind Barolo and Barbaresco, famed for tar, roses, ethereal aromas, and profound tannins.'),
('Nero d\'Avola', 'Sicily’s primary red variety, known for juicy dark cherry, plum, and warm Mediterranean spice.'),
('Petit Verdot', 'Intensely coloured, spicy, and structured blending component in classic Bordeaux reds.'),
('Pinot Blanc', 'Round, gentle white member of the Pinot family with orchard fruit flavours (Weissburgunder).'),
('Pinot Gris', 'Rich, textured white variety (Grauburgunder / Pinot Grigio) producing versatile dry and off-dry styles.'),
('Pinot Noir', 'The incomparable noble red grape of Burgundy, delivering ethereal elegance, red berries, and earthy complexity.'),
('Pinot Précoce Noir', 'Early-ripening mutation of Pinot Noir (Frühburgunder) cultivated in Germany and cool regions.'),
('Pinotage', 'South African cross between Pinot Noir and Cinsault, producing bold, smoky dark fruit reds.'),
('Primitivo/Zinfandel', 'Rich, brambly, sun-drenched red variety celebrated in Puglia and California.'),
('Prior', 'Fungus-resistant PIWI red grape variety.'),
('Riesling', 'One of the world’s greatest white grapes: unmatched precision, crystalline acidity, and longevity across dry and sweet styles.'),
('Sangiovese', 'The soul of Tuscany: high acidity, firm tannins, and sour cherry flavours in Chianti Classico and Brunello.'),
('Sauvignon Blanc', 'Zesty, aromatic white grape renowned in the Loire Valley, Bordeaux (blended with Sémillon), and New Zealand.'),
('Scheurebe', 'Aromatic German white cross offering exuberant blackcurrant leaf, grapefruit, and exotic fruit.'),
('Schiava/Trollinger/Vernatsch', 'Light, refreshing, red-berried alpine red variety traditional in Alto Adige and Württemberg.'),
('Sémillon', 'Waxy, honeyed white grape essential to great dry white Graves and luscious Sauternes.'),
('Silvaner', 'Subtle, earthy, and food-friendly white grape prized in Franken and Alsace.'),
('St. Laurent', 'Aromatic, silky Austrian red variety related to Pinot Noir.'),
('Syrah', 'The noble red grape of the northern Rhône (Hermitage, Côte-Rôtie) and Australia (Shiraz).'),
('Tannat', 'Deeply coloured, powerfully tannic red variety of Madiran and Uruguay.'),
('Tempranillo', 'Spain’s flagship noble red grape, forming the backbone of Rioja and Ribera del Duero.'),
('Verdejo', 'Aromatic, herbaceous white grape of Rueda in Spain with refreshing citrus and fennel notes.'),
('Verdicchio/Turbiana', 'High-acid, mineral white grape of the Marche and Lake Garda (Lugana) in Italy.'),
('Viognier', 'Opulent, apricot- and honeysuckle-scented white grape of Condrieu in the northern Rhône.'),
('Zweigelt', 'Austria’s most widespread red grape, producing bright cherry-scented, spicy wines.');

-- --------------------------------------------------------
-- Key Wine Countries
-- --------------------------------------------------------

INSERT INTO `countries` (`country`) VALUES
('Argentina'),
('Australia'),
('Austria'),
('Belgium'),
('Chile'),
('France'),
('Germany'),
('Greece'),
('Hungary'),
('Italy'),
('New Zealand'),
('Portugal'),
('South Africa'),
('South Korea'),
('Spain'),
('Switzerland'),
('United Kingdom'),
('United States');

SET foreign_key_checks = 1;
