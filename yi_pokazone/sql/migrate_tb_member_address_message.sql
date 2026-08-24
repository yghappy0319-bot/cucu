-- 배송지 주소록: 배송 메시지 컬럼 추가
SET NAMES utf8mb4;

ALTER TABLE `tb_member_address`
    ADD COLUMN `addr_message` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '배송 메시지' AFTER `addr_detail`;
