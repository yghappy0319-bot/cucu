-- 환불 신청(판매자 수락 후 완료)
SET NAMES utf8mb4;

ALTER TABLE `tb_auction_order`
    ADD COLUMN `ao_refund_requested_at` DATETIME NULL DEFAULT NULL COMMENT '환불 신청 일시' AFTER `ao_refund_amount`;
