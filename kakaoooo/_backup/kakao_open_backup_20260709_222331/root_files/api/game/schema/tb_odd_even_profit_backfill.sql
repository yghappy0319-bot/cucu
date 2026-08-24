-- 최초 1회: tb_odd_even_log → tb_odd_even_profit 백필 (서버에서 .타짜 첫 호출 시 자동 실행되기도 함)

INSERT INTO tb_odd_even_profit (nick, total_nyang, regdate, updated_at)
SELECT
  l.nick,
  SUM(
    CASE l.result
      WHEN 'win' THEN COALESCE(l.delta_point, 0) - COALESCE(l.bet, 0)
      WHEN 'push' THEN COALESCE(l.delta_point, 0) - COALESCE(l.bet, 0)
      WHEN 'lose' THEN COALESCE(l.delta_point, 0)
      ELSE COALESCE(l.delta_point, 0)
    END
  ) AS total_nyang,
  MIN(l.regdate),
  NOW()
FROM tb_odd_even_log l
WHERE l.result IN ('win', 'lose', 'push')
GROUP BY l.nick
ON DUPLICATE KEY UPDATE
  total_nyang = VALUES(total_nyang),
  updated_at = NOW();
