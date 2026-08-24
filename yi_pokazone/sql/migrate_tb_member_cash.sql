-- 회원 캐시(경매 낙찰 결제용 테스트)
SET NAMES utf8mb4;

ALTER TABLE `tb_member`
    ADD COLUMN `mb_cash` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '보유 캐시(원)' AFTER `mb_point`;
