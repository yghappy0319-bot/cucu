-- 보이스룸 누적시간(voice_time) 자정 초기화용 날짜 컬럼 (한 번만 실행)
ALTER TABLE tb_member
  ADD COLUMN `voice_time_reset_date` DATE DEFAULT NULL COMMENT 'voice_time 마지막 초기화 날짜 (자정 기준)';

