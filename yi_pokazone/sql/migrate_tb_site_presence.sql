-- 운영 DB 마이그레이션: 사이트 실시간 접속
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_site_presence` (
    `sp_session_id`   VARCHAR(128) NOT NULL COMMENT 'PHP session_id',
    `mb_idx`          INT UNSIGNED     NULL DEFAULT NULL COMMENT '로그인 회원 (비로그인 NULL)',
    `sp_last_seen_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '마지막 활동',
    PRIMARY KEY (`sp_session_id`),
    KEY `idx_last_seen` (`sp_last_seen_at`),
    KEY `idx_mb_idx` (`mb_idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사이트 접속 세션';
