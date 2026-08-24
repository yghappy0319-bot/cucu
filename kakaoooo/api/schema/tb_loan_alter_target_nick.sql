-- 모금 기록: nick=모금자, target_nick=자숙 대상
ALTER TABLE `tb_loan`
  ADD COLUMN `target_nick` VARCHAR(32) NOT NULL DEFAULT ''
  COMMENT '자숙 대상 닉(모금 받는 사람)'
  AFTER `nick`;
