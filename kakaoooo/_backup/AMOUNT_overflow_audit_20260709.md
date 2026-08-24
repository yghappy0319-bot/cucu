# 금액 오버플로 점검·수정 요약 (2026-07-09)

## 백업
- 폴더: `_backup/kakao_open_backup_20260709_222331/`
- tar: `_backup/kakao_open_backup_20260709_222331.tar.gz`
- 복원: 같은 폴더의 `RESTORE.txt` 참고

## 증상
게임냥 총량이 ~922경을 넘으면 `922경3,372조`(PHP_INT_MAX)로 고정 표시됨.

## 수정한 핵심
1. 시세 스냅샷 `DECIMAL(40,0)` + SUM CAST + 문자열
2. 표시 헬퍼: `랭킹_게임냥표시`, `냥_경조_*`, `콤마삽입`, `newpoint표시` — (int)/float 제거
3. `.우리방` / `.환율` — 문자열·bcmath
4. 공통: `냥_정수문자열`, `냥_비율내림`, `냥_나눗셈내림`, `게임냥_안전표시`
5. 표시 경로: wallet / odd_even / mining / stopwatch / seotda fmt
6. 모금 단가·지급로그 BIGINT 문자열 INSERT

## 아직 남은 위험 (실행 로직, 표시보다 우선순위 낮음)
- 홀짝/지갑/섯다: 배팅·잔액 비교에 `(int)$point` 잔존 → 개인 잔액이 922경 넘으면 배팅 한도/차감 이상
- `냥_금액_파싱`: PHP_INT_MAX 초과 입력 → 0 반환
- 아이템 시세 `log10((float)total)` 경로
- 웹 JS `INITIAL_POINT` 등 Number 한계

개인 잔액이 보통 922경 미만이면 표시·시세는 정상. 실행 로직 전면 문자열화는 별도 작업.
