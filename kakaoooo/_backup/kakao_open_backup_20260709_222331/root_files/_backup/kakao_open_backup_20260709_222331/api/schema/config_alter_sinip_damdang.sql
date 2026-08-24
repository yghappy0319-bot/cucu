-- 신입 수령 담당자 (내가받을께!!! 로 등록된 2글자닉)
ALTER TABLE config
  ADD COLUMN `신입담당` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '신입 수령 담당 2글자닉';
