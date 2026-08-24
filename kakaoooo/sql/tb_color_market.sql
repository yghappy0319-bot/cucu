-- 색표 1~45 고정가 매매
CREATE TABLE IF NOT EXISTS `tb_color_market` (
  `color_num` TINYINT UNSIGNED NOT NULL COMMENT '색번호 1~45',
  `owner_nick` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '소유자 닉(없으면 무주인)',
  `price` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '판매가(게임냥)',
  `listed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=판매중',
  `trade_lock` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=구매불가(준호 지정)',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`color_num`),
  KEY `idx_owner` (`owner_nick`),
  KEY `idx_listed` (`listed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='색표 고정가 매매 소유권';

-- 기존 테이블용
-- ALTER TABLE tb_color_market ADD COLUMN `trade_lock` TINYINT(1) NOT NULL DEFAULT 0 AFTER `listed`;

CREATE TABLE IF NOT EXISTS `tb_color_trade_log` (
  `idx` INT NOT NULL AUTO_INCREMENT,
  `color_num` TINYINT UNSIGNED NOT NULL,
  `seller_nick` VARCHAR(50) NOT NULL DEFAULT '',
  `buyer_nick` VARCHAR(50) NOT NULL DEFAULT '',
  `price` DECIMAL(40,0) NOT NULL DEFAULT 0,
  `fee` DECIMAL(40,0) NOT NULL DEFAULT 0,
  `action` VARCHAR(20) NOT NULL DEFAULT 'buy' COMMENT 'buy|claim|list|unlist|lock|unlock',
  `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  KEY `idx_color` (`color_num`),
  KEY `idx_regdate` (`regdate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='색표 거래 로그';
