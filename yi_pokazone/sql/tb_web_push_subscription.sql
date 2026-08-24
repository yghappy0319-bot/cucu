-- Web Push 구독 (거래 메시지 등). UTF-8
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_web_push_subscription` (
    `wp_idx`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mb_idx`         INT UNSIGNED    NOT NULL COMMENT '회원',
    `endpoint`       TEXT            NOT NULL COMMENT 'Push endpoint URL',
    `endpoint_hash`  CHAR(64)        NOT NULL COMMENT 'sha256(endpoint) hex',
    `p256dh`         VARCHAR(255)    NOT NULL,
    `auth`           VARCHAR(255)    NOT NULL,
    `user_agent`     VARCHAR(512)    NULL DEFAULT NULL,
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`wp_idx`),
    UNIQUE KEY `uq_endpoint_hash` (`endpoint_hash`),
    KEY `idx_mb` (`mb_idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Web Push 구독';
