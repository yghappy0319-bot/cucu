-- tb_enhance_log: 무기복구 사용 여부 (기존 DB 마이그레이션)
ALTER TABLE `tb_enhance_log`
  ADD COLUMN `restored` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '무기복구 완료 여부' AFTER `suho_left`,
  ADD COLUMN `restored_at` DATETIME DEFAULT NULL COMMENT '무기복구 시각' AFTER `restored`,
  ADD KEY `idx_nick_break_restore` (`nick`, `result`, `restored`);
