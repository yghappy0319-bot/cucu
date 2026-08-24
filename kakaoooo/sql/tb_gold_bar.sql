-- 개인금고 금괴 — config.tax / .금고 털이와 별개
-- 신규: 1은괴=1해 · 1금괴=10해 · 1백금괴=100해 · 기존 예치 amount 유지
-- 기간: 7/14일 (신규) · 동일 이율 · 중도해지 잠금/강제해지·이자×3
CREATE TABLE IF NOT EXISTS `tb_gold_bar` (
  `idx` INT NOT NULL AUTO_INCREMENT,
  `nick` VARCHAR(50) NOT NULL COMMENT '소유 닉',
  `amount` DECIMAL(40,0) NOT NULL DEFAULT 1000000000000000000000 COMMENT '신규:1은괴=1해·1금괴=10해·1백금괴=100해 / 구예치유지',
  `term_days` SMALLINT NOT NULL DEFAULT 14 COMMENT '7|14|30',
  `status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=보관중 1=환전완료',
  `early` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=중도해지',
  `deposited_at` DATETIME NOT NULL,
  `unlock_at` DATETIME NOT NULL COMMENT '만기 시각',
  `withdrawn_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`idx`),
  KEY `idx_nick_status` (`nick`, `status`),
  KEY `idx_unlock` (`unlock_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='개인금고 금괴';
