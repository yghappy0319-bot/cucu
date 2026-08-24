-- 로또 지급·조회·추첨 성능용 권장 인덱스 (실행 전 SHOW INDEX 로 중복 확인)
-- 관리 페이지: /page/lotto_maintain.php?run=1

ALTER TABLE tb_game_lotto
  ADD INDEX idx_drow_rank (drow, rank),
  ADD INDEX idx_drow_nick (drow, nick),
  ADD INDEX idx_drow_status (drow, status),
  ADD INDEX idx_status_drow (status, drow);

ALTER TABLE tb_game_lotto_result
  ADD INDEX idx_status_drow (status, drow),
  ADD INDEX idx_drow (drow);
