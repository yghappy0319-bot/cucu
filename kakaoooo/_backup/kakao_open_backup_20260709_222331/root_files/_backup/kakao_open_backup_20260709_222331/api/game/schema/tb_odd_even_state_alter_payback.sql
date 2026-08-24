-- 홀짝 웹 페이백 누적 (회원별)
-- SHOW COLUMNS FROM tb_odd_even_state LIKE 'payback_pool'; 로 중복 확인 후 실행

ALTER TABLE tb_odd_even_state
  ADD COLUMN payback_pool BIGINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT '웹 페이백 누적(게임냥)' AFTER odds_mode;
