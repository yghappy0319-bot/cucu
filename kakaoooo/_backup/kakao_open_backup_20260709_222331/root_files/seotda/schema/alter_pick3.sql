-- 기존 tb_seotda_room에 3장 선택 컬럼 추가 (1회 실행, 이미 있으면 오류 무시)
ALTER TABLE `tb_seotda_room`
  ADD COLUMN `host_card3` TINYINT UNSIGNED DEFAULT NULL COMMENT '3장 중 2장 선택' AFTER `host_card2`,
  ADD COLUMN `guest_card3` TINYINT UNSIGNED DEFAULT NULL AFTER `guest_card2`,
  ADD COLUMN `sys_card3` TINYINT UNSIGNED DEFAULT NULL AFTER `sys_card2`,
  ADD COLUMN `host_picked` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `sys_card3`,
  ADD COLUMN `guest_picked` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `host_picked`,
  ADD COLUMN `host_pick1` TINYINT UNSIGNED DEFAULT NULL COMMENT '선택 슬롯 1-3' AFTER `guest_picked`,
  ADD COLUMN `host_pick2` TINYINT UNSIGNED DEFAULT NULL AFTER `host_pick1`,
  ADD COLUMN `guest_pick1` TINYINT UNSIGNED DEFAULT NULL AFTER `host_pick2`,
  ADD COLUMN `guest_pick2` TINYINT UNSIGNED DEFAULT NULL AFTER `guest_pick1`;
