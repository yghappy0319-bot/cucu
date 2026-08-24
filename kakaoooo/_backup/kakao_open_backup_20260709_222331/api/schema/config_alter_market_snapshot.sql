-- 시세 산정용 총량 스냅샷 (크론 1시간 갱신 · 보상·아이템·스왑 환율 기준)
ALTER TABLE `config`
  ADD COLUMN `시세기준_본방냥` DECIMAL(20,4) NOT NULL DEFAULT 0 COMMENT '시세 산정용 본방냥(newpoint) 총량 스냅샷',
  ADD COLUMN `시세기준_게임냥` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '시세 산정용 게임냥(point) 총량 스냅샷',
  ADD COLUMN `시세기준_갱신시각` DATETIME NULL DEFAULT NULL COMMENT '시세 스냅샷 마지막 갱신 시각';

-- 기존 BIGINT/좁은 DECIMAL 컬럼이 있으면 DECIMAL(40,0)으로 확장 (PHP_INT_MAX·BIGINT ~922경 초과 총량 보존)
-- ALTER TABLE `config` MODIFY COLUMN `시세기준_게임냥` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '시세 산정용 게임냥(point) 총량 스냅샷';
