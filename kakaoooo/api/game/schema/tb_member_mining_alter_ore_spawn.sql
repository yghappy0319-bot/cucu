-- 채굴 광물 크론 스케줄 컬럼 (mining_ore_cron.inc.php 가 자동 ALTER)
ALTER TABLE `tb_member_mining`
  ADD COLUMN IF NOT EXISTS `mining_ore_next_spawn_at` DATETIME DEFAULT NULL COMMENT '다음 광물 spawn 예정',
  ADD COLUMN IF NOT EXISTS `mining_ore_window_start` DATETIME DEFAULT NULL COMMENT '현재 60분 창 시작';

-- MariaDB 10.5 미만은 IF NOT EXISTS 미지원 — mining_ore_schedule_ensure_columns() 사용

ALTER TABLE `tb_member_mining`
  ADD KEY `idx_ore_next_spawn` (`mining_weapon_equipped`, `mining_ore_next_spawn_at`);
