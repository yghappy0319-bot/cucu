-- 채굴 광물 (tb_item 과 별도)
-- 적용: MySQL/MariaDB, utf8mb4

CREATE TABLE IF NOT EXISTS `tb_mining_ore_def` (
  `ore_key` VARCHAR(32) NOT NULL COMMENT 'copper|iron|silver|gold|diamond|eunchong_shard',
  `icon` VARCHAR(16) NOT NULL DEFAULT '',
  `label` VARCHAR(32) NOT NULL COMMENT '표시명 (이모지 제외)',
  `sell_newpoint` DECIMAL(24,10) NOT NULL DEFAULT 0 COMMENT '터치 시 mining_pending 가산 (은총조각=0)',
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ore_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='채굴 광물 정의';

INSERT INTO `tb_mining_ore_def` (`ore_key`, `icon`, `label`, `sell_newpoint`, `sort_order`) VALUES
  ('copper',         '🟤', '구리',       0.1,  1),
  ('iron',           '⚙️', '철',         1,    2),
  ('silver',         '🥈', '은',         3,    3),
  ('gold',           '🥇', '금',         5,    4),
  ('diamond',        '💎', '다이아',     10,   5),
  ('eunchong_shard', '✨', '은총조각',   0,    6)
ON DUPLICATE KEY UPDATE
  `icon` = VALUES(`icon`),
  `label` = VALUES(`label`),
  `sell_newpoint` = VALUES(`sell_newpoint`),
  `sort_order` = VALUES(`sort_order`);

-- 대기 중인 발견 (1시간 내 터치)
CREATE TABLE IF NOT EXISTS `tb_mining_ore_find` (
  `idx` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nick` VARCHAR(32) NOT NULL,
  `ore_key` VARCHAR(32) NOT NULL,
  `qty` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `pending_value` DECIMAL(24,10) NOT NULL DEFAULT 0 COMMENT '터치 시 pending 가산',
  `weapon_item` VARCHAR(50) DEFAULT NULL,
  `weapon_enhance` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('pending','claimed','expired') NOT NULL DEFAULT 'pending',
  `found_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expire_at` DATETIME NOT NULL COMMENT 'found_at + 1h',
  `claimed_at` DATETIME DEFAULT NULL,
  `pos_x_pct` DECIMAL(5,2) DEFAULT NULL COMMENT '채굴장면 X 위치 %',
  `pos_y_pct` DECIMAL(5,2) DEFAULT NULL COMMENT '채굴장면 Y 위치 %',
  PRIMARY KEY (`idx`),
  KEY `idx_nick_status_expire` (`nick`, `status`, `expire_at`),
  KEY `idx_expire_status` (`status`, `expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='채굴 광물 발견 (터치 대기)';

-- 발견·수령·소멸 로그
CREATE TABLE IF NOT EXISTS `tb_mining_ore_log` (
  `idx` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nick` VARCHAR(32) NOT NULL,
  `find_idx` BIGINT UNSIGNED DEFAULT NULL,
  `ore_key` VARCHAR(32) NOT NULL,
  `event` ENUM('found','claimed','expired') NOT NULL,
  `qty` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `pending_added` DECIMAL(24,10) NOT NULL DEFAULT 0,
  `weapon_item` VARCHAR(50) DEFAULT NULL,
  `weapon_enhance` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `shard_after` TINYINT UNSIGNED DEFAULT NULL COMMENT '은총조각 터치 후 보유',
  `memo` VARCHAR(255) DEFAULT NULL,
  `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  KEY `idx_nick_regdate` (`nick`, `regdate`),
  KEY `idx_find_idx` (`find_idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='채굴 광물 이벤트 로그';

-- tb_member_mining 확장 (mining_ore.inc.php mining_ore_ensure_member_columns() 가 자동 ALTER)
-- mining_ore_roll_acc          : legacy (크론 전환 후 미사용)
-- mining_ore_roll_targets      : 60분 창 내 남은 spawn offset JSON(초)
-- mining_ore_next_spawn_at     : 크론 spawn 예정 시각 (next_spawn_at <= NOW())
-- mining_ore_window_start      : rolling 60분 창 시작
-- mining_eunchong_shard        : 은총조각 보유 (10개 → 은총 1개)
-- 크론: api/_auto_mining_ore.php (매 1분)
