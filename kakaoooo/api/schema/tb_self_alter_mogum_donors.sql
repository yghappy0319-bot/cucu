-- 모금자 닉 목록
ALTER TABLE `tb_self`
  ADD COLUMN `mogum_donors` VARCHAR(500) NOT NULL DEFAULT ''
  COMMENT '모금자 닉 목록(공백구분)'
  AFTER `mogum_sec`;
