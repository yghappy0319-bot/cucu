-- 야바위: .마감 시각부터 30분 미굴림 시 .점수에서 자동 3회 처리
ALTER TABLE `config`
  ADD COLUMN `야바위게임시작` DATETIME NULL DEFAULT NULL COMMENT '야바위 .마감 시각';
