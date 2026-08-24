-- 홀짝 웹/채팅 성능용 권장 인덱스 (DB에 없으면 간헐적으로 느려질 수 있음)
-- 실행 전 SHOW INDEX 로 중복 여부 확인

-- tb_odd_even_state: room_lock·만료 조회
ALTER TABLE tb_odd_even_state
  ADD INDEX idx_pending_bet_at (pending_bet, pending_at),
  ADD INDEX idx_streak_updated (streak, updated_at);

-- tb_odd_even_log: 연승 픽 표시
ALTER TABLE tb_odd_even_log
  ADD INDEX idx_nick_idx (nick, idx);

-- tb_odd_even_profit: .타짜 상위 조회 (테이블 없으면 odd_even_profit.inc.php 가 생성)
-- CREATE TABLE 은 api/game/schema/tb_odd_even_profit.sql 참고

-- tb_msg: 미션 미기록 시 생타/버프 fallback (가능하면 tb_mission 100타·버프1000타 로 회피)
ALTER TABLE tb_msg
  ADD INDEX idx_nick_regdate_tasu (nickname, regdate, tasu);

-- tb_mission: bet 시 출석 조건
ALTER TABLE tb_mission
  ADD INDEX idx_nick_regdate (nick, regdate);
