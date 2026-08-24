CREATE TABLE IF NOT EXISTS `tb_draw_product_card` (
  `dpc_idx` int unsigned NOT NULL AUTO_INCREMENT,
  `dp_idx` int unsigned NOT NULL,
  `dpc_orig_name` varchar(255) NOT NULL DEFAULT '',
  `dpc_grade` varchar(10) NOT NULL DEFAULT '',
  `dpc_image_path` varchar(255) NOT NULL,
  `dpc_sort` int NOT NULL DEFAULT '0',
  `dpc_created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`dpc_idx`),
  KEY `idx_dp_idx_orig` (`dp_idx`,`dpc_orig_name`,`dpc_idx`),
  KEY `idx_dp_grade` (`dp_idx`,`dpc_grade`,`dpc_idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
