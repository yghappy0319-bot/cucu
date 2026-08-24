-- 경매 주문: 배송 메시지 스냅샷 컬럼 추가
SET NAMES utf8mb4;

ALTER TABLE `tb_auction_order`
    ADD COLUMN `ao_addr_message` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '배송 메시지' AFTER `ao_addr_detail`;
