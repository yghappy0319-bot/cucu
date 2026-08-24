-- 야바위 역대 최고점수 캐시 (결과 메시지용, REGEXP 조회 대체)
ALTER TABLE `config`
  ADD COLUMN `야바위최고점수` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '야바위 역대 최고 주사위 점수',
  ADD COLUMN `야바위최고점수닉` VARCHAR(32) NULL DEFAULT NULL COMMENT '야바위 역대 최고점 닉',
  ADD COLUMN `야바위최고점수상태` VARCHAR(64) NULL DEFAULT NULL COMMENT '야바위 역대 최고점 로그 status 표시문',
  ADD COLUMN `야바위최고점수일시` DATETIME NULL DEFAULT NULL COMMENT '야바위 역대 최고점 달성 시각';
