-- 결제 요청 채팅 메시지별 입금 상태 (요청 msg_idx 단위)
-- 0:결제대기 1:입금확인중 2:입금완료 3:결제요청취소
SET NAMES utf8mb4;

SET @has_msg_pay_status := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_room_msg'
      AND COLUMN_NAME = 'msg_pay_status'
);
SET @sql_msg_pay_status := IF(
    @has_msg_pay_status = 0,
    'ALTER TABLE `tb_trade_room_msg` ADD COLUMN `msg_pay_status` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT ''결제요청 메시지 입금상태 0~3'' AFTER `msg_pay_idx`',
    'SELECT 1'
);
PREPARE stmt_msg_pay_status FROM @sql_msg_pay_status;
EXECUTE stmt_msg_pay_status;
DEALLOCATE PREPARE stmt_msg_pay_status;

-- tb_trade_payment.request_msg_idx 와 동기화 (기존 데이터)
UPDATE tb_trade_room_msg m
INNER JOIN tb_trade_payment p ON p.request_msg_idx = m.msg_idx
SET m.msg_pay_status = LEAST(p.pay_status, 3)
WHERE m.msg_body LIKE '[PZ_PAY:%'
  AND m.msg_body NOT LIKE '[PZ_PAY_CANCEL:%'
  AND m.msg_body NOT LIKE '[PZ_PAY_ACK:%'
  AND m.msg_body NOT LIKE '[PZ_PAY_DONE:%'
  AND (m.msg_pay_status IS NULL OR m.msg_pay_status <> LEAST(p.pay_status, 3));
