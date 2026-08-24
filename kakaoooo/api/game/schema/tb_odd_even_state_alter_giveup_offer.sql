-- 홀짝 연승포기 버튼 오퍼 (1연승부터 매 승 10%)
-- SHOW COLUMNS FROM tb_odd_even_state LIKE 'giveup_offer'; 로 중복 확인 후 실행

ALTER TABLE tb_odd_even_state
  ADD COLUMN giveup_offer TINYINT UNSIGNED NOT NULL DEFAULT 0
  COMMENT '연승포기 버튼 오퍼(1연승부터 승마다 10%)';
