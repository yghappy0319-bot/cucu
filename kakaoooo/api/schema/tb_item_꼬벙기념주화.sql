-- 꼬벙기념주화 (가방: 제1회🌶️꼬벙기념주화)
-- 지급: 민호 · 우서 · 다오 · 여름 각 1개
-- 런타임: api/item_kkobung_coin.inc.php 가 자동 생성·지급

-- tb_item (없을 때만)
INSERT INTO tb_item (itemname, sname, buy, sell, percent, buystatus, status, randum, sort)
SELECT '꼬벙기념주화', '꼬벙기념주화', 0, 0, 0, '1', '1', '0', 920
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM tb_item WHERE TRIM(sname) = '꼬벙기념주화' LIMIT 1
);

-- bag 컬럼
-- ALTER TABLE tb_member_item_bag ADD COLUMN `꼬벙기념주화` INT UNSIGNED NOT NULL DEFAULT 0;
