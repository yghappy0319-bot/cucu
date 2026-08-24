-- 무기 강화 시도 이력 (채팅 .강화 · 웹 enchant.php 공통)
-- 적용: MySQL/MariaDB, utf8mb4

CREATE TABLE IF NOT EXISTS `tb_enhance_log` (
  `idx` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nick` VARCHAR(32) NOT NULL COMMENT 'tb_member.name',
  `channel` ENUM('chat','web') NOT NULL DEFAULT 'chat' COMMENT '강화 경로',
  `item` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '시도 당시 무기',
  `style` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '시도 당시 스타일',
  `enhance_before` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '시도 전 강화',
  `enhance_after` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '시도 후 강화(파손 시 0)',
  `result` ENUM('success','fail_protect','fail_break') NOT NULL COMMENT '결과',
  `cost` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '차감 냥',
  `dice_roll` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '주사위/확률숫자',
  `dice_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '성공 분자',
  `dice_den` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '성공 분모',
  `rate_pct` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '표시 확률',
  `eunchong` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '은총 버프',
  `challenge_mode` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '+20 도전모드',
  `challenge_target` VARCHAR(32) DEFAULT NULL COMMENT '+20 보유자 닉',
  `challenge_steal` TINYINT(1) DEFAULT NULL COMMENT '탈취성공(도전모드 성공 시만)',
  `suho_used` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '수호 소모 여부',
  `suho_left` SMALLINT UNSIGNED DEFAULT NULL COMMENT '남은 수호',
  `restored` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '무기복구 완료 여부',
  `restored_at` DATETIME DEFAULT NULL COMMENT '무기복구 시각',
  `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  KEY `idx_nick_regdate` (`nick`, `regdate`),
  KEY `idx_regdate` (`regdate`),
  KEY `idx_result` (`result`),
  KEY `idx_nick_break_restore` (`nick`, `result`, `restored`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='무기 강화 시도 이력';
