-- 결제내역(tb_pay_log) 영수증/세금계산서 발행번호
ALTER TABLE `tb_pay_log` ADD COLUMN `approval_number` varchar(64) DEFAULT NULL COMMENT 'Popbill 발행번호(국세청승인번호 또는 문서번호)' AFTER `receipt_status`;
