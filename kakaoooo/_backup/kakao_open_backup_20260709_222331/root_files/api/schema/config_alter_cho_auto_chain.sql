-- 초성 퀴즈: 정답 후 다음 문제 자동 출제 여부 (`.초성초기화`로 0)
ALTER TABLE `config`
  ADD COLUMN `초성자동연속` TINYINT(1) NOT NULL DEFAULT 0;
