-- tb_point_log·config 금액 컬럼 BIGINT 확장 (4경+ 양도·홀짝·채굴 로그)
-- mysql kakao1 < api/schema/tb_point_log_alter_bigint.sql

ALTER TABLE tb_point_log
  MODIFY COLUMN point BIGINT NOT NULL DEFAULT 0,
  MODIFY COLUMN tax BIGINT NOT NULL DEFAULT 0,
  MODIFY COLUMN mypoint BIGINT NOT NULL DEFAULT 0;

ALTER TABLE config
  MODIFY COLUMN tax BIGINT NOT NULL DEFAULT 0;
