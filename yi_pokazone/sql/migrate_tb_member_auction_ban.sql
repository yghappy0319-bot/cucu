-- 낙찰 주문 취소 등 경매 이용 제한
SET NAMES utf8mb4;

ALTER TABLE `tb_member`
    ADD COLUMN `mb_auction_banned` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '경매 이용 금지(1)',
    ADD COLUMN `mb_auction_banned_at` DATETIME NULL DEFAULT NULL COMMENT '경매 이용 금지 일시',
    ADD COLUMN `mb_auction_ban_memo` VARCHAR(200) NULL DEFAULT NULL COMMENT '경매 이용 금지 사유';
