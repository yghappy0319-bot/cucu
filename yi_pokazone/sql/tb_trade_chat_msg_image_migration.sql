-- 거래 채팅 메시지에 이미지 첨부 컬럼 추가 (기존 설치 DB용)
SET NAMES utf8mb4;

ALTER TABLE `tb_trade_room_msg`
    ADD COLUMN `msg_image` VARCHAR(512) NULL DEFAULT NULL
    COMMENT '첨부 이미지 웹경로 (/uploads/trade/...)'
    AFTER `msg_body`;
