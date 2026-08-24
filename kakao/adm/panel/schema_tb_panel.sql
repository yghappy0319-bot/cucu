-- 럭키패널 관리: 등록 패널 마스터
-- MySQL 5.7+ / MariaDB, DB: bank (또는 실제 DB명) 에서 실행

CREATE TABLE IF NOT EXISTS `tb_panel` (
  `idx` int(11) NOT NULL AUTO_INCREMENT,
  `member_no` int(11) NOT NULL DEFAULT 0 COMMENT 'member.mb_no',
  `panel_name_ko` varchar(64) NOT NULL COMMENT '패널명(한글) 2글자 이상',
  `panel_name_en` varchar(128) NOT NULL COMMENT '패널명(영문) 6자 이상, A-Za-z',
  `contact_phone` varchar(32) NOT NULL COMMENT '담당자 연락처',
  `panel_url` varchar(512) NOT NULL DEFAULT '' COMMENT '미사용(레거시)',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1 정상 0 중지 2 삭제완료',
  `regdate` datetime DEFAULT NULL,
  `enddate` datetime DEFAULT NULL COMMENT '이용 종료일(등록 시점 + 31일)',
  `moddate` datetime DEFAULT NULL,
  PRIMARY KEY (`idx`),
  KEY `idx_member_no` (`member_no`),
  UNIQUE KEY `uq_member_panel_en` (`member_no`,`panel_name_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='관리자 패널 등록';

-- 이미 테이블을 만든 경우 (필요한 것만 실행)
ALTER TABLE `tb_panel` ADD COLUMN `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1 정상 0 중지' AFTER `panel_url`;
ALTER TABLE `tb_panel` ADD COLUMN `enddate` datetime DEFAULT NULL COMMENT '이용 종료일(등록 시점 + 31일)' AFTER `regdate`;
ALTER TABLE `tb_panel` ADD COLUMN `db_delete` tinyint(4) NOT NULL DEFAULT 0 COMMENT '1 삭제(소프트)' AFTER `moddate`;
ALTER TABLE `tb_panel` ADD COLUMN `delete_time` datetime DEFAULT NULL COMMENT '삭제 예정 시각(삭제 버튼 시점 +3일)' AFTER `db_delete`;
ALTER TABLE `tb_panel` ADD COLUMN `test_panel_url` varchar(512) NOT NULL DEFAULT '' COMMENT '임시 도메인 URL(DNS 생성 후)' AFTER `panel_url`;
ALTER TABLE `tb_panel` ADD COLUMN `admin_id` varchar(64) NOT NULL DEFAULT '' COMMENT '패널 관리자 아이디' AFTER `test_panel_url`;
ALTER TABLE `tb_panel` ADD COLUMN `admin_pw` varchar(255) NOT NULL DEFAULT '' COMMENT '패널 관리자 비밀번호(password_hash)' AFTER `admin_id`;
ALTER TABLE `tb_panel` ADD COLUMN `admin_pw_plain` varchar(128) NOT NULL DEFAULT '' COMMENT '패널 관리자 비밀번호(평문, 관리용)' AFTER `admin_pw`;
-- 이미 admin_pw 컬럼이 있는 경우
-- ALTER TABLE `tb_panel` MODIFY COLUMN `admin_pw` varchar(255) NOT NULL DEFAULT '' COMMENT '패널 관리자 비밀번호(password_hash)';
-- ALTER TABLE `tb_panel` ADD COLUMN `admin_pw_plain` varchar(128) NOT NULL DEFAULT '' COMMENT '패널 관리자 비밀번호(평문, 관리용)' AFTER `admin_pw`;
ALTER TABLE `tb_panel` ADD COLUMN `ftp_id` varchar(64) NOT NULL DEFAULT '' COMMENT 'FTP 아이디' AFTER `admin_pw`;
ALTER TABLE `tb_panel` ADD COLUMN `ftp_pw` varchar(128) NOT NULL DEFAULT '' COMMENT 'FTP 비밀번호' AFTER `ftp_id`;
ALTER TABLE `tb_panel` ADD COLUMN `db_name` varchar(64) NOT NULL DEFAULT '' COMMENT 'DB명' AFTER `ftp_pw`;
ALTER TABLE `tb_panel` ADD COLUMN `db_id` varchar(64) NOT NULL DEFAULT '' COMMENT 'DB 아이디' AFTER `db_name`;
ALTER TABLE `tb_panel` ADD COLUMN `db_pw` varchar(128) NOT NULL DEFAULT '' COMMENT 'DB 비밀번호' AFTER `db_id`;
-- UPDATE `tb_panel` SET `enddate` = DATE_ADD(`regdate`, INTERVAL 31 DAY) WHERE `enddate` IS NULL AND `regdate` IS NOT NULL;
