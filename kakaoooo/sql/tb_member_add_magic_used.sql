-- 마법 시전 횟수 한도용 (한 번만 실행)
-- 4시간당 10~14강 지호2 3명, 15강 마법 5명 (magic_window 구간마다 magic_used 리셋)

ALTER TABLE tb_member ADD COLUMN `magic_used` INT NOT NULL DEFAULT 0 COMMENT '현재 4시간 구간 내 마법 시전 횟수';
ALTER TABLE tb_member ADD COLUMN `magic_window` DATETIME DEFAULT NULL COMMENT '마법 4시간 구간 시작 시각 (경과 시 magic_used 리셋)';
