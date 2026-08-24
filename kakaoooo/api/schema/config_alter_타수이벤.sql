-- 타수 이벤트 시작/종료 시각 (.타수이벤시작 / .타수이벤종료)
ALTER TABLE `config`
  ADD COLUMN `타수이벤시작` DATETIME NULL DEFAULT NULL COMMENT '타수 이벤트 시작 시각',
  ADD COLUMN `타수이벤종료` DATETIME NULL DEFAULT NULL COMMENT '타수 이벤트 종료 시각';
