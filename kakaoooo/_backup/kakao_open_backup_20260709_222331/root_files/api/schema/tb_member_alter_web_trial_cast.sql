-- 무기 강화 웹 맛보기 (단소/활·마법 시전 1회/일, 마법 보호 1회/일)
ALTER TABLE tb_member
  ADD COLUMN IF NOT EXISTS web_trial_cast_date DATE NULL DEFAULT NULL COMMENT '웹 맛보기 시전/공격일',
  ADD COLUMN IF NOT EXISTS web_trial_protect_date DATE NULL DEFAULT NULL COMMENT '웹 맛보기 보호일';
