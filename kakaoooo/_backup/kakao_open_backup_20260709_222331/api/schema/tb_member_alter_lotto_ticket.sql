-- 로또 추첨 티켓 (레벨당 50장 지급 · .로또 자동 50 차감)
-- SHOW COLUMNS FROM tb_member LIKE 'lotto_ticket'; 로 중복 확인 후 실행

ALTER TABLE tb_member
  ADD COLUMN lotto_ticket INT UNSIGNED NOT NULL DEFAULT 0
    COMMENT '로또 추첨 티켓 잔량' AFTER level;

ALTER TABLE tb_member
  ADD COLUMN lotto_ticket_level INT UNSIGNED NOT NULL DEFAULT 0
    COMMENT '티켓 지급 완료 레벨(백필·레벨업 추적)' AFTER lotto_ticket;

-- 기존 회원 백필: 보유 레벨 × 50장 (1회)
UPDATE tb_member
SET
  lotto_ticket = lotto_ticket + GREATEST(0, CAST(level AS SIGNED) - CAST(lotto_ticket_level AS SIGNED)) * 50,
  lotto_ticket_level = GREATEST(lotto_ticket_level, level)
WHERE status = 0
  AND level > lotto_ticket_level;
