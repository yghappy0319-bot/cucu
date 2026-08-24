-- .강화 수호 기능용 컬럼 추가 (한 번만 실행)
-- tb_member에 enhance_suho 컬럼이 없을 때만 DB에서 실행하세요.
-- 수호 사용 시 강화 실패 1회 보호 여부 저장 (1=보호 1회 있음)

ALTER TABLE tb_member ADD COLUMN `enhance_suho` TINYINT NOT NULL DEFAULT 0 COMMENT '강화 수호 보호 1회 (0=없음, 1=있음)';
