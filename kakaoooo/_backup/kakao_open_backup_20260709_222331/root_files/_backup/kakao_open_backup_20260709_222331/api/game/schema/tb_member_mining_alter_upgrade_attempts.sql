-- 채굴강화 시도 누적 (MINING_UNLOCK_UPGRADE_ATTEMPTS 해제 조건)
ALTER TABLE `tb_member_mining`
  ADD COLUMN `mining_upgrade_attempts` INT UNSIGNED NOT NULL DEFAULT 0
  COMMENT '채굴강화 시도 누적(성공·실패)' AFTER `mining_weapon_equipped`;
