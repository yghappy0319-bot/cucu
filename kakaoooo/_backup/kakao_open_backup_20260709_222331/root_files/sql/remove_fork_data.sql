-- 포크 무기·기록 제거 (한 번만 실행)
-- 이미 컬럼이 있으면 아래 DROP 구문도 필요 시 실행

UPDATE tb_member SET `item` = '', `enhance` = 0 WHERE `item` LIKE '%포크%';

DELETE FROM tb_damege WHERE `유형` IN ('포크', '포크지목');

-- 포크 전용 컬럼 제거 (존재할 때만 실행)
-- ALTER TABLE tb_member DROP COLUMN `fork_used`;
-- ALTER TABLE tb_member DROP COLUMN `fork_window`;
-- ALTER TABLE tb_member DROP COLUMN `stime`;
