-- 접속 목록용 online_at (status heartbeat 전용) — 1회 실행
ALTER TABLE `tb_seotda_room`
  ADD COLUMN `online_at` DATETIME DEFAULT NULL COMMENT '섯다 페이지 status heartbeat' AFTER `last_result`,
  ADD KEY `idx_online_at` (`online_at`);

-- 기존 유령 접속자 제거 (과거 updated_at/테스트 잔여)
UPDATE `tb_seotda_room` SET `online_at` = NULL;
