-- 거래 메시지함 — 결제 요청·무통장 입금 (기존 채팅 DB에 추가)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_trade_payment` (
    `pay_idx`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `room_idx`          INT UNSIGNED     NOT NULL                COMMENT '문의방',
    `tr_idx`            INT UNSIGNED     NOT NULL                COMMENT '거래글',
    `request_msg_idx`   BIGINT UNSIGNED  NULL DEFAULT NULL       COMMENT '결제 요청 메시지',
    `seller_mb_idx`     INT UNSIGNED     NOT NULL,
    `buyer_mb_idx`      INT UNSIGNED     NOT NULL,
    `pay_amount`        INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '결제 금액(원)',
    `product_label`     VARCHAR(200)     NOT NULL DEFAULT ''     COMMENT '상품명',
    `pay_status`        TINYINT UNSIGNED NOT NULL DEFAULT 0      COMMENT '0:요청 1:입금확인중',
    `depositor_name`    VARCHAR(50)      NOT NULL DEFAULT ''     COMMENT '입금자명',
    `pay_created_at`    DATETIME         NOT NULL,
    `pay_submitted_at`  DATETIME         NULL DEFAULT NULL       COMMENT '입금 신청 시각',
    PRIMARY KEY (`pay_idx`),
    KEY `idx_room` (`room_idx`, `pay_created_at`),
    KEY `idx_request_msg` (`request_msg_idx`),
    CONSTRAINT `fk_tp_room` FOREIGN KEY (`room_idx`) REFERENCES `tb_trade_room` (`room_idx`) ON DELETE CASCADE,
    CONSTRAINT `fk_tp_trade` FOREIGN KEY (`tr_idx`) REFERENCES `tb_trade` (`tr_idx`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='거래 메시지 결제 요청';

-- 컬럼은 중복 실행해도 안전 (이미 있으면 건너뜀)
SET @has_msg_type := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_room_msg'
      AND COLUMN_NAME = 'msg_type'
);
SET @sql_msg_type := IF(
    @has_msg_type = 0,
    'ALTER TABLE `tb_trade_room_msg` ADD COLUMN `msg_type` VARCHAR(20) NOT NULL DEFAULT ''text'' COMMENT ''text|payment_request'' AFTER `msg_body`',
    'SELECT 1'
);
PREPARE stmt_msg_type FROM @sql_msg_type;
EXECUTE stmt_msg_type;
DEALLOCATE PREPARE stmt_msg_type;

SET @has_msg_pay_idx := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_room_msg'
      AND COLUMN_NAME = 'msg_pay_idx'
);
SET @sql_msg_pay_idx := IF(
    @has_msg_pay_idx = 0,
    'ALTER TABLE `tb_trade_room_msg` ADD COLUMN `msg_pay_idx` INT UNSIGNED NULL DEFAULT NULL COMMENT ''tb_trade_payment.pay_idx'' AFTER `msg_type`',
    'SELECT 1'
);
PREPARE stmt_msg_pay_idx FROM @sql_msg_pay_idx;
EXECUTE stmt_msg_pay_idx;
DEALLOCATE PREPARE stmt_msg_pay_idx;
