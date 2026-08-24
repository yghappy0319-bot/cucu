-- 패 선택 전 랜덤 1장 공개 — 1회 실행
ALTER TABLE `tb_seotda_room`
  ADD COLUMN `host_peek_slot` TINYINT UNSIGNED DEFAULT NULL COMMENT '호스트 랜덤 공개 슬롯 1-3' AFTER `guest_pick2`,
  ADD COLUMN `guest_peek_slot` TINYINT UNSIGNED DEFAULT NULL COMMENT '게스트 랜덤 공개 슬롯 1-3' AFTER `host_peek_slot`;
