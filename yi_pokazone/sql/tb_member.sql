-- =============================================================================
-- Pokazone - Member Tables
-- =============================================================================
-- DB : poka
-- Charset : utf8mb4 / utf8mb4_unicode_ci
-- Prefix  : tb_
-- -----------------------------------------------------------------------------

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. 회원 기본 테이블
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tb_member`;
CREATE TABLE `tb_member` (
    `mb_idx`             INT UNSIGNED   NOT NULL AUTO_INCREMENT            COMMENT '회원 고유번호',
    `mb_id`              VARCHAR(30)    NOT NULL                           COMMENT '아이디 (영문+숫자 4~20)',
    `mb_pw`              VARCHAR(255)   NOT NULL                           COMMENT '비밀번호 (password_hash)',
    `mb_name`            VARCHAR(50)    NOT NULL                           COMMENT '이름',
    `mb_nick`            VARCHAR(30)    NOT NULL                           COMMENT '닉네임',
    `mb_email`           VARCHAR(100)   NOT NULL                           COMMENT '이메일',
    `mb_phone`           VARCHAR(20)        NULL DEFAULT NULL              COMMENT '휴대폰 (숫자만)',
    `mb_level`           TINYINT UNSIGNED NOT NULL DEFAULT 1               COMMENT '회원등급 1:일반 3:인증(경매예외) 5:VIP 9:관리자',
    `mb_status`          TINYINT UNSIGNED NOT NULL DEFAULT 1               COMMENT '상태 0:휴면 1:정상 2:정지 3:탈퇴',
    `mb_login_block_until` DATETIME           NULL DEFAULT NULL            COMMENT '로그인 제한 해제 시각',
    `mb_login_block_days`  TINYINT UNSIGNED     NULL DEFAULT NULL          COMMENT '로그인 제한 일수',
    `mb_login_block_reason` VARCHAR(500)        NULL DEFAULT NULL          COMMENT '로그인 제한 사유',
    `mb_login_block_at`    DATETIME           NULL DEFAULT NULL            COMMENT '로그인 제한 적용 시각',
    `mb_login_block_ad_idx` INT UNSIGNED        NULL DEFAULT NULL          COMMENT '로그인 제한 처리 관리자',
    `mb_point`           INT UNSIGNED   NOT NULL DEFAULT 0                 COMMENT '보유 포인트',
    `mb_cash`            INT UNSIGNED   NOT NULL DEFAULT 0                 COMMENT '보유 캐시(원)',
    `mb_settle_bank`     VARCHAR(30)        NULL DEFAULT NULL              COMMENT '정산 은행명',
    `mb_settle_holder`   VARCHAR(50)        NULL DEFAULT NULL              COMMENT '정산 예금주',
    `mb_settle_account`  VARCHAR(30)        NULL DEFAULT NULL              COMMENT '정산 계좌번호(숫자)',
    `mb_settle_updated_at` DATETIME           NULL DEFAULT NULL            COMMENT '정산계좌 수정일시',
    `mb_auction_banned`    TINYINT(1)     NOT NULL DEFAULT 0                 COMMENT '경매 이용 금지(1)',
    `mb_auction_banned_at` DATETIME           NULL DEFAULT NULL              COMMENT '경매 이용 금지 일시',
    `mb_auction_ban_memo`  VARCHAR(200)       NULL DEFAULT NULL              COMMENT '경매 이용 금지 사유',
    `mb_trade_sale_cancel_count` INT UNSIGNED NOT NULL DEFAULT 0             COMMENT '입금 확인 후 판매 취소 누적 횟수',
    `mb_trade_sell_suspended_until` DATETIME NULL DEFAULT NULL              COMMENT '거래 판매글 등록 정지 해제 일시',
    `mb_trade_sell_suspend_memo` VARCHAR(200) NULL DEFAULT NULL              COMMENT '거래 판매글 등록 정지 사유',
    `mb_profile_img`     VARCHAR(255)       NULL DEFAULT NULL              COMMENT '프로필 이미지 경로',
    `mb_shop_intro`      VARCHAR(500)       NULL DEFAULT NULL              COMMENT '판매자 상점 소개',
    `mb_login_count`     INT UNSIGNED   NOT NULL DEFAULT 0                 COMMENT '로그인 누적 횟수',
    `mb_last_login_at`   DATETIME           NULL DEFAULT NULL              COMMENT '마지막 로그인 일시',
    `mb_last_login_ip`   VARCHAR(45)        NULL DEFAULT NULL              COMMENT '마지막 로그인 IP',
    `mb_agree_terms`     TINYINT(1)     NOT NULL DEFAULT 0                 COMMENT '이용약관 동의',
    `mb_agree_privacy`   TINYINT(1)     NOT NULL DEFAULT 0                 COMMENT '개인정보 동의',
    `mb_agree_marketing` TINYINT(1)     NOT NULL DEFAULT 0                 COMMENT '마케팅 수신동의',
    `mb_created_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '가입일시',
    `mb_updated_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    PRIMARY KEY (`mb_idx`),
    UNIQUE KEY `uq_mb_id`    (`mb_id`),
    UNIQUE KEY `uq_mb_nick`  (`mb_nick`),
    UNIQUE KEY `uq_mb_email` (`mb_email`),
    KEY `idx_mb_status`      (`mb_status`),
    KEY `idx_mb_created_at`  (`mb_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='회원 기본';


-- -----------------------------------------------------------------------------
-- 2. 로그인 이력 테이블
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tb_member_login_log`;
CREATE TABLE `tb_member_login_log` (
    `log_idx`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT              COMMENT '로그 고유번호',
    `mb_idx`         INT UNSIGNED        NULL DEFAULT NULL                COMMENT '회원 고유번호 (실패 시 NULL 가능)',
    `log_mb_id`      VARCHAR(30)         NULL DEFAULT NULL                COMMENT '시도한 아이디',
    `log_ip`         VARCHAR(45)     NOT NULL                             COMMENT '접속 IP',
    `log_user_agent` VARCHAR(500)        NULL DEFAULT NULL                COMMENT 'User-Agent',
    `log_status`     TINYINT UNSIGNED NOT NULL DEFAULT 1                  COMMENT '1:성공 0:실패',
    `log_message`    VARCHAR(100)        NULL DEFAULT NULL                COMMENT '실패사유',
    `log_created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP   COMMENT '기록일시',
    PRIMARY KEY (`log_idx`),
    KEY `idx_mb_idx`     (`mb_idx`, `log_created_at`),
    KEY `idx_created_at` (`log_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='회원 로그인 이력';


-- -----------------------------------------------------------------------------
-- 테스트용 관리자 계정 (비밀번호: admin1234)
--   해시는 PHP password_hash('admin1234', PASSWORD_DEFAULT) 로 생성됨
-- -----------------------------------------------------------------------------
-- INSERT INTO `tb_member`
--     (`mb_id`, `mb_pw`, `mb_name`, `mb_nick`, `mb_email`, `mb_level`, `mb_agree_terms`, `mb_agree_privacy`)
-- VALUES
--     ('admin', '$2y$10$...여기에생성된해시...', '관리자', '관리자', 'admin@pokazone.com', 9, 1, 1);
