-- (레거시) 회원별 타이틀보상 추적 — 현재 로직은 tb_title_bonus_track.sql(타이틀 문자열당 추적) 사용. 이미 적용한 DB는 그대로 두어도 무방.
ALTER TABLE tb_member
  ADD COLUMN title_bonus_streak INT NOT NULL DEFAULT 0 COMMENT '현 타이틀 기준 타이틀보상 연속 일수',
  ADD COLUMN title_bonus_last_date DATE NULL COMMENT '마지막 타이틀보상 지급일';
