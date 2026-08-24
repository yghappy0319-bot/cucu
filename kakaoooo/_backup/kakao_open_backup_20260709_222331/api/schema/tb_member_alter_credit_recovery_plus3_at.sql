-- 신용 회복 순간 기준으로 3시간 후 시각 (회복 처리 시 코드에서 DATE_ADD 로 설정)
ALTER TABLE `tb_member`
  ADD COLUMN `credit_recovery_plus3_at` DATETIME NULL DEFAULT NULL
    COMMENT '신용 회복 시각 +3시간';
