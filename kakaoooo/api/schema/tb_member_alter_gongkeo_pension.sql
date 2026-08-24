-- 공커 연금 누적 잔액 (1일당 1,000냥, cron 누적 · .공커연금 수령)

ALTER TABLE `tb_member`
  ADD COLUMN `gongkeo_pension` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '공커연금 누적(수령 대기)';
