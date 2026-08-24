-- 커뮤니티 좋아요 / 댓글·대댓글 (기존 DB에 추가 실행)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_community_like` (
    `co_idx`    INT UNSIGNED NOT NULL,
    `mb_idx`    INT UNSIGNED NOT NULL,
    `liked_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`co_idx`, `mb_idx`),
    KEY `idx_mb` (`mb_idx`),
    CONSTRAINT `fk_colk_co` FOREIGN KEY (`co_idx`) REFERENCES `tb_community` (`co_idx`) ON DELETE CASCADE,
    CONSTRAINT `fk_colk_mb` FOREIGN KEY (`mb_idx`) REFERENCES `tb_member` (`mb_idx`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='커뮤니티 글 좋아요';

CREATE TABLE IF NOT EXISTS `tb_community_comment` (
    `cc_idx`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `co_idx`         INT UNSIGNED    NOT NULL,
    `mb_idx`         INT UNSIGNED    NOT NULL,
    `cc_parent_idx`  BIGINT UNSIGNED     NULL DEFAULT NULL COMMENT 'NULL:댓글, 값:대댓글(부모는 최상위 댓글만)',
    `cc_content`     VARCHAR(2000)   NOT NULL,
    `cc_status`      TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1:정상 9:삭제',
    `cc_ip`          VARCHAR(45)         NULL DEFAULT NULL,
    `cc_created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cc_updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`cc_idx`),
    KEY `idx_co_status_created` (`co_idx`, `cc_status`, `cc_created_at`),
    KEY `idx_parent` (`cc_parent_idx`),
    CONSTRAINT `fk_cc_co` FOREIGN KEY (`co_idx`) REFERENCES `tb_community` (`co_idx`) ON DELETE CASCADE,
    CONSTRAINT `fk_cc_mb` FOREIGN KEY (`mb_idx`) REFERENCES `tb_member` (`mb_idx`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='커뮤니티 댓글';

-- (선택) 기존 글의 co_likes / co_comments 실제 건수와 맞추기
-- UPDATE tb_community c
-- SET co_likes = (SELECT COUNT(*) FROM tb_community_like l WHERE l.co_idx = c.co_idx),
--     co_comments = (SELECT COUNT(*) FROM tb_community_comment x WHERE x.co_idx = c.co_idx AND x.cc_status = 1);
