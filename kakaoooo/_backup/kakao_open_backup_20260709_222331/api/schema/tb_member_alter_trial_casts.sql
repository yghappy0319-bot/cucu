-- 무기 +10 맛보기 시전 (웹·채팅 공용)
ALTER TABLE tb_member
  ADD COLUMN IF NOT EXISTS trial_casts INT NOT NULL DEFAULT 0 COMMENT '맛보기 시전 잔여',
  ADD COLUMN IF NOT EXISTS trial_casts_init TINYINT NOT NULL DEFAULT 0 COMMENT '맛보기 1회 부여 완료';

-- 기존 +10 이상 무기 보유자 1회성 부여 (배포 시 1회 실행)
UPDATE tb_member
SET trial_casts = 3, trial_casts_init = 1
WHERE IFNULL(enhance, 0) >= 10
  AND TRIM(IFNULL(item, '')) != ''
  AND trial_casts_init = 0;
