-- tb_point_log·tb_battle 인덱스 (InnoDB 전환 완료 후 실행, 재실행 가능)
-- mysql kakao1 < api/schema/tb_point_log_tune.sql

CREATE INDEX IF NOT EXISTS idx_point_log_status_nick ON tb_point_log (status, nick);
CREATE INDEX IF NOT EXISTS idx_point_log_nick_status_reg ON tb_point_log (nick, status, regdate);
CREATE INDEX IF NOT EXISTS idx_battle_win ON tb_battle (win);
