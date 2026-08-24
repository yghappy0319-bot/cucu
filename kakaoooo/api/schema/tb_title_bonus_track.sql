-- 타이틀보상 연속 일수: 타이틀 문자열당 1행. 보유자가 바뀌어도(뺏겨도) 같은 타이틀이면 일수 유지.
CREATE TABLE IF NOT EXISTS tb_title_bonus_track (
  title_key VARCHAR(191) NOT NULL PRIMARY KEY,
  streak INT NOT NULL DEFAULT 0 COMMENT '연속 타이틀보상 일차',
  last_bonus_date DATE NULL COMMENT '마지막 타이틀보상 지급일'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
