-- =============================================================================
-- Pokazone - 회원 배송지 주소록
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_member_address` (
    `addr_idx`        INT UNSIGNED     NOT NULL AUTO_INCREMENT            COMMENT '주소 고유번호',
    `mb_idx`          INT UNSIGNED     NOT NULL                           COMMENT '회원 고유번호',
    `addr_label`      VARCHAR(30)      NOT NULL DEFAULT '배송지'          COMMENT '배송지명',
    `addr_name`       VARCHAR(50)      NOT NULL                           COMMENT '받는 분',
    `addr_phone`      VARCHAR(20)      NOT NULL                           COMMENT '연락처',
    `addr_zip`        VARCHAR(10)      NOT NULL                           COMMENT '우편번호',
    `addr_road`       VARCHAR(255)     NOT NULL                           COMMENT '도로명주소',
    `addr_jibun`      VARCHAR(255)         NULL DEFAULT NULL              COMMENT '지번주소',
    `addr_extra`      VARCHAR(100)     NOT NULL DEFAULT ''                COMMENT '참고항목',
    `addr_detail`     VARCHAR(100)     NOT NULL DEFAULT ''                COMMENT '상세주소',
    `addr_message`    VARCHAR(200)     NOT NULL DEFAULT ''                COMMENT '배송 메시지',
    `addr_is_default` TINYINT(1)       NOT NULL DEFAULT 0                 COMMENT '기본 배송지 여부',
    `addr_status`     TINYINT UNSIGNED NOT NULL DEFAULT 1                 COMMENT '1:사용 9:삭제',
    `addr_created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록일시',
    `addr_updated_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    PRIMARY KEY (`addr_idx`),
    KEY `idx_mb_addr` (`mb_idx`, `addr_status`, `addr_is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='회원 배송지 주소록';
