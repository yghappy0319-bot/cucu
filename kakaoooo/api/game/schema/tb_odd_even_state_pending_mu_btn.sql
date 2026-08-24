-- 2~5연승 구간 무 버튼(10% 출현)용 컬럼
-- SHOW COLUMNS FROM tb_odd_even_state LIKE 'pending_mu_btn'; 로 중복 확인 후 실행

ALTER TABLE tb_odd_even_state
  ADD COLUMN `pending_mu_btn` TINYINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT '1=이번 판 무 버튼 출현(2~5연승 구간 10%)'
    AFTER `pending_lose_mult`;
