-- 판매자 상점 소개
SET NAMES utf8mb4;

ALTER TABLE `tb_member`
    ADD COLUMN `mb_shop_intro` VARCHAR(500) NULL DEFAULT NULL COMMENT '판매자 상점 소개';
