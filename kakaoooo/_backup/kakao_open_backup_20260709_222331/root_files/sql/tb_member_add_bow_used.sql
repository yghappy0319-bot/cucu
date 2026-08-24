-- 활 3시간 구간당 4회 한도용 (한 번만 실행)
-- bow_window 구간마다 bow_used 리셋

ALTER TABLE tb_member ADD COLUMN `bow_used` INT NOT NULL DEFAULT 0 COMMENT '현재 3시간 구간 내 활 시전 횟수';
ALTER TABLE tb_member ADD COLUMN `bow_window` DATETIME DEFAULT NULL COMMENT '활 3시간 구간 시작 시각 (경과 시 bow_used 리셋)';
