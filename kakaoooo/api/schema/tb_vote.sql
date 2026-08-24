-- 투표 (본방 info1.php — .투표 / .찬성 / .반대 / .투표종료)
-- 진행중(status=1) 투표는 1건만 유지 · title 은 자유 제목(강제출석 등과 무관)

CREATE TABLE IF NOT EXISTS `tb_vote` (
  `idx` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL DEFAULT '' COMMENT '투표 제목 (자유 입력)',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=진행중 0=종료',
  `created_by` varchar(30) NOT NULL DEFAULT '' COMMENT '투표 개설 관리자',
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` datetime DEFAULT NULL COMMENT '종료 시각',
  `ended_by` varchar(30) DEFAULT NULL COMMENT '투표 종료 처리자',
  PRIMARY KEY (`idx`),
  KEY `idx_vote_status` (`status`, `idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tb_vote_ballot` (
  `idx` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `vote_idx` int(11) unsigned NOT NULL COMMENT 'tb_vote.idx',
  `nickname` varchar(30) NOT NULL DEFAULT '' COMMENT '투표자 2글자 닉',
  `choice` tinyint(1) NOT NULL COMMENT '1=찬성 0=반대',
  `regdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uk_vote_ballot_vote_nick` (`vote_idx`, `nickname`),
  KEY `idx_vote_ballot_vote_choice` (`vote_idx`, `choice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
