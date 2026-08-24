-- .지또 닉당 1회 제한용 (한 번만 실행)
-- tb_member에 지또사용 컬럼이 없을 때만 DB에서 실행하세요.

ALTER TABLE tb_member ADD COLUMN `지또사용` TINYINT NOT NULL DEFAULT 0 COMMENT '지또 출입 사용 여부 (닉당 1회, 1=사용함)';
