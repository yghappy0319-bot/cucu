-- 게스트 선택 제한시간 (10초 무응답 자동 퇴장) — 1회 실행
ALTER TABLE `tb_seotda_room`
  ADD COLUMN `guest_action_since` DATETIME DEFAULT NULL COMMENT '게스트 선택 대기 시작' AFTER `waiting_since`;
