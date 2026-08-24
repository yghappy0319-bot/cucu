-- 홀짝 난이도 모드 (easy|hard) — 회원별 저장
ALTER TABLE `tb_odd_even_state`
  ADD COLUMN `odds_mode` VARCHAR(8) NOT NULL DEFAULT 'easy'
    COMMENT '확률 모드: easy|hard'
    AFTER `pending_at`;
