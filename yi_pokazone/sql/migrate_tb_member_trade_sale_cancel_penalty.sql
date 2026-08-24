-- 거래 판매 취소 누적 · 입금 확인 후 3회 이상 시 1일 판매(등록) 정지
SET NAMES utf8mb4;

ALTER TABLE `tb_member`
    ADD COLUMN `mb_trade_sale_cancel_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '입금 확인 후 판매 취소 누적 횟수',
    ADD COLUMN `mb_trade_sell_suspended_until` DATETIME NULL DEFAULT NULL COMMENT '거래 판매글 등록 정지 해제 일시',
    ADD COLUMN `mb_trade_sell_suspend_memo` VARCHAR(200) NULL DEFAULT NULL COMMENT '거래 판매글 등록 정지 사유';
