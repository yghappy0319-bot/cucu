-- 신불자 10% 추가차감: 1시간 간격(크론 1분이어도 1시간에 1회만) 제어용
ALTER TABLE `tb_member`
  ADD COLUMN `credit_debt_at` DATETIME NULL DEFAULT NULL
    COMMENT '신불 추가차감 마지막 시각 (credit=1·마이너스 시)';
