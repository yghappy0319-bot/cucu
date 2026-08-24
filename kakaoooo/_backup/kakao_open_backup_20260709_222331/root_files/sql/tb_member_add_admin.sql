-- .관리자 닉네임 으로 관리자 지정 시 사용 (한 번만 실행)
-- tb_member에 admin 컬럼이 없을 때만 DB에서 실행하세요.

ALTER TABLE tb_member ADD COLUMN `admin` TINYINT NOT NULL DEFAULT 0 COMMENT '관리자 여부 (1=관리자, .관리자 명령으로 지정)';
