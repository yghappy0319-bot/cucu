-- 개인금고 초과 금괴 본방냥 보너스 (마켓할인 15% 초과분)
-- 초과 금괴 1개당 하루 = 본방냥 총합 × 0.001% · 개인금고에서 수령
CREATE TABLE IF NOT EXISTS `tb_gold_vault_bonus` (
  `nick` VARCHAR(50) NOT NULL,
  `pending` DECIMAL(20,4) NOT NULL DEFAULT 0 COMMENT '수령 대기 본방냥',
  `claimed_total` DECIMAL(20,4) NOT NULL DEFAULT 0 COMMENT '누적 수령',
  `first_day_excess` INT NOT NULL DEFAULT 0 COMMENT '첫날분 이미 지급한 초과 금괴 수',
  `last_accrue_date` DATE NOT NULL COMMENT '마지막 적립일',
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`nick`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='개인금고 본방냥 보너스';
