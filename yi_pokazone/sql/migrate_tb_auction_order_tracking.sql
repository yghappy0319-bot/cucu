-- 경매 주문: 택배사·운송장 번호
SET NAMES utf8mb4;

ALTER TABLE `tb_auction_order`
    ADD COLUMN `ao_courier_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '택배사명' AFTER `ao_addr_message`,
    ADD COLUMN `ao_tracking_no` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '운송장번호' AFTER `ao_courier_name`,
    ADD COLUMN `ao_shipped_at` DATETIME NULL DEFAULT NULL COMMENT '운송장 등록일시' AFTER `ao_tracking_no`;
