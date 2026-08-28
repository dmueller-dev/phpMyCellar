-- ==============================================================================
-- phpMyCellar Database Master Schema
-- Character Set: utf8mb4 / Collation: utf8mb4_unicode_ci
-- ==============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET foreign_key_checks = 0;

-- --------------------------------------------------------
-- Core Site Settings & Static Content Tables
-- --------------------------------------------------------

DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE `site_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(32) NOT NULL DEFAULT 'general',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `static_pages`;
CREATE TABLE `static_pages` (
  `page_key` varchar(64) NOT NULL,
  `page_title` varchar(255) NOT NULL,
  `page_content` longtext NOT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`page_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- User Management, Roles & Dynamic Privileges
-- --------------------------------------------------------

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `role_name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `privileges`;
CREATE TABLE `privileges` (
  `privilege_code` varchar(50) NOT NULL,
  `privilege_name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_admin_only` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`privilege_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `role_privileges`;
CREATE TABLE `role_privileges` (
  `role_name` varchar(50) NOT NULL,
  `privilege_code` varchar(50) NOT NULL,
  PRIMARY KEY (`role_name`,`privilege_code`),
  KEY `idx_rp_role` (`role_name`),
  KEY `idx_rp_priv` (`privilege_code`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_name`) REFERENCES `roles` (`role_name`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_priv` FOREIGN KEY (`privilege_code`) REFERENCES `privileges` (`privilege_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL,
  `displayname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `initials` varchar(3) NOT NULL,
  `email_notifications` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `idx_username` (`username`),
  KEY `idx_user_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_privileges`;
CREATE TABLE `user_privileges` (
  `user_id` smallint(5) UNSIGNED NOT NULL,
  `privilege_code` varchar(50) NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`user_id`,`privilege_code`),
  KEY `idx_up_user` (`user_id`),
  KEY `idx_up_priv` (`privilege_code`),
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_priv` FOREIGN KEY (`privilege_code`) REFERENCES `privileges` (`privilege_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Lookup & Master Reference Tables
-- --------------------------------------------------------

DROP TABLE IF EXISTS `displayoptions`;
CREATE TABLE `displayoptions` (
  `nameconvention` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY (`nameconvention`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `colours`;
CREATE TABLE `colours` (
  `colour` varchar(24) NOT NULL,
  PRIMARY KEY (`colour`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `styles`;
CREATE TABLE `styles` (
  `style` varchar(32) NOT NULL,
  `style_desc` text DEFAULT NULL,
  PRIMARY KEY (`style`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sizes`;
CREATE TABLE `sizes` (
  `size` varchar(32) NOT NULL,
  PRIMARY KEY (`size`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `bottle_formats`;
CREATE TABLE `bottle_formats` (
  `format` varchar(7) NOT NULL,
  `format_desc` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`format`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `variety`;
CREATE TABLE `variety` (
  `grape` varchar(255) NOT NULL,
  `grape_desc` text DEFAULT NULL,
  PRIMARY KEY (`grape`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `dmpts`;
CREATE TABLE `dmpts` (
  `pts` smallint(3) UNSIGNED NOT NULL,
  `dmpts_desc` text NOT NULL,
  `dmpts_class` tinytext NOT NULL,
  PRIMARY KEY (`pts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `wsetpts`;
CREATE TABLE `wsetpts` (
  `pts` decimal(2,1) UNSIGNED NOT NULL,
  `wset_desc` text NOT NULL,
  PRIMARY KEY (`pts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Geographic & Wine Origin Hierarchy
-- --------------------------------------------------------

DROP TABLE IF EXISTS `countries`;
CREATE TABLE `countries` (
  `country` varchar(255) NOT NULL,
  PRIMARY KEY (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `regions`;
CREATE TABLE `regions` (
  `region_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `region` varchar(255) NOT NULL,
  `country` varchar(255) NOT NULL,
  `region_desc` text DEFAULT NULL,
  PRIMARY KEY (`region_id`),
  UNIQUE KEY `idx_regions` (`region`,`country`),
  KEY `idx_reg_country` (`country`),
  CONSTRAINT `fk_regions_country` FOREIGN KEY (`country`) REFERENCES `countries` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `subregions`;
CREATE TABLE `subregions` (
  `subregion_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `subregion` varchar(255) NOT NULL,
  `region_id` smallint(5) UNSIGNED NOT NULL,
  `subregion_desc` text DEFAULT NULL,
  PRIMARY KEY (`subregion_id`),
  UNIQUE KEY `idx_subregions` (`subregion`,`region_id`),
  KEY `idx_sub_region` (`region_id`),
  CONSTRAINT `fk_subregions_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `appellations`;
CREATE TABLE `appellations` (
  `appellation_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `appellation` varchar(255) NOT NULL,
  `region_id` smallint(5) UNSIGNED NOT NULL,
  `appellation_desc` text DEFAULT NULL,
  PRIMARY KEY (`appellation_id`),
  UNIQUE KEY `idx_appellations` (`appellation`,`region_id`),
  KEY `idx_app_region` (`region_id`),
  CONSTRAINT `fk_appellations_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vineyards`;
CREATE TABLE `vineyards` (
  `vineyard_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `vineyard` varchar(255) NOT NULL,
  `region_id` smallint(5) UNSIGNED NOT NULL,
  `appellation_id` smallint(5) UNSIGNED DEFAULT NULL,
  `vineyard_desc` text DEFAULT NULL,
  PRIMARY KEY (`vineyard_id`),
  UNIQUE KEY `idx_vineyards` (`vineyard`,`region_id`,`appellation_id`),
  KEY `idx_vy_region` (`region_id`),
  KEY `idx_vy_appellation` (`appellation_id`),
  CONSTRAINT `fk_vineyards_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`region_id`),
  CONSTRAINT `fk_vineyards_appellation` FOREIGN KEY (`appellation_id`) REFERENCES `appellations` (`appellation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `producers`;
CREATE TABLE `producers` (
  `producer_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `producer` varchar(255) NOT NULL,
  `region_id` smallint(5) UNSIGNED NOT NULL,
  `producer_desc` text DEFAULT NULL,
  PRIMARY KEY (`producer_id`),
  UNIQUE KEY `idx_producers` (`producer`,`region_id`),
  KEY `idx_prod_region` (`region_id`),
  CONSTRAINT `fk_producers_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vintages`;
CREATE TABLE `vintages` (
  `vintage` smallint(4) UNSIGNED NOT NULL,
  PRIMARY KEY (`vintage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `x_vintage_region`;
CREATE TABLE `x_vintage_region` (
  `vintage` smallint(4) UNSIGNED NOT NULL,
  `region_id` smallint(5) UNSIGNED NOT NULL,
  `vintage_desc` text NOT NULL,
  UNIQUE KEY `idx_vintage_region` (`vintage`,`region_id`),
  KEY `idx_xvr_region` (`region_id`),
  CONSTRAINT `fk_xvr_vintage` FOREIGN KEY (`vintage`) REFERENCES `vintages` (`vintage`),
  CONSTRAINT `fk_xvr_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Wine Catalog & Inventory Structure
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wines_master`;
CREATE TABLE `wines_master` (
  `master_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `nameconvention` varchar(255) NOT NULL,
  `producer_id` smallint(5) UNSIGNED NOT NULL,
  `region_id` smallint(5) UNSIGNED NOT NULL,
  `subregion_id` smallint(5) UNSIGNED DEFAULT NULL,
  `appellation_id` smallint(5) UNSIGNED DEFAULT NULL,
  `vineyard_id` smallint(5) UNSIGNED DEFAULT NULL,
  `grape` varchar(255) NOT NULL,
  `colour` varchar(24) NOT NULL,
  `style` varchar(32) NOT NULL,
  `wine_desc` text DEFAULT NULL,
  PRIMARY KEY (`master_id`),
  UNIQUE KEY `idx_master` (`name`,`producer_id`,`region_id`,`grape`,`vineyard_id`),
  KEY `idx_wm_nameconv` (`nameconvention`),
  KEY `idx_wm_producer` (`producer_id`),
  KEY `idx_wm_region` (`region_id`),
  KEY `idx_wm_subregion` (`subregion_id`),
  KEY `idx_wm_appellation` (`appellation_id`),
  KEY `idx_wm_vineyard` (`vineyard_id`),
  KEY `idx_wm_grape` (`grape`),
  KEY `idx_wm_colour` (`colour`),
  KEY `idx_wm_style` (`style`),
  CONSTRAINT `fk_wm_nameconv` FOREIGN KEY (`nameconvention`) REFERENCES `displayoptions` (`nameconvention`),
  CONSTRAINT `fk_wm_producer` FOREIGN KEY (`producer_id`) REFERENCES `producers` (`producer_id`),
  CONSTRAINT `fk_wm_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`region_id`),
  CONSTRAINT `fk_wm_subregion` FOREIGN KEY (`subregion_id`) REFERENCES `subregions` (`subregion_id`),
  CONSTRAINT `fk_wm_appellation` FOREIGN KEY (`appellation_id`) REFERENCES `appellations` (`appellation_id`),
  CONSTRAINT `fk_wm_vineyard` FOREIGN KEY (`vineyard_id`) REFERENCES `vineyards` (`vineyard_id`),
  CONSTRAINT `fk_wm_grape` FOREIGN KEY (`grape`) REFERENCES `variety` (`grape`),
  CONSTRAINT `fk_wm_colour` FOREIGN KEY (`colour`) REFERENCES `colours` (`colour`),
  CONSTRAINT `fk_wm_style` FOREIGN KEY (`style`) REFERENCES `styles` (`style`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `wines`;
CREATE TABLE `wines` (
  `wine_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `master_id` smallint(5) UNSIGNED NOT NULL,
  `vintage` smallint(4) UNSIGNED DEFAULT NULL,
  `alcohol` decimal(3,1) DEFAULT NULL,
  `drink_from` smallint(4) UNSIGNED DEFAULT NULL,
  `drink_until` smallint(4) UNSIGNED DEFAULT NULL,
  `best_after` smallint(4) UNSIGNED DEFAULT NULL,
  `peak_from` smallint(4) UNSIGNED DEFAULT NULL,
  `peak_until` smallint(4) UNSIGNED DEFAULT NULL,
  `winedata_desc` text DEFAULT NULL,
  `cepage` varchar(255) DEFAULT NULL,
  `upgrades` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`wine_id`),
  UNIQUE KEY `idx_wines` (`master_id`,`vintage`),
  KEY `idx_wines_vintage` (`vintage`),
  CONSTRAINT `fk_wines_master` FOREIGN KEY (`master_id`) REFERENCES `wines_master` (`master_id`),
  CONSTRAINT `fk_wines_vintage` FOREIGN KEY (`vintage`) REFERENCES `vintages` (`vintage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cellars`;
CREATE TABLE `cellars` (
  `cellar_id` smallint(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cellar_name` varchar(50) NOT NULL,
  `owner` smallint(5) UNSIGNED NOT NULL,
  PRIMARY KEY (`cellar_id`),
  KEY `idx_cellars_owner` (`owner`),
  CONSTRAINT `fk_cellars_owner` FOREIGN KEY (`owner`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `storageBins`;
CREATE TABLE `storageBins` (
  `bin_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `bin_name` varchar(10) NOT NULL,
  `cellar_id` smallint(3) UNSIGNED NOT NULL,
  PRIMARY KEY (`bin_id`),
  UNIQUE KEY `idxStorageBins` (`cellar_id`,`bin_name`),
  CONSTRAINT `fk_sb_cellar` FOREIGN KEY (`cellar_id`) REFERENCES `cellars` (`cellar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stores`;
CREATE TABLE `stores` (
  `store_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_name` varchar(50) NOT NULL,
  `country` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`store_id`),
  UNIQUE KEY `idxStores` (`store_name`,`country`),
  KEY `idx_stores_country` (`country`),
  CONSTRAINT `fk_stores_country` FOREIGN KEY (`country`) REFERENCES `countries` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_date` date NOT NULL,
  `order_reference` varchar(50) DEFAULT NULL,
  `store_id` smallint(5) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  KEY `idx_orders_store` (`store_id`),
  CONSTRAINT `fk_orders_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `order_documents`;
CREATE TABLE `order_documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  KEY `idx_od_order` (`order_id`),
  CONSTRAINT `fk_od_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tasting Notes & Editorial
-- --------------------------------------------------------

DROP TABLE IF EXISTS `tnotes`;
CREATE TABLE `tnotes` (
  `note_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `wine_id` smallint(5) UNSIGNED NOT NULL,
  `user_id` smallint(5) UNSIGNED NOT NULL,
  `tasting_date` date NOT NULL,
  `wsetpts` decimal(2,1) UNSIGNED DEFAULT NULL,
  `dmpts` smallint(3) UNSIGNED DEFAULT NULL,
  `flawed_yn` varchar(3) NOT NULL DEFAULT 'no',
  `note` text NOT NULL,
  `favourite` varchar(3) NOT NULL DEFAULT 'no',
  `img` varchar(255) DEFAULT NULL,
  `blind_tasting` tinyint(1) NOT NULL DEFAULT 0,
  `blind_grape` varchar(255) DEFAULT NULL,
  `blind_country` varchar(255) DEFAULT NULL,
  `blind_region` varchar(255) DEFAULT NULL,
  `blind_vintage` varchar(50) DEFAULT NULL,
  `blind_notes` text DEFAULT NULL,
  `status` varchar(9) NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`note_id`),
  UNIQUE KEY `idx_tnotes` (`wine_id`,`tasting_date`,`user_id`),
  KEY `idx_tn_wine` (`wine_id`),
  KEY `idx_tn_user` (`user_id`),
  KEY `idx_tn_wsetpts` (`wsetpts`),
  KEY `idx_tn_dmpts` (`dmpts`),
  CONSTRAINT `fk_tn_wine` FOREIGN KEY (`wine_id`) REFERENCES `wines` (`wine_id`),
  CONSTRAINT `fk_tn_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_tn_wsetpts` FOREIGN KEY (`wsetpts`) REFERENCES `wsetpts` (`pts`),
  CONSTRAINT `fk_tn_dmpts` FOREIGN KEY (`dmpts`) REFERENCES `dmpts` (`pts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `wine_id` smallint(5) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_per_bottle` decimal(10,2) NOT NULL,
  `format` varchar(7) NOT NULL DEFAULT '750ml',
  `is_duty_paid` tinyint(1) NOT NULL DEFAULT 1,
  `is_delivered` tinyint(1) NOT NULL DEFAULT 1,
  `storage_location` smallint(5) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  KEY `idx_oi_order` (`order_id`),
  KEY `idx_oi_wine` (`wine_id`),
  KEY `idx_oi_format` (`format`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oi_wine` FOREIGN KEY (`wine_id`) REFERENCES `wines` (`wine_id`),
  CONSTRAINT `fk_oi_format` FOREIGN KEY (`format`) REFERENCES `bottle_formats` (`format`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `bottles`;
CREATE TABLE `bottles` (
  `bottle_id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT,
  `wine_id` smallint(5) UNSIGNED NOT NULL,
  `in_stock` varchar(3) NOT NULL,
  `purchased_from` smallint(5) UNSIGNED DEFAULT NULL,
  `purchase_price` decimal(6,2) DEFAULT NULL,
  `storage_location` smallint(5) UNSIGNED DEFAULT NULL,
  `consumed_date` date DEFAULT NULL,
  `note_id` smallint(5) UNSIGNED DEFAULT NULL,
  `bottle_notes` varchar(255) DEFAULT NULL,
  `format` varchar(7) NOT NULL DEFAULT '750ml',
  `order_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`bottle_id`),
  KEY `idx_bot_wine` (`wine_id`),
  KEY `idx_bot_storage` (`storage_location`),
  KEY `idx_bot_store` (`purchased_from`),
  KEY `idx_bot_note` (`note_id`),
  KEY `idx_bot_format` (`format`),
  KEY `idx_bot_order` (`order_id`),
  CONSTRAINT `fk_bot_wine` FOREIGN KEY (`wine_id`) REFERENCES `wines` (`wine_id`),
  CONSTRAINT `fk_bot_storage` FOREIGN KEY (`storage_location`) REFERENCES `storageBins` (`bin_id`),
  CONSTRAINT `fk_bot_store` FOREIGN KEY (`purchased_from`) REFERENCES `stores` (`store_id`),
  CONSTRAINT `fk_bot_note` FOREIGN KEY (`note_id`) REFERENCES `tnotes` (`note_id`),
  CONSTRAINT `fk_bot_format` FOREIGN KEY (`format`) REFERENCES `bottle_formats` (`format`),
  CONSTRAINT `fk_bot_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `blogposts`;
CREATE TABLE `blogposts` (
  `blog_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` smallint(5) UNSIGNED NOT NULL,
  `pub_date` date NOT NULL,
  `edit_date` date DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `status` varchar(9) NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`blog_id`),
  KEY `idx_bp_user` (`user_id`),
  CONSTRAINT `fk_bp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Comments, Subscriptions & Notifications
-- --------------------------------------------------------

DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
  `comment_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` smallint(5) UNSIGNED NOT NULL,
  `comment_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment` text NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'approved',
  PRIMARY KEY (`comment_id`),
  KEY `idx_com_user` (`user_id`),
  CONSTRAINT `fk_com_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `subscription_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` smallint(5) UNSIGNED NOT NULL,
  `item_type` enum('wine','tnote','blog') NOT NULL,
  `item_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`subscription_id`),
  UNIQUE KEY `idx_user_item` (`user_id`,`item_id`,`item_type`),
  KEY `idx_sub_user` (`user_id`),
  CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` smallint(5) UNSIGNED NOT NULL,
  `sender_id` smallint(5) UNSIGNED NOT NULL,
  `item_type` enum('wine','tnote','blog') NOT NULL,
  `item_id` int(11) NOT NULL,
  `comment_id` smallint(5) UNSIGNED NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_sender` (`sender_id`),
  KEY `idx_notif_comment` (`comment_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`comment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Junction Tables
-- --------------------------------------------------------

DROP TABLE IF EXISTS `x_blog_tnotes`;
CREATE TABLE `x_blog_tnotes` (
  `blog_id` smallint(5) UNSIGNED NOT NULL,
  `note_id` smallint(5) UNSIGNED NOT NULL,
  UNIQUE KEY `idx_blog_tnotes` (`blog_id`,`note_id`),
  KEY `idx_xbt_note` (`note_id`),
  CONSTRAINT `fk_xbt_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogposts` (`blog_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_xbt_note` FOREIGN KEY (`note_id`) REFERENCES `tnotes` (`note_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `x_blog_wines`;
CREATE TABLE `x_blog_wines` (
  `blog_id` smallint(5) UNSIGNED NOT NULL,
  `wine_id` smallint(5) UNSIGNED NOT NULL,
  UNIQUE KEY `idx_blog_wines` (`blog_id`,`wine_id`),
  KEY `idx_xbw_wine` (`wine_id`),
  CONSTRAINT `fk_xbw_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogposts` (`blog_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_xbw_wine` FOREIGN KEY (`wine_id`) REFERENCES `wines` (`wine_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `x_comments_blogposts`;
CREATE TABLE `x_comments_blogposts` (
  `comment_id` smallint(5) UNSIGNED NOT NULL,
  `blog_id` smallint(5) UNSIGNED NOT NULL,
  PRIMARY KEY (`comment_id`,`blog_id`),
  KEY `idx_xcb_blog` (`blog_id`),
  CONSTRAINT `fk_xcb_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`comment_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_xcb_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogposts` (`blog_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `x_comments_tnotes`;
CREATE TABLE `x_comments_tnotes` (
  `comment_id` smallint(5) UNSIGNED NOT NULL,
  `note_id` smallint(5) UNSIGNED NOT NULL,
  PRIMARY KEY (`comment_id`,`note_id`),
  KEY `idx_xct_note` (`note_id`),
  CONSTRAINT `fk_xct_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`comment_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_xct_note` FOREIGN KEY (`note_id`) REFERENCES `tnotes` (`note_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `x_comments_wines`;
CREATE TABLE `x_comments_wines` (
  `comment_id` smallint(5) UNSIGNED NOT NULL,
  `wine_id` smallint(5) UNSIGNED NOT NULL,
  PRIMARY KEY (`comment_id`,`wine_id`),
  KEY `idx_xcw_wine` (`wine_id`),
  CONSTRAINT `fk_xcw_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`comment_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_xcw_wine` FOREIGN KEY (`wine_id`) REFERENCES `wines` (`wine_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Views for Vintage Analysis & Statistics
-- --------------------------------------------------------

DROP VIEW IF EXISTS `view_vintage_country_stats`;
CREATE VIEW `view_vintage_country_stats` AS
SELECT
  `wines`.`vintage` AS `vintage`,
  `regions`.`country` AS `country`,
  COUNT(`tnotes`.`note_id`) AS `country_notes_count`,
  `total_vintage`.`total_notes_count` AS `total_notes_count`,
  ROUND(COUNT(`tnotes`.`note_id`) * 100.0 / `total_vintage`.`total_notes_count`, 1) AS `country_percentage`
FROM `tnotes`
JOIN `wines` ON `tnotes`.`wine_id` = `wines`.`wine_id`
JOIN `wines_master` ON `wines`.`master_id` = `wines_master`.`master_id`
JOIN `regions` ON `wines_master`.`region_id` = `regions`.`region_id`
JOIN (
  SELECT `w2`.`vintage` AS `vintage`, COUNT(`tn2`.`note_id`) AS `total_notes_count`
  FROM `tnotes` `tn2`
  JOIN `wines` `w2` ON `tn2`.`wine_id` = `w2`.`wine_id`
  WHERE `tn2`.`status` = 'published'
  GROUP BY `w2`.`vintage`
) `total_vintage` ON `wines`.`vintage` = `total_vintage`.`vintage`
WHERE `tnotes`.`status` = 'published'
GROUP BY `wines`.`vintage`, `regions`.`country`, `total_vintage`.`total_notes_count`;

DROP VIEW IF EXISTS `view_vintage_region_colour_stats`;
CREATE VIEW `view_vintage_region_colour_stats` AS
SELECT
  `wines`.`vintage` AS `vintage`,
  `regions`.`country` AS `country`,
  `regions`.`region` AS `region`,
  `wines_master`.`region_id` AS `region_id`,
  `wines_master`.`colour` AS `colour`,
  CONCAT(`regions`.`country`, ': ', `regions`.`region`, ' (', `wines_master`.`colour`, ')') AS `country_region_colour`,
  COUNT(`tnotes`.`note_id`) AS `note_count`,
  ROUND(AVG(CASE WHEN `tnotes`.`flawed_yn` = 'no' AND `tnotes`.`dmpts` IS NOT NULL THEN `tnotes`.`dmpts` END), 1) AS `avg_dmpts`,
  `xvr`.`vintage_desc` AS `vintage_desc`
FROM `tnotes`
JOIN `wines` ON `tnotes`.`wine_id` = `wines`.`wine_id`
JOIN `wines_master` ON `wines`.`master_id` = `wines_master`.`master_id`
JOIN `regions` ON `wines_master`.`region_id` = `regions`.`region_id`
LEFT JOIN `x_vintage_region` `xvr` ON `wines`.`vintage` = `xvr`.`vintage` AND `wines_master`.`region_id` = `xvr`.`region_id`
WHERE `tnotes`.`status` = 'published'
GROUP BY `wines`.`vintage`, `regions`.`country`, `regions`.`region`, `wines_master`.`region_id`, `wines_master`.`colour`, `xvr`.`vintage_desc`;

DROP VIEW IF EXISTS `view_vintage_top_wines`;
CREATE VIEW `view_vintage_top_wines` AS
SELECT
  `tnotes`.`note_id` AS `note_id`,
  `tnotes`.`wine_id` AS `wine_id`,
  `tnotes`.`user_id` AS `user_id`,
  `tnotes`.`tasting_date` AS `tasting_date`,
  `tnotes`.`dmpts` AS `dmpts`,
  `tnotes`.`flawed_yn` AS `flawed_yn`,
  `tnotes`.`favourite` AS `favourite`,
  `tnotes`.`status` AS `status`,
  `users`.`initials` AS `initials`,
  `wines`.`vintage` AS `vintage`,
  `wines_master`.`master_id` AS `master_id`,
  `wines_master`.`name` AS `name`,
  `wines_master`.`nameconvention` AS `nameconvention`,
  `wines_master`.`grape` AS `grape`,
  `wines_master`.`colour` AS `colour`,
  `wines_master`.`style` AS `style`,
  `producers`.`producer_id` AS `producer_id`,
  `producers`.`producer` AS `producer`,
  `vineyards`.`vineyard_id` AS `vineyard_id`,
  `vineyards`.`vineyard` AS `vineyard`,
  `regions`.`region_id` AS `region_id`,
  `regions`.`region` AS `region`,
  `regions`.`country` AS `country`,
  `appellations`.`appellation_id` AS `appellation_id`,
  `appellations`.`appellation` AS `appellation`
FROM `tnotes`
JOIN `users` ON `tnotes`.`user_id` = `users`.`user_id`
JOIN `wines` ON `tnotes`.`wine_id` = `wines`.`wine_id`
JOIN `wines_master` ON `wines`.`master_id` = `wines_master`.`master_id`
JOIN `producers` ON `wines_master`.`producer_id` = `producers`.`producer_id`
JOIN `regions` ON `wines_master`.`region_id` = `regions`.`region_id`
LEFT JOIN `vineyards` ON `wines_master`.`vineyard_id` = `vineyards`.`vineyard_id`
LEFT JOIN `appellations` ON `wines_master`.`appellation_id` = `appellations`.`appellation_id`
WHERE `tnotes`.`status` = 'published' AND `tnotes`.`flawed_yn` = 'no' AND `tnotes`.`dmpts` >= 8;

SET foreign_key_checks = 1;
