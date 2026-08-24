# 섯다 웹 (독립 모듈)

기존 `info1/info2`, 홀짝, 지갑 코드를 **수정하지 않습니다.**  
공유하는 것: `tb_member` (code·point), `지급로그()`, `config.tax` (금고) 정도만 사용합니다.

## 설치

1. DB 마이그레이션 (1회)

```bash
mysql -u ... -p ... < seotda/schema/tb_seotda.sql
```

2. 접속 URL

| 역할 | URL |
|---|---|
| 플레이 | `/seotda/seotda_web.php?code=회원CODE` |
| 호환 | `/page/seotda.php?code=회원CODE` |

## 확정 플로우

1. **solo** — 나 ↔ 시스템 1:1 · **유저 60% / 시스템 40%** (양쪽 최적 2장 기준 딜)
2. **상대 선택 입장** — 플레이 중 유저 목록에서 **입장** 버튼
3. 호스트 solo 판 진행 중 입장 → **match_pending**
4. solo 판 종료 → **waiting** (양쪽 **준비** 버튼, 90초 타임아웃)
5. 양쪽 준비 → **pvp** 1:1
6. PvP 종료 → **waiting** (게스트 유지) → 양쪽 준비 → **다음 PvP** … **나가기** 전까지 반복
7. **waiting** 중 배팅 변경 → 상대 **수락/거절** (양쪽 게임냥 충분해야 함)
8. **게스트 30초 무선택** → 자동 퇴장 (준비·패 선택)
9. **1:1 준비 후 30초 무응답** → 미준비 쪽 자동 퇴장

## 매칭 방식

- **플레이어 목록** — **섯다 페이지 접속 중** 유저만 표시 → **입장**으로 1:1 신청
- 입장 불가(대결 중) 유저는 버튼 비활성
- 접속자 없으면: 솔로만 가능

접속 판정: `online_at` **12초** 이내 · **status API 적응형 폴링**(상대찾기 0.8초 / 대결·대기 1초)으로 갱신 (게임/입장·updated_at과 무관)

기존 DB: `mysql ... < seotda/schema/alter_online_at.sql` 또는 페이지 접속 시 자동 추가

## API (POST, `code` 필수)

| action | 설명 |
|---|---|
| `status` | 상태 폴링 (+ `players` 목록) |
| `solo_start` | 솔로 시작 (`amount`) |
| `solo_reveal` | 솔로 패 오픈·정산 (`pick1`, `pick2`) |
| `solo_next` | 솔로 done → idle |
| `join_host` | 지정 유저 방 입장 (`host_nick`, `amount`) |
| `ready` | 1:1 준비 |
| `leave` | 퇴장 / solo 복귀 |
| `pvp_reveal` | PvP 오픈·정산 |
| `send_emote` | 1:1 리액션 (`emote=wave` 등) |
| `bet_propose` | 배팅 변경 요청 (`amount`) |
| `bet_accept` | 배팅 변경 수락 |
| `bet_reject` | 배팅 변경 거절 |
| `bet_cancel` | 본인 배팅 변경 요청 취소 |

## 폴더 구조

```
seotda/
  schema/tb_seotda.sql
  lib/          — bootstrap, cards, room, solo, pvp, match
  seotda_web.php
page/seotda.php — 호환 진입점
```

`tb_seotda_queue` 테이블은 초기 스키마에만 포함 (미사용, deprecated)

## MVP 족보

- 1~10월 2장씩 · **땡 > 끗 > 망통**
- **패 3장** 딜 → **2장 선택** 후 족보 대결
- 솔로: 시스템은 3장 중 **최고 조합** 자동 선택
- 최소 배팅 3천만 · 기본 1억

### DB 추가 마이그레이션 (기존 설치)

```bash
mysql -u ... -p ... < seotda/schema/alter_pick3.sql
```

## 2차 (미구현)

- 콜/레이즈, 화투 이미지 UI, 게임제한 연동
- ~~채팅 `.섯다`~~ — **웹 전용, 채팅 연동 없음 (의도적 제외)**
