-- 기존 DB: 회원 연락처·배송지 변경 이력 테이블 추가
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_member_contact_history` (
    `mch_idx`        BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `mb_idx`           INT UNSIGNED     NOT NULL,
    `mch_type`         VARCHAR(20)      NOT NULL COMMENT 'email, phone, address',
    `mch_action`       VARCHAR(20)      NOT NULL DEFAULT 'update' COMMENT 'create, update, delete',
    `mch_old_value`    TEXT                 NULL DEFAULT NULL COMMENT '변경 전 (주소 JSON)',
    `mch_new_value`    TEXT                 NULL DEFAULT NULL COMMENT '변경 후 (주소 JSON)',
    `addr_idx`         INT UNSIGNED         NULL DEFAULT NULL,
    `mch_actor_type`   VARCHAR(10)      NOT NULL DEFAULT 'member' COMMENT 'member, admin',
    `actor_mb_idx`     INT UNSIGNED         NULL DEFAULT NULL,
    `actor_ad_idx`     INT UNSIGNED         NULL DEFAULT NULL,
    `mch_ip`           VARCHAR(45)      NOT NULL DEFAULT '',
    `mch_user_agent`   VARCHAR(500)     NOT NULL DEFAULT '',
    `mch_created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`mch_idx`),
    KEY `idx_mb_type_created` (`mb_idx`, `mch_type`, `mch_created_at`),
    KEY `idx_addr_created` (`addr_idx`, `mch_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='회원 이메일·휴대폰·배송지 변경 이력';
