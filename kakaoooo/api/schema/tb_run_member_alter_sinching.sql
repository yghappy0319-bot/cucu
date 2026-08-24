-- 야바위: .마감 시 모든 참가 행에 동일하게 저장되는 주사위 입력 마감 시각(마감 시점 + 30분).
-- .점수 호출 시 신청일시 <= NOW() 인 미입력자를 3점·3회 마감 처리한다.
ALTER TABLE tb_run_member ADD COLUMN 신청일시 DATETIME NULL DEFAULT NULL COMMENT '야바위 주사위 입력 마감 시각(.마감+30분)';
