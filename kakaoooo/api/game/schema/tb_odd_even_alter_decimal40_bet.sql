-- 고액 배팅(경·해) 지원: BIGINT UNSIGNED 는 약 1844경까지, PHP int 는 약 922경에서 잘림.
-- tb_member.point 는 이미 DECIMAL(65,0) 인데 홀짝 배팅/로그만 BIGINT 라 4500경 올인이
-- 922경으로 깎여 저장되고, 당첨금 계산이 float 로 변질돼 지급액이 어긋났다.
--
-- 기존 DB에 1회 실행. (PHP 쪽 홀짝_배팅컬럼_확보() 가 자동으로도 시도하지만,
--  ALTER 권한이 없는 환경에서는 이 파일을 직접 실행할 것.)
--
-- 확인: SHOW COLUMNS FROM tb_odd_even_state LIKE 'pending_bet';

ALTER TABLE `tb_odd_even_state`
  MODIFY `streak_max_bet` DECIMAL(40,0) NOT NULL DEFAULT 0
    COMMENT '이번 연승 구간에서 건 배팅 중 최대액(다음 판 하한)',
  MODIFY `pending_bet` DECIMAL(40,0) NOT NULL DEFAULT 0
    COMMENT '진행 중 판의 배팅액, 0이면 대기 없음';

ALTER TABLE `tb_odd_even_log`
  MODIFY `bet` DECIMAL(40,0) NOT NULL DEFAULT 0,
  MODIFY `delta_point` DECIMAL(41,0) NOT NULL DEFAULT 0
    COMMENT '실제 포인트 변동 (+승리지급 / -패배차감)';

-- 페이백 누적도 동일 사유로 승격 (odd_even_payback.inc.php 가 문자열로 다룸)
ALTER TABLE `tb_odd_even_state`
  MODIFY `payback_pool` DECIMAL(40,0) NOT NULL DEFAULT 0
    COMMENT '웹 페이백 누적(게임냥)';
