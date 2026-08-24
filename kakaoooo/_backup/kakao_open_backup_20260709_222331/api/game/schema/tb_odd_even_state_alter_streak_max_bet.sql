-- 기존 DB에만 실행 (이미 테이블이 있는 경우)
ALTER TABLE `tb_odd_even_state`
  ADD COLUMN `streak_max_bet` INT UNSIGNED NOT NULL DEFAULT 0
  COMMENT '이번 연승 구간에서 건 배팅 중 최대액(다음 판 하한)'
  AFTER `streak`;
