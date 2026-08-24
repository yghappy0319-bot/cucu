-- 금괴 정상만기 원금 잔액 (마켓 소비 시 글로벌 누적 차감)
-- runtime: gv_만기원금잔액_스키마보장()
CREATE TABLE IF NOT EXISTS `tb_gold_maturity_balance` (
  `nick` VARCHAR(50) NOT NULL COMMENT '만기해지 회원 2글자닉',
  `remaining` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '마켓 미소비 만기 원금 잔액',
  `total_matured` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '정상 만기 원금 누적 합',
  `market_spent` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '마켓 소비로 차감된 합',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`nick`),
  KEY `idx_remaining` (`remaining`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='금괴 정상만기 원금 잔액(마켓 소비 시 글로벌 누적 차감)';
