-- Pokazone - 크론·웹훅 작업 실행 로그
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_cron_job_log` (
    `cjl_idx`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cjl_job`            VARCHAR(60)     NOT NULL DEFAULT '' COMMENT '작업 식별자',
    `cjl_ok`             TINYINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '1:성공 0:실패',
    `cjl_trigger`        VARCHAR(10)     NOT NULL DEFAULT 'cli' COMMENT 'cli|web|post',
    `cjl_message`        VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '요약 메시지',
    `cjl_count_processed` INT UNSIGNED   NOT NULL DEFAULT 0  COMMENT '처리 대상 건수',
    `cjl_count_ok`       INT UNSIGNED   NOT NULL DEFAULT 0  COMMENT '성공 건수',
    `cjl_count_fail`     INT UNSIGNED   NOT NULL DEFAULT 0  COMMENT '실패 건수',
    `cjl_ref_id`         INT UNSIGNED        NULL DEFAULT NULL COMMENT 'pay_idx 등 참조',
    `cjl_duration_ms`    INT UNSIGNED   NOT NULL DEFAULT 0  COMMENT '실행 시간(ms)',
    `cjl_detail`         VARCHAR(1000)   NOT NULL DEFAULT '' COMMENT '부가 정보(JSON)',
    `cjl_created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`cjl_idx`),
    KEY `idx_job_created` (`cjl_job`, `cjl_created_at`),
    KEY `idx_ok_created` (`cjl_ok`, `cjl_created_at`),
    KEY `idx_created` (`cjl_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='크론·웹훅 실행 로그';
