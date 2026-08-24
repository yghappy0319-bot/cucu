-- 스톱워치 그룹 라운드 (시스템이 목표 시간 선정 · 친구들이 동일 목표에 도전)
CREATE TABLE IF NOT EXISTS `tb_stopwatch_round` (
  `idx` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `target_ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `entry_fee` BIGINT UNSIGNED NOT NULL DEFAULT 100000000000,
  `pot` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `player_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `winner_nick` VARCHAR(32) DEFAULT NULL,
  `opened_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ends_at` DATETIME NOT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`idx`),
  KEY `idx_status_ends` (`status`, `ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 라운드별 참가 기록 (닉당 라운드 1회)
CREATE TABLE IF NOT EXISTS `tb_stopwatch_entry` (
  `idx` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `round_id` BIGINT UNSIGNED NOT NULL,
  `nick` VARCHAR(32) NOT NULL,
  `bet` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `started_at` DOUBLE NOT NULL DEFAULT 0,
  `token` VARCHAR(64) NOT NULL DEFAULT '',
  `status` ENUM('playing','done','timeout') NOT NULL DEFAULT 'playing',
  `elapsed_ms` INT UNSIGNED DEFAULT NULL,
  `diff_ms` INT UNSIGNED DEFAULT NULL,
  `finished_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uk_round_nick` (`round_id`, `nick`),
  KEY `idx_round_rank` (`round_id`, `status`, `diff_ms`, `finished_at`),
  KEY `idx_nick_status` (`nick`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 스톱워치 게임 로그 (라운드 정산·개인 기록)
CREATE TABLE IF NOT EXISTS `tb_stopwatch_log` (
  `idx` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `round_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `nick` VARCHAR(32) NOT NULL,
  `bet` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `target_ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `elapsed_ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `diff_ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `result` VARCHAR(16) NOT NULL DEFAULT '',
  `delta_point` BIGINT NOT NULL DEFAULT 0,
  `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  KEY `idx_round` (`round_id`),
  KEY `idx_nick_reg` (`nick`, `regdate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
