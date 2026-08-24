-- 무기 타입 컬럼 (1=활 2=단소 3=마법)
-- 앱에서도 무기_타입_스키마보장() 이 자동 추가·마이그레이션합니다.

ALTER TABLE tb_member
  ADD COLUMN `무기타입` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=활 2=단소 3=마법' AFTER `item`;

UPDATE tb_member SET 무기타입 = 3
WHERE IFNULL(무기타입,0) = 0
  AND TRIM(COALESCE(item,'')) != ''
  AND REPLACE(TRIM(item), ' ', '') LIKE '%마법%';

UPDATE tb_member SET 무기타입 = 2
WHERE IFNULL(무기타입,0) = 0
  AND TRIM(COALESCE(item,'')) != ''
  AND REPLACE(TRIM(item), ' ', '') LIKE '%단소%';

UPDATE tb_member SET 무기타입 = 1
WHERE IFNULL(무기타입,0) = 0
  AND TRIM(COALESCE(item,'')) != ''
  AND (
    REPLACE(TRIM(item), ' ', '') LIKE '%활%'
    OR TRIM(item) IN ('🏹활', '🏹 활')
  );
