-- 홀짝 하드 5연승 후원모금함 (config 전역 누적 · 준호 수령)
-- SHOW COLUMNS FROM config LIKE '후원모금함'; 로 중복 확인 후 실행

ALTER TABLE `config`
  ADD COLUMN `후원모금함` DECIMAL(40,0) NOT NULL DEFAULT 0
    COMMENT '홀짝 하드 5연승 후원 누적(게임냥). 준호만 수령';
