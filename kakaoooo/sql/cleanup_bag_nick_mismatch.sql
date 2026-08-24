-- tb_member(midx + name) 과 일치하지 않는 tb_member_item_bag 행 삭제
-- (회원 없음 / bag.nick ≠ member.name)

-- 미리보기
SELECT
  b.midx,
  b.nick AS bag_nick,
  m.name AS mem_name
FROM tb_member_item_bag b
LEFT JOIN tb_member m ON m.idx = b.midx
WHERE m.idx IS NULL
   OR TRIM(IFNULL(b.nick, '')) <> TRIM(IFNULL(m.name, ''))
ORDER BY b.midx ASC;

-- 삭제
DELETE b FROM tb_member_item_bag b
LEFT JOIN tb_member m
  ON m.idx = b.midx
 AND TRIM(IFNULL(m.name, '')) = TRIM(IFNULL(b.nick, ''))
WHERE m.idx IS NULL;
