-- 생타수(당일 메시지 가중 합) 400 이상 연속 5일 시 일방권 지급 (_auto_raw_tasu_streak.php)
ALTER TABLE `tb_member`
  ADD COLUMN `raw_tasu_streak` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '생타 400+ 연속 일수',
  ADD COLUMN `raw_tasu_streak_last` DATE NULL DEFAULT NULL COMMENT '마지막으로 400+ 충족한 날짜';
