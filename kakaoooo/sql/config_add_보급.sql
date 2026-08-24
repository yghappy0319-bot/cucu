-- .보급지급 명령용 (한 번만 실행)
-- config 테이블에 보급지급날짜 컬럼이 없을 때만 DB에서 실행하세요.

ALTER TABLE config ADD COLUMN `보급지급날짜` DATE DEFAULT NULL COMMENT '다음 보급 예정일 (7일마다 지급 가능)';
