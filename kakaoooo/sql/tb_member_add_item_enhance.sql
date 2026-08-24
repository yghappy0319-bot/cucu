-- .강화 기능용 컬럼 추가 (한 번만 실행)
-- tb_member에 item, enhance 컬럼이 없을 때만 DB에서 실행하세요.

ALTER TABLE tb_member ADD COLUMN `item` VARCHAR(50) DEFAULT NULL COMMENT '강화 무기(검/창/활/봉)';
ALTER TABLE tb_member ADD COLUMN `enhance` INT NOT NULL DEFAULT 0 COMMENT '강화 단계';
