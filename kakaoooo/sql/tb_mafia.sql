-- 마피아 단일 글로벌 게임
CREATE TABLE IF NOT EXISTS `tb_mafia_game` (
  `idx` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `round_no` INT UNSIGNED NOT NULL DEFAULT 0,
  `phase` VARCHAR(16) NOT NULL DEFAULT 'idle',
  `phase_ends_at` DATETIME NULL DEFAULT NULL,
  `winner` VARCHAR(16) NOT NULL DEFAULT '',
  `last_result` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_mafia_player` (
  `idx` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `round_no` INT UNSIGNED NOT NULL DEFAULT 0,
  `nick` VARCHAR(50) NOT NULL,
  `role` VARCHAR(16) NOT NULL DEFAULT 'citizen',
  `alive` TINYINT NOT NULL DEFAULT 1,
  `night_action_nick` VARCHAR(50) NOT NULL DEFAULT '',
  `day_vote_nick` VARCHAR(50) NOT NULL DEFAULT '',
  `investigated` TINYINT NOT NULL DEFAULT 0,
  `investigate_result` VARCHAR(16) NOT NULL DEFAULT '',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_round_nick` (`round_no`, `nick`),
  KEY `idx_round_alive` (`round_no`, `alive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_mafia_log` (
  `idx` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `round_no` INT UNSIGNED NOT NULL DEFAULT 0,
  `msg` VARCHAR(500) NOT NULL,
  `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  KEY `idx_round` (`round_no`, `idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
