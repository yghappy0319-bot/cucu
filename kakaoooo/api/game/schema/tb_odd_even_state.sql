-- 홀짝 1:1 도전 게임 상태 (회원 1행)
-- 적용: MySQL/MariaDB, utf8mb4

CREATE TABLE IF NOT EXISTS `tb_odd_even_state` (
  `nick` VARCHAR(32) NOT NULL COMMENT 'tb_member.name (두 글자 닉 규칙과 동일)',
  `streak` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '직전까지의 연승 (0=없음, 1=1연승 … 최대 표시 5)',
  `streak_max_bet` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '이번 연승 구간에서 건 배팅 중 최대액(다음 판 하한)',
  `pending_bet` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '진행 중 판의 배팅액, 0이면 대기 없음',
  `pending_answer` TINYINT UNSIGNED DEFAULT NULL COMMENT '1=홀 2=짝 3=무승부 — 배팅 시 사전 봉인에서 소진',
  `sealed_answer` TINYINT NULL DEFAULT NULL COMMENT '다음 판 사전 봉인 정답(1홀2짝3무)',
  `pending_win_mult` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '승리 시 지급 배수 × 배팅',
  `pending_lose_mult` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '패배 시 차감 배수 × 배팅',
  `pending_at` DATETIME DEFAULT NULL COMMENT '도전 시작 시각 (타임아웃 판정용)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`nick`),
  KEY `idx_pending_at` (`pending_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='홀짝 도전 연승·진행 중 판 상태';

-- 선택: 과거 판 로그 (감사·분쟁 대비)
CREATE TABLE IF NOT EXISTS `tb_odd_even_log` (
  `idx` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nick` VARCHAR(32) NOT NULL,
  `bet` BIGINT UNSIGNED NOT NULL,
  `streak_before` INT UNSIGNED NOT NULL DEFAULT 0,
  `win_mult` SMALLINT UNSIGNED NOT NULL,
  `lose_mult` SMALLINT UNSIGNED NOT NULL,
  `system_pick` TINYINT UNSIGNED NOT NULL COMMENT '1=홀 2=짝 3=무',
  `user_pick` TINYINT UNSIGNED NOT NULL COMMENT '1=홀 2=짝',
  `result` ENUM('win','lose','push') NOT NULL,
  `delta_point` BIGINT NOT NULL COMMENT '실제 포인트 변동 (+승리지급 / -패배차감)',
  `streak_after` INT UNSIGNED NOT NULL DEFAULT 0,
  `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  KEY `idx_nick_regdate` (`nick`, `regdate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='홀짝 도전 결과 로그';
