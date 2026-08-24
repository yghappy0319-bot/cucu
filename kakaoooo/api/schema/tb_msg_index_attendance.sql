-- 출석 크론·타수 집계 속도 (DATE(regdate) 대신 regdate 범위 조회용)
-- 재실행 가능: mysql kakao1 < api/schema/tb_msg_index_attendance.sql
CREATE INDEX IF NOT EXISTS idx_msg_regdate_tasu_nick ON tb_msg (regdate, tasu, nickname);
