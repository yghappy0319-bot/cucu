CREATE TABLE IF NOT EXISTS `tb_draw_product` (
  `dp_idx` int unsigned NOT NULL AUTO_INCREMENT,
  `dp_name` varchar(200) NOT NULL,
  `dp_code` varchar(80) DEFAULT NULL,
  `dp_pack_type` varchar(20) NOT NULL DEFAULT 'expansion',
  `dp_pack_count` tinyint unsigned NOT NULL DEFAULT '30',
  `dp_image_path` varchar(255) DEFAULT NULL,
  `dp_sort` int NOT NULL DEFAULT '0',
  `dp_status` tinyint NOT NULL DEFAULT '1',
  `dp_created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dp_updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`dp_idx`),
  KEY `idx_dp_status_sort` (`dp_status`,`dp_sort`,`dp_idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
