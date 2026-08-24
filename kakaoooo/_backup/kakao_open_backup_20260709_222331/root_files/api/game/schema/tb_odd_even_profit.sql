-- 홀짝 순이익 집계 (.타짜 고속 조회용)
-- 기존 로그 백필: api/game/schema/tb_odd_even_profit_backfill.sql

CREATE TABLE IF NOT EXISTS `tb_odd_even_profit` (
  `nick` VARCHAR(32) NOT NULL,
  `total_nyang` BIGINT NOT NULL DEFAULT 0,
  `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`nick`),
  KEY `idx_total_nyang` (`total_nyang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='홀짝 닉별 누적 순이익';
