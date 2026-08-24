-- 단소/활 공격 1회 방어용 보호 수치 (한 번만 실행)
-- tb_member에 protect 컬럼이 없을 때만 DB에서 실행하세요.
-- .보호 닉 또는 마법 시전으로 상대 보호 +1, 공격 시 보호 1회 소모 후 방어

ALTER TABLE tb_member ADD COLUMN `protect` INT NOT NULL DEFAULT 0 COMMENT '단소/활 공격 1회 방어 (마법 시전·.보호로 증가)';
ALTER TABLE tb_member ADD COLUMN `protect_used` INT NOT NULL DEFAULT 0 COMMENT '보호 부여 사용 횟수 (마법 10~14강 8회, 15강 20회 한도, 자정 리셋)';
ALTER TABLE tb_member ADD COLUMN `protect_reset_date` DATE DEFAULT NULL COMMENT '보호 부여 횟수 리셋 기준일 (매일 자정 후 새 날짜면 protect_used 0으로)';
