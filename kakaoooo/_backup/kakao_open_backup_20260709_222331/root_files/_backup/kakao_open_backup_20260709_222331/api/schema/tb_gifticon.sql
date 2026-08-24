-- 기프티콘 마켓 (shop/)
CREATE TABLE IF NOT EXISTS `tb_gifticon` (
  `idx` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `seller_nick` varchar(30) NOT NULL COMMENT '판매자 닉',
  `brand` varchar(60) NOT NULL DEFAULT '' COMMENT '브랜드(선택)',
  `name` varchar(120) NOT NULL COMMENT '상품명',
  `face_value` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '권면가(원)',
  `price_nyang` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '판매가(게임냥)',
  `price_newpoint` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '판매가(본방냥·스냅샷 환산)',
  `category` varchar(20) NOT NULL DEFAULT 'cafe',
  `emoji` varchar(10) DEFAULT NULL,
  `image_file` varchar(120) DEFAULT NULL COMMENT '기프티콘 이미지 파일명',
  `expire_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'sale' COMMENT 'sale|sold|reserved|cancelled',
  `buyer_nick` varchar(30) DEFAULT NULL,
  `settled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '판매자 정산완료',
  `sold_at` datetime DEFAULT NULL COMMENT '판매일시',
  `regdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  KEY `idx_gifticon_status` (`status`),
  KEY `idx_gifticon_seller` (`seller_nick`),
  KEY `idx_gifticon_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
