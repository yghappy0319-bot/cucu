-- 로또 수수료 누적 (강화·채굴·홀짝·수리·주식 등) — 해 단위 DECIMAL(65,0)
-- 코드: 로또누적_컬럼보장() 에서도 자동 추가됨

ALTER TABLE `config`
  ADD COLUMN `로또누적` DECIMAL(65,0) NOT NULL DEFAULT 0
  COMMENT '로또 수수료 누적(강화·채굴·홀짝·수리·주식 등)';
