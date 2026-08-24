-- 1:1 배팅 변경 요청 — 1회 실행
ALTER TABLE `tb_seotda_room`
  ADD COLUMN `pending_bet_amount` BIGINT UNSIGNED DEFAULT NULL COMMENT '배팅 변경 요청액' AFTER `guest_emote_at`,
  ADD COLUMN `pending_bet_by` VARCHAR(32) DEFAULT NULL COMMENT '배팅 변경 요청자' AFTER `pending_bet_amount`,
  ADD COLUMN `pending_bet_at` DATETIME DEFAULT NULL COMMENT '배팅 변경 요청 시각' AFTER `pending_bet_by`;
