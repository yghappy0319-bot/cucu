-- 외부 판매처 행별 플랫폼·업체명 (쿠팡, 신세계 등)
SET NAMES utf8mb4;

ALTER TABLE `tb_card_price_offer`
    ADD COLUMN `co_type` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '플랫폼·업체(쿠팡, 신세계 등)' AFTER `co_site_name`;
