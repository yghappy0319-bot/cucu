-- 홀짝 하드모드 5연승 후원 오퍼 (웹)
-- SHOW COLUMNS FROM tb_odd_even_state LIKE 'hard_donate_offer'; 로 중복 확인 후 실행

ALTER TABLE tb_odd_even_state
  ADD COLUMN hard_donate_offer DECIMAL(40,0) NOT NULL DEFAULT 0
    COMMENT '하드 5연승 후원 기준액(당첨금). 0=없음' AFTER payback_pool;
