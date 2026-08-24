-- 기존 rb_sell 단일 컬럼 → rb_count + rb_sells(JSON 배열) 로 변경
-- (이미 신규 스키마면 실행하지 마세요.)

ALTER TABLE `tb_admin_record_board`
    ADD COLUMN `rb_count` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `rb_memo`,
    ADD COLUMN `rb_sells` TEXT NULL AFTER `rb_sell`;

UPDATE `tb_admin_record_board`
SET `rb_count` = 1,
    `rb_sells` = CONCAT('[', IFNULL(`rb_sell`, 0), ']')
WHERE `rb_sells` IS NULL;

ALTER TABLE `tb_admin_record_board`
    DROP COLUMN `rb_sell`,
    MODIFY `rb_sells` TEXT NOT NULL;
