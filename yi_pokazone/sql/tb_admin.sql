-- =============================================================================
-- Pokazone - Admin (백오피스) 계정
-- =============================================================================
-- DB : poka
-- Charset : utf8mb4 / utf8mb4_unicode_ci
-- -----------------------------------------------------------------------------

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. 관리자 계정
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tb_admin`;
CREATE TABLE `tb_admin` (
    `ad_idx`             INT UNSIGNED   NOT NULL AUTO_INCREMENT            COMMENT '관리자 고유번호',
    `ad_id`              VARCHAR(30)    NOT NULL                           COMMENT '로그인 아이디',
    `ad_pw`              VARCHAR(255)   NOT NULL                           COMMENT '비밀번호 (password_hash)',
    `ad_name`            VARCHAR(50)    NOT NULL                           COMMENT '이름(표시명)',
    `ad_status`          TINYINT UNSIGNED NOT NULL DEFAULT 1               COMMENT '0:비활성 1:정상',
    `ad_level`           TINYINT UNSIGNED NOT NULL DEFAULT 1               COMMENT '관리자 등급 (확장용)',
    `ad_login_count`     INT UNSIGNED   NOT NULL DEFAULT 0                 COMMENT '로그인 누적',
    `ad_last_login_at`   DATETIME           NULL DEFAULT NULL              COMMENT '마지막 로그인 일시',
    `ad_last_login_ip`   VARCHAR(45)        NULL DEFAULT NULL              COMMENT '마지막 로그인 IP',
    `ad_created_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록일시',
    `ad_updated_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    PRIMARY KEY (`ad_idx`),
    UNIQUE KEY `uq_ad_id` (`ad_id`),
    KEY `idx_ad_status` (`ad_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='관리자 계정';

-- -----------------------------------------------------------------------------
-- 2. 관리자 로그인 이력
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tb_admin_login_log`;
CREATE TABLE `tb_admin_login_log` (
    `log_idx`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT              COMMENT '로그 고유번호',
    `ad_idx`          INT UNSIGNED        NULL DEFAULT NULL                COMMENT '관리자 idx (실패 시 NULL)',
    `log_ad_id`       VARCHAR(30)         NULL DEFAULT NULL                COMMENT '시도한 아이디',
    `log_ip`          VARCHAR(45)     NOT NULL                             COMMENT 'IP',
    `log_user_agent`  VARCHAR(500)        NULL DEFAULT NULL                COMMENT 'User-Agent',
    `log_status`      TINYINT UNSIGNED NOT NULL DEFAULT 0                  COMMENT '1:성공 0:실패',
    `log_message`     VARCHAR(100)        NULL DEFAULT NULL                COMMENT '실패 사유',
    `log_created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP   COMMENT '기록일시',
    PRIMARY KEY (`log_idx`),
    KEY `idx_ad_idx`     (`ad_idx`, `log_created_at`),
    KEY `idx_created_at` (`log_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='관리자 로그인 이력';

-- -----------------------------------------------------------------------------
-- 초기 관리자 (초기 비밀번호: password — 반드시 운영 전 변경)
--   $2y$10$... = password_hash('password', PASSWORD_DEFAULT) 와 동일한 포맷(예: Laravel 기본)
-- -----------------------------------------------------------------------------
INSERT INTO `tb_admin` (`ad_id`, `ad_pw`, `ad_name`, `ad_status`, `ad_level`) VALUES
(
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    '최초 관리자',
    1,
    1
);
