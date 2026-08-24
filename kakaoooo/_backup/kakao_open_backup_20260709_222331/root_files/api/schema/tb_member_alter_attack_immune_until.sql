-- 지정 연속 시전(.시전 닉네임 N) 피해 후 5시간 공격 면역
ALTER TABLE `tb_member`
  ADD COLUMN `attack_immune_until` DATETIME NULL DEFAULT NULL COMMENT '활·단소 공격 면역 만료 시각' AFTER `protect`;
