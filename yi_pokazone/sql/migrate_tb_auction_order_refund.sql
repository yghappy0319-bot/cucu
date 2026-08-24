-- 경매 주문: 구매확정 후 환불
SET NAMES utf8mb4;

ALTER TABLE `tb_auction_order`
    ADD COLUMN `ao_refunded_at` DATETIME NULL DEFAULT NULL COMMENT '환불 완료 일시' AFTER `ao_platform_fee`,
    ADD COLUMN `ao_refund_amount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '환불 금액(원)' AFTER `ao_refunded_at`;
