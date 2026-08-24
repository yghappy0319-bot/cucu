-- =============================================================================
-- 사이트 레이어 팝업
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_popup` (
    `pu_idx`         INT UNSIGNED     NOT NULL AUTO_INCREMENT            COMMENT '팝업 고유번호',
    `pu_title`       VARCHAR(100)     NOT NULL                           COMMENT '제목(관리·접근성)',
    `pu_content`     MEDIUMTEXT           NULL                           COMMENT '본문(HTML 허용 최소화: 텍스트/줄바꿈)',
    `pu_image`       VARCHAR(255)         NULL DEFAULT NULL              COMMENT '이미지 경로',
    `pu_link_url`    VARCHAR(500)         NULL DEFAULT NULL              COMMENT '클릭 시 이동 URL',
    `pu_width`       SMALLINT UNSIGNED NOT NULL DEFAULT 400              COMMENT '팝업 너비(px)',
    `pu_target`      VARCHAR(20)      NOT NULL DEFAULT 'all'            COMMENT 'all:전체 home:메인만',
    `pu_start_at`    DATETIME             NULL DEFAULT NULL              COMMENT '노출 시작(NULL=즉시)',
    `pu_end_at`      DATETIME             NULL DEFAULT NULL              COMMENT '노출 종료(NULL=무기한)',
    `pu_hide_days`   TINYINT UNSIGNED NOT NULL DEFAULT 1                COMMENT '다시보지않기 일수',
    `pu_sort`        INT              NOT NULL DEFAULT 0                 COMMENT '정렬(작을수록 먼저)',
    `pu_status`      TINYINT UNSIGNED NOT NULL DEFAULT 1                 COMMENT '1:노출 9:숨김',
    `pu_created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록일시',
    `pu_updated_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    PRIMARY KEY (`pu_idx`),
    KEY `idx_pu_status_sort` (`pu_status`, `pu_sort`, `pu_idx`),
    KEY `idx_pu_period` (`pu_start_at`, `pu_end_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사이트 레이어 팝업';
