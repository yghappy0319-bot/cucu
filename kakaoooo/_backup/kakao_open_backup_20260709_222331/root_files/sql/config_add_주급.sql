-- .주급지급 명령용 (한 번만 실행)
-- config 테이블에 주급, 주급지급날짜 컬럼이 없을 때만 DB에서 실행하세요.

ALTER TABLE config ADD COLUMN `주급` INT NOT NULL DEFAULT 0 COMMENT '관리자 주급 금액(냥)';
ALTER TABLE config ADD COLUMN `주급지급날짜` DATE DEFAULT NULL COMMENT '다음 주급 예정일 (7일마다 지급 가능)';
