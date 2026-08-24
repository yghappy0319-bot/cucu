-- 채굴 은총 등급 (1=은총 2=메가 3=테라) — mining_eunchong_tier_ensure_column() 가 자동 ALTER
ALTER TABLE `tb_member_mining`
  ADD COLUMN `mining_eunchong_tier` TINYINT UNSIGNED NOT NULL DEFAULT 1
  COMMENT '활성 은총 등급 1=은총 2=메가 3=테라';
