-- 단소 10~14강 3시간 구간당 4회 한도용 (한 번만 실행)
-- danso_window 구간마다 danso_used 리셋

ALTER TABLE tb_member ADD COLUMN `danso_used` INT NOT NULL DEFAULT 0 COMMENT '현재 3시간 구간 내 단소 시전 횟수 (10~14강)';
ALTER TABLE tb_member ADD COLUMN `danso_window` DATETIME DEFAULT NULL COMMENT '단소 3시간 구간 시작 시각 (경과 시 danso_used 리셋)';
