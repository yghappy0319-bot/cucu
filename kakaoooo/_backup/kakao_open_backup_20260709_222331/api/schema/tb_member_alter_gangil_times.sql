-- 강일(tb_progress status=강일) 만료 시 참가자별 누적 횟수 (cron: _auto_tasu_chk.php)

ALTER TABLE `tb_member`
  ADD COLUMN `gangil_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '강일 만료 완료 누적 횟수';
