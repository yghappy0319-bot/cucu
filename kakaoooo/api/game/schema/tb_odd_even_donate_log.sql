-- 홀짝 하드모드 5연승 후원 로그
-- 적용: mysql < api/game/schema/tb_odd_even_donate_log.sql

CREATE TABLE IF NOT EXISTS `tb_odd_even_donate_log` (
  `idx` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nick` VARCHAR(32) NOT NULL COMMENT '후원한 사람 (두자리 닉)',
  `receiver` VARCHAR(32) NOT NULL DEFAULT '준호' COMMENT '후원 받은 사람',
  `base_amount` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '5연승 당첨금(기준액)',
  `donate_amount` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '실제 후원액(1%)',
  `odds_mode` VARCHAR(8) NOT NULL DEFAULT 'hard' COMMENT '후원 시점 모드',
  `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  KEY `idx_donate_nick_regdate` (`nick`, `regdate`),
  KEY `idx_donate_receiver_regdate` (`receiver`, `regdate`),
  KEY `idx_donate_regdate` (`regdate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='홀짝 하드모드 5연승 후원 내역';
