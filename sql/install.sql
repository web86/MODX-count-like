-- Change "modx_" to your MODX table prefix when necessary.
CREATE TABLE IF NOT EXISTS `modx_mcl_stats` (
  `resource_id` INT UNSIGNED NOT NULL,
  `views` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `likes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `modx_mcl_viewers` (
  `resource_id` INT UNSIGNED NOT NULL,
  `visitor_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `last_viewed_at` DATETIME NOT NULL,
  PRIMARY KEY (`resource_id`, `visitor_hash`),
  KEY `last_viewed_at` (`last_viewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `modx_mcl_likes` (
  `resource_id` INT UNSIGNED NOT NULL,
  `visitor_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`resource_id`, `visitor_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
