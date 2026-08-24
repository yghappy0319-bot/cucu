-- tb_cash_withdraw: 총액·수수료·실입금액 컬럼 추가 (기존 cw_amount = 실입금액 호환)
SET NAMES utf8mb4;

SET @has_gross := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_cash_withdraw'
      AND COLUMN_NAME = 'cw_gross_amount'
);
SET @sql_gross := IF(
    @has_gross = 0,
    'ALTER TABLE `tb_cash_withdraw`
        ADD COLUMN `cw_gross_amount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''출금 신청 총액(캐시 차감)'' AFTER `cw_amount`,
        ADD COLUMN `cw_fee_rate` TINYINT UNSIGNED NOT NULL DEFAULT 10 COMMENT ''수수료율(%)'' AFTER `cw_gross_amount`,
        ADD COLUMN `cw_fee_amount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''수수료 금액'' AFTER `cw_fee_rate`,
        ADD COLUMN `cw_net_amount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''실제 입금액'' AFTER `cw_fee_amount`',
    'SELECT 1'
);
PREPARE stmt_gross FROM @sql_gross;
EXECUTE stmt_gross;
DEALLOCATE PREPARE stmt_gross;

-- 기존 데이터: 수수료 없이 신청된 건은 총액=실입금액으로 이관
UPDATE `tb_cash_withdraw`
SET
    `cw_gross_amount` = `cw_amount`,
    `cw_fee_rate` = 0,
    `cw_fee_amount` = 0,
    `cw_net_amount` = `cw_amount`
WHERE `cw_gross_amount` = 0 AND `cw_amount` > 0;
