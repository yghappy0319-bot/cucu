-- .은총 (닉네임) 사용 시 5분 동안 14→15강 확률 0.1% 고정 (한 번만 실행)
ALTER TABLE tb_member ADD COLUMN `은총` DATETIME NULL DEFAULT NULL COMMENT '은총 버프 만료 시각 (이 시간까지 14→15강 0.1% 적용)';
