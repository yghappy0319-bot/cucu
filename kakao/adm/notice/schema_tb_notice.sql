-- 공지사항 관리 (대시보드 /adm/index.php 에서 노출)
-- MySQL 5.7+ / MariaDB 에서 실행

CREATE TABLE IF NOT EXISTS `tb_notice` (
  `idx` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT '제목',
  `content` mediumtext NOT NULL COMMENT '내용',
  `is_pinned` tinyint(4) NOT NULL DEFAULT 0 COMMENT '1 상단고정',
  `is_visible` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1 노출 0 숨김',
  `writer_id` varchar(64) NOT NULL DEFAULT '' COMMENT '작성자 아이디',
  `regdate` datetime DEFAULT NULL COMMENT '등록일',
  `moddate` datetime DEFAULT NULL COMMENT '수정일',
  PRIMARY KEY (`idx`),
  KEY `idx_notice_visible_pinned` (`is_visible`, `is_pinned`, `regdate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='공지사항';

-- 이미 테이블이 있는 경우 (필요한 것만 실행)
-- ALTER TABLE `tb_notice` ADD COLUMN `is_pinned` tinyint(4) NOT NULL DEFAULT 0 COMMENT '1 상단고정' AFTER `content`;
-- ALTER TABLE `tb_notice` ADD COLUMN `is_visible` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1 노출 0 숨김' AFTER `is_pinned`;
-- ALTER TABLE `tb_notice` ADD COLUMN `writer_id` varchar(64) NOT NULL DEFAULT '' COMMENT '작성자 아이디' AFTER `is_visible`;
-- ALTER TABLE `tb_notice` ADD COLUMN `moddate` datetime DEFAULT NULL COMMENT '수정일' AFTER `regdate`;
