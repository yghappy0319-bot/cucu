-- 신불자 최초 진입 시각 (credit_debt_at 은 1시간마다 추가차감 기준으로 갱신되므로 별도 보관)
ALTER TABLE `tb_member`
  ADD COLUMN `credit_debt_entered_at` DATETIME NULL DEFAULT NULL
    COMMENT '신불자 최초 진입 시각';

-- 이미 신불자 타이틀인데 컬럼이 비어 있으면, 남아 있는 credit_debt_at 또는 현재 시각으로 대략 보정
UPDATE `tb_member`
SET `credit_debt_entered_at` = COALESCE(`credit_debt_at`, NOW())
WHERE `title` = '🆘신불자'
  AND `credit_debt_entered_at` IS NULL;
