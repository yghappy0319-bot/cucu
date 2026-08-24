-- 미가입/비규격 닉에게 환영 자동응답을 이미 보냈는지 기록
-- 같은 닉이면 INSERT IGNORE 로 재전송 차단 (PRIMARY KEY = nick)

CREATE TABLE IF NOT EXISTS tb_welcome_sent (
  nick VARCHAR(191) NOT NULL PRIMARY KEY,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4;
