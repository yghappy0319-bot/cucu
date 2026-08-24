-- 채굴 장비 내구도 — mining_durability_ensure_column() 가 자동 ALTER
ALTER TABLE `tb_member_mining`
  ADD COLUMN `mining_durability` DECIMAL(12,6) NOT NULL DEFAULT 100
  COMMENT '채굴 장비 내구도 (0=정지)'
  AFTER `mining_upgrade_attempts`;
