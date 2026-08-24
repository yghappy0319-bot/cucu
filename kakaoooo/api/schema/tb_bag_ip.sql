-- 가방 접속 시 닉네임·IP 별도 수집
CREATE TABLE IF NOT EXISTS `tb_bag_ip` (
  `idx` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nick` VARCHAR(50) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `ua` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  KEY `nick` (`nick`),
  KEY `ip` (`ip`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
