-- 채굴 회원 상태 (tb_member 와 분리)
-- 적용: MySQL/MariaDB, utf8mb4

CREATE TABLE IF NOT EXISTS `tb_member_mining` (
  `nick` VARCHAR(32) NOT NULL COMMENT 'tb_member.name (두 글자 닉 규칙과 동일)',
  `mining_tool` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=숟가락 1=포크',
  `mining_pending` DECIMAL(24,10) NOT NULL DEFAULT 0 COMMENT '채굴 미저장 냥(소수 10자리)',
  `mining_sync_at` DATETIME DEFAULT NULL COMMENT 'pending 마지막 확정 시각',
  `mining_lease_token` VARCHAR(32) DEFAULT NULL COMMENT '채굴 활성 세션 토큰',
  `mining_lease_until` DATETIME DEFAULT NULL COMMENT '채굴 lease 만료',
  `mining_weapon_equipped` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '채굴 무기 장착 여부',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`nick`),
  KEY `idx_lease_until` (`mining_lease_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='채굴 장비·누적·동기화 상태';

-- (선택) 이미 tb_member 에 채굴 컬럼을 추가한 경우 1회 이전
-- INSERT INTO tb_member_mining (nick, mining_tool, mining_pending, mining_sync_at, mining_lease_token, mining_lease_until)
-- SELECT m.name, IFNULL(m.mining_tool,0), IFNULL(m.mining_pending,0), m.mining_sync_at, m.mining_lease_token, m.mining_lease_until
-- FROM tb_member m
-- LEFT JOIN tb_member_mining mm ON mm.nick = m.name
-- WHERE mm.nick IS NULL
--   AND (IFNULL(m.mining_tool,0) > 0 OR IFNULL(m.mining_pending,0) > 0 OR m.mining_sync_at IS NOT NULL OR m.mining_lease_token IS NOT NULL);
