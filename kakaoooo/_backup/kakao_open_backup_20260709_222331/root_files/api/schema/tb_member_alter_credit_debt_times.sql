-- 누적 신불 진입 회차 (1회차=진입일+1일에 0냥 초기화, 2회차=+2일, …)
ALTER TABLE `tb_member`
  ADD COLUMN `credit_debt_times` INT UNSIGNED NOT NULL DEFAULT 0
    COMMENT '신불 누적 진입 횟수(0냥 변제 가능일 = DATE(진입) + 회차 일수)';

-- 기존 데이터: 과거 카운터 없음 → 1회로 간주 (기존과 동일: 다음날 초기화)
UPDATE `tb_member`
SET `credit_debt_times` = GREATEST(IFNULL(`credit_debt_times`, 0), 1)
WHERE `title` = '🆘신불자'
   OR (IFNULL(`credit`, 0) = 1 AND `point` < 0);
