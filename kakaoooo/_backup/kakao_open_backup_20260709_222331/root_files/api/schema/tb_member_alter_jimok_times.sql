-- 지목(tb_progress status=지목) 만료 시 참가자별 누적 횟수 (cron: _auto_tasu_chk.php)

ALTER TABLE `tb_member`
  ADD COLUMN `jimok_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '지목 만료 완료 누적 횟수';
