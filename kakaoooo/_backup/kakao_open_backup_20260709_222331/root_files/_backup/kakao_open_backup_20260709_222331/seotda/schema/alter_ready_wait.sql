-- 1:1 준비 후 상대 응답 대기 (20초) — 1회 실행
ALTER TABLE `tb_seotda_room`
  ADD COLUMN `ready_wait_since` DATETIME DEFAULT NULL COMMENT '한쪽 준비 후 상대 응답 대기' AFTER `guest_action_since`;
