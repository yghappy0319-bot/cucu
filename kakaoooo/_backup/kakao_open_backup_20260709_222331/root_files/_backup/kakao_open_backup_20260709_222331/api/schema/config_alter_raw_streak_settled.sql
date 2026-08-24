-- 생타 연속 배치: 마지막으로 일별 집계를 끝낸 날짜 (YYYY-MM-DD)
ALTER TABLE `config`
  ADD COLUMN `raw_streak_settled_date` VARCHAR(12) NULL DEFAULT '' COMMENT '생타연속 마지막 처리일';
