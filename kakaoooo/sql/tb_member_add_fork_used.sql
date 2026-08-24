-- 포크 10~14강 3시간 구간당 4회 한도용 (한 번만 실행)
-- fork_window 구간마다 fork_used 리셋

ALTER TABLE tb_member ADD COLUMN `fork_used` INT NOT NULL DEFAULT 0 COMMENT '현재 3시간 구간 내 포크 뺏기 횟수 (10~14강)';
ALTER TABLE tb_member ADD COLUMN `fork_window` DATETIME DEFAULT NULL COMMENT '포크 3시간 구간 시작 시각 (경과 시 fork_used 리셋)';
