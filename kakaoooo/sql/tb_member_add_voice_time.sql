-- 보이스룸(보룸) 누적 이용시간(분) 저장용 (한 번만 실행)
ALTER TABLE tb_member
  ADD COLUMN `voice_time` INT NOT NULL DEFAULT 0 COMMENT '보이스룸/보룸 누적 이용시간(분)';

