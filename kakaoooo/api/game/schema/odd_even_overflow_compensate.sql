-- ============================================================
-- 홀짝 고액 오버플로우 보상 조회 / 보정용
-- ============================================================
-- 증상: 4500경 올인 + 4~5연승(×11) 성공 → 약 1해만 지급
-- 원인: PHP (int) 가 배팅을 PHP_INT_MAX(≈922경)로 클램프 →
--       922경 × 11 − 수수료10% ≈ 1.005해
-- 정상: 4500경 × 11 − 수수료10% = 4.905해
--
-- 단위: 1경 = 10^16, 1해 = 10^20 (= 10000경)
--
-- ★ 닉·시각을 본인 상황에 맞게 바꿔서 조회한 뒤,
--   차액을 확인한 다음 UPDATE 를 수동으로 실행하세요.
-- ★ 자동 일괄 보정 금지 — 실제 보유·로그를 보고 판단.
-- ============================================================

-- 1) 의심 로그 찾기 (배팅이 PHP_INT_MAX 근처 + 고배수 승리)
--    PHP_INT_MAX = 9223372036854775807 ≈ 922경
SELECT
  l.idx,
  l.nick,
  CAST(l.bet AS CHAR) AS bet,
  l.streak_before,
  l.win_mult,
  CAST(l.delta_point AS CHAR) AS delta_point,
  l.result,
  l.regdate,
  CAST(m.point AS CHAR) AS current_point
FROM tb_odd_even_log l
LEFT JOIN tb_member m ON m.name = l.nick
WHERE l.result = 'win'
  AND l.win_mult >= 9
  AND l.bet >= 9000000000000000000   -- 약 900경 이상 (클램프 의심)
ORDER BY l.idx DESC
LIMIT 50;

-- 2) 특정 닉의 최근 승리 (닉 바꿔서 실행)
-- SELECT idx, nick, CAST(bet AS CHAR) AS bet, streak_before, win_mult,
--        CAST(delta_point AS CHAR) AS delta_point, regdate
-- FROM tb_odd_even_log
-- WHERE nick = '닉네임' AND result = 'win'
-- ORDER BY idx DESC LIMIT 5;

-- 3) 차액 계산 (4500경 ×11 예시 · bet_pick merge 경로)
--    실제배팅 = 4500경 = 45000000000000000000
--    클램프배팅 = 9223372036854775807
--    정상실지급 = 49050경, 실제실지급 ≈ 10053.5경
--    순변동 정상 = 44550경, 순변동 실제 ≈ 9131경
--    보정액 ≈ 35418.9경 ≈ 3.54해
--
SET @실제배팅 = 45000000000000000000;      -- ★ 4500경 (알고 있는 실제 올인액)
SET @클램프배팅 = 9223372036854775807;     -- ★ 로그의 bet
SET @배수 = 11;
SET @정상실지급 = (@실제배팅 * @배수) - FLOOR(@실제배팅 * 10 / 100);
SET @실제실지급 = (@클램프배팅 * @배수) - FLOOR(@클램프배팅 * 10 / 100);
-- merge_bet: 순변동 = 실지급 − 배팅
SET @보정액 = (@정상실지급 - @실제배팅) - (@실제실지급 - @클램프배팅);
SELECT
  CAST(@실제배팅 AS CHAR) AS 실제배팅,
  CAST(@클램프배팅 AS CHAR) AS 클램프배팅,
  CAST(@정상실지급 AS CHAR) AS 정상실지급,
  CAST(@실제실지급 AS CHAR) AS 실제실지급,
  CAST(@보정액 AS CHAR) AS 보정액;

-- 4) 포인트 보정 (닉·보정액 확인 후)
-- UPDATE tb_member
-- SET point = point + 354188616835137719510
-- WHERE name = '닉네임'
-- LIMIT 1;

-- 5) 순이익 집계도 같이 맞추려면 (선택)
-- UPDATE tb_odd_even_profit
-- SET total_nyang = total_nyang + 354188616835137719510
-- WHERE nick = '닉네임'
-- LIMIT 1;

-- 6) 로그 정정 (선택 · 감사 추적용)
-- UPDATE tb_odd_even_log
-- SET bet = 45000000000000000000,
--     delta_point = 490500000000000000000
-- WHERE idx = 로그idx
-- LIMIT 1;
