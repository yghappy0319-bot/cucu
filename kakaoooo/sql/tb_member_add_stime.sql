-- 포크 시전 쿨다운(4시간)용 컬럼 추가 (한 번만 실행)
-- tb_member에 stime 컬럼이 없을 때만 DB에서 실행하세요.

ALTER TABLE tb_member ADD COLUMN `stime` DATETIME DEFAULT NULL COMMENT '포크 시전 마지막 사용 시각 (4시간 간격)';
