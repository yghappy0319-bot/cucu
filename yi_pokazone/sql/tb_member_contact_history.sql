-- =============================================================================
-- Pokazone - 회원 연락처·배송지 변경 이력 (사기 방지 백업)
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_member_contact_history` (
    `mch_idx`        BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT            COMMENT '이력 고유번호',
    `mb_idx`           INT UNSIGNED     NOT NULL                           COMMENT '대상 회원 고유번호',
    `mch_type`         VARCHAR(20)      NOT NULL                           COMMENT 'email, phone, address',
    `mch_action`       VARCHAR(20)      NOT NULL DEFAULT 'update'          COMMENT 'create, update, delete',
    `mch_old_value`    TEXT                 NULL DEFAULT NULL              COMMENT '변경 전 값 (주소는 JSON)',
    `mch_new_value`    TEXT                 NULL DEFAULT NULL              COMMENT '변경 후 값 (주소는 JSON)',
    `addr_idx`         INT UNSIGNED         NULL DEFAULT NULL              COMMENT '배송지 고유번호 (주소 변경 시)',
    `mch_actor_type`   VARCHAR(10)      NOT NULL DEFAULT 'member'          COMMENT 'member, admin',
    `actor_mb_idx`     INT UNSIGNED         NULL DEFAULT NULL              COMMENT '변경 수행 회원',
    `actor_ad_idx`     INT UNSIGNED         NULL DEFAULT NULL              COMMENT '변경 수행 관리자',
    `mch_ip`           VARCHAR(45)      NOT NULL DEFAULT ''                COMMENT '요청 IP',
    `mch_user_agent`   VARCHAR(500)     NOT NULL DEFAULT ''                COMMENT 'User-Agent',
    `mch_created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '기록일시',
    PRIMARY KEY (`mch_idx`),
    KEY `idx_mb_type_created` (`mb_idx`, `mch_type`, `mch_created_at`),
    KEY `idx_addr_created` (`addr_idx`, `mch_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='회원 이메일·휴대폰·배송지 변경 이력';
