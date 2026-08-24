-- Pokazone - 거래글 1:1 문의 채팅 (판매자 ↔ 문의 회원)
-- Charset: utf8mb4_unicode_ci
-- 기존 DB: 아래 두 테이블만 추가하면 됩니다.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_trade_room` (
    `room_idx`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tr_idx`           INT UNSIGNED NOT NULL COMMENT '거래글',
    `buyer_mb_idx`     INT UNSIGNED NOT NULL COMMENT '문의자 회원번호',
    `room_buyer_read_msg_idx`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '구매자 마지막 읽음 msg_idx',
    `room_seller_read_msg_idx` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '판매자 마지막 읽음 msg_idx',
    `room_buyer_archived_at`   DATETIME         NULL DEFAULT NULL COMMENT '구매자 대화 종료 시각',
    `room_seller_archived_at`  DATETIME         NULL DEFAULT NULL COMMENT '판매자 대화 종료 시각',
    `room_buyer_hidden_at`     DATETIME         NULL DEFAULT NULL COMMENT '구매자 목록 숨김 시각',
    `room_seller_hidden_at`    DATETIME         NULL DEFAULT NULL COMMENT '판매자 목록 숨김 시각',
    `room_updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`room_idx`),
    KEY `idx_trade_buyer` (`tr_idx`, `buyer_mb_idx`),
    KEY `idx_tr_updated` (`tr_idx`, `room_updated_at`),
    CONSTRAINT `fk_tc_room_trade` FOREIGN KEY (`tr_idx`) REFERENCES `tb_trade` (`tr_idx`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='거래글 문의방';

CREATE TABLE IF NOT EXISTS `tb_trade_room_msg` (
    `msg_idx`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `room_idx`         INT UNSIGNED NOT NULL,
    `mb_idx`           INT UNSIGNED NOT NULL COMMENT '발신자',
    `msg_body`         VARCHAR(2000)   NOT NULL,
    `msg_image`        VARCHAR(512)    NULL DEFAULT NULL COMMENT '첨부 이미지 웹경로 (/uploads/trade/...)',
    `msg_created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`msg_idx`),
    KEY `idx_room_created` (`room_idx`, `msg_idx`),
    CONSTRAINT `fk_tc_msg_room` FOREIGN KEY (`room_idx`) REFERENCES `tb_trade_room` (`room_idx`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='거래 문의 메시지';
