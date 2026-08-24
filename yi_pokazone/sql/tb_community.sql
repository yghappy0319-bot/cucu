-- =============================================================================
-- Pokazone - Community Board Tables
-- =============================================================================
-- DB      : poka
-- Charset : utf8mb4 / utf8mb4_unicode_ci
-- Prefix  : tb_
-- -----------------------------------------------------------------------------

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. 커뮤니티 게시글 테이블
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tb_community`;
CREATE TABLE `tb_community` (
    `co_idx`         INT UNSIGNED     NOT NULL AUTO_INCREMENT            COMMENT '게시글 고유번호',
    `mb_idx`         INT UNSIGNED     NOT NULL                           COMMENT '작성자 회원번호',
    `co_category`    VARCHAR(20)      NOT NULL DEFAULT 'free'            COMMENT '카테고리 free/brag/question/info/tip/tcg_cafe',
    `co_source`      VARCHAR(20)      NOT NULL DEFAULT 'pokazone'        COMMENT 'pokazone:포카존커뮤니티 / naver_tcg:네이버포켓몬TCG카페',
    `co_external_url` VARCHAR(500)        NULL DEFAULT NULL              COMMENT '외부 원문 URL',
    `co_external_nick` VARCHAR(50)        NULL DEFAULT NULL              COMMENT '외부 원문 작성자 닉네임',
    `co_title`       VARCHAR(150)     NOT NULL                           COMMENT '제목',
    `co_content`     MEDIUMTEXT       NOT NULL                           COMMENT '본문 (plain text)',
    `co_views`       INT UNSIGNED     NOT NULL DEFAULT 0                 COMMENT '조회수',
    `co_likes`       INT UNSIGNED     NOT NULL DEFAULT 0                 COMMENT '추천수',
    `co_comments`    INT UNSIGNED     NOT NULL DEFAULT 0                 COMMENT '댓글수 (추후확장)',
    `co_status`      TINYINT UNSIGNED NOT NULL DEFAULT 1                 COMMENT '1:정상 9:삭제',
    `co_ip`          VARCHAR(45)          NULL DEFAULT NULL              COMMENT '작성 IP',
    `co_created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '작성일시',
    `co_updated_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    PRIMARY KEY (`co_idx`),
    KEY `idx_mb_idx`      (`mb_idx`),
    KEY `idx_co_category` (`co_category`),
    KEY `idx_co_source`   (`co_source`, `co_created_at`),
    UNIQUE KEY `uk_co_external_url` (`co_external_url`),
    KEY `idx_co_status`   (`co_status`, `co_created_at`),
    KEY `idx_created_at`  (`co_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='커뮤니티 게시글';
