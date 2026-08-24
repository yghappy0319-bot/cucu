<?php
/**
 * 간단 부루마블 — 전원 공유 보드
 * 턴: 민호 → 가입 오래된 순(regdate ASC) · 한 명씩 주사위
 * 칸 48 · 나라 36 · 땅값은 겜냥 스냅샷(시세기준_게임냥) 비율 · 현금은 부루마블 머니(burumable_money)
 */

if (!defined('부루마블_지급_퍼센트')) {
  /** 새 판 시작 시 전원 지급 · 땅값 기준도 같은 비율 (스냅샷 게임냥의 10%) */
  define('부루마블_지급_퍼센트', 0.10);
}
if (!defined('부루마블_시작_퍼센트_최소')) {
  define('부루마블_시작_퍼센트_최소', 부루마블_지급_퍼센트);
  define('부루마블_시작_퍼센트_최대', 부루마블_지급_퍼센트);
}
if (!defined('부루마블_시작_퍼센트')) {
  define('부루마블_시작_퍼센트', 부루마블_지급_퍼센트);
}
if (!defined('부루마블_월급_시작비')) {
  define('부루마블_월급_시작비', 15);
  define('부루마블_최대턴', 200);
  define('부루마블_최소시작금', '2000000');
  define('부루마블_턴제한초', 60);
  define('부루마블_턴최소초', 10);
  define('부루마블_턴감초', 10);
  define('부루마블_구매제한초', 20);
}
if (!defined('부루마블_건설제한초')) {
  /** 내 땅에 도착해 펜션·호텔·랜드마크를 고르는 시간(초) */
  define('부루마블_건설제한초', 30);
}
if (!defined('부루마블_찬스제한초')) {
  /** 찬스 칸에서 카드 고르는 시간(초) · 만료 시 나온 카드 중 무작위 */
  define('부루마블_찬스제한초', 30);
  define('부루마블_찬스카드수', 3);
}
if (!defined('부루마블_여행제한초')) {
  /** 세계여행: 이동할 도시를 고르는 시간(초) · 만료 시 무작위 도시 */
  define('부루마블_여행제한초', 40);
}
if (!defined('부루마블_찬스통행할인_횟수')) {
  /** 찬스: 통행료 50% 할인 횟수 */
  define('부루마블_찬스통행할인_횟수', 3);
  define('부루마블_찬스통행할인_퍼센트', 50);
}
if (!defined('부루마블_이자_퍼센트')) {
  /** 한 바퀴(턴 +1)마다 땅값 기준 이자(%). 펜션·호텔·랜드마크는 아래 추가분(겹치지 않음) */
  define('부루마블_이자_퍼센트', '0.5');
  define('부루마블_이자_펜션_추가', '0.1');
  define('부루마블_이자_호텔_추가', '0.4');
  define('부루마블_이자_랜드마크_추가', '1.0');
}
if (!defined('부루마블_양도수수료_퍼센트')) {
  /** 땅 양도: 땅값 10% 수수료(기존 금고 5%·로또 5%를 소멸) · 양도 후 땅값·통행료 20% 하락 · 한 바퀴마다 원래 땅값의 5%씩 회복(4턴 원상) */
  define('부루마블_양도수수료_퍼센트', 10);
  define('부루마블_양도금고_퍼센트', 5);
  define('부루마블_양도로또_퍼센트', 5);
  define('부루마블_양도땅값삭감_퍼센트', 20);
  define('부루마블_양도땅값복구_퍼센트', 5);
}
if (!defined('부루마블_이름')) {
  define('부루마블_이름', '부루마블');
}
if (!defined('부루마블_페이지주소')) {
  define('부루마블_페이지주소', 'http://49.247.160.164/page/burumable.php');
}
if (!defined('부루마블_연구실주소')) {
  define('부루마블_연구실주소', 'https://open.kakao.com/o/gQIh7cIi');
}
if (!defined('부루마블_머니이름')) {
  define('부루마블_머니이름', '부루마블 머니');
}
if (!defined('부루마블_기권환급_퍼센트')) {
  /** 종료 정산 시 보유 땅값+건물비의 이 비율(%)을 부루마블 머니로 환급. 이번 판 포기는 매각(90%)을 씀 */
  define('부루마블_기권환급_퍼센트', 50);
}
if (!defined('부루마블_1등아이템가격')) {
  /** 우승 아이템 표시용 폴백 · 실제 거래가는 본방냥 시총 10% */
  define('부루마블_1등아이템가격', '0');
}
if (!defined('부루마블_등수조각_1')) {
  /** 결승 1~5등 은총·은총조각 · 땅 매도%(게임냥). 다음 종료 시상부터 */
  define('부루마블_등수은총_1', 5);
  define('부루마블_등수은총_2', 2);
  define('부루마블_등수은총_3', 1);
  define('부루마블_등수은총_4', 0);
  define('부루마블_등수은총_5', 0);
  define('부루마블_등수조각_1', 30);
  define('부루마블_등수조각_2', 20);
  define('부루마블_등수조각_3', 10);
  define('부루마블_등수조각_4', 10);
  define('부루마블_등수조각_5', 5);
  define('부루마블_등수매도_1', 100);
  define('부루마블_등수매도_2', 50);
  define('부루마블_등수매도_3', 10);
  define('부루마블_등수매도_4', 0);
  define('부루마블_등수매도_5', 0);
}
if (!defined('부루마블_시상인원_소')) {
  /** 참가 인원별 시상. 5~9명 1~3등 · 10명↑ 1~5등 */
  define('부루마블_시상인원_소', 5);
  define('부루마블_시상인원_대', 10);
  define('부루마블_등수소은총_1', 3);
  define('부루마블_등수소은총_2', 2);
  define('부루마블_등수소은총_3', 1);
  define('부루마블_등수소조각_1', 20);
  define('부루마블_등수소조각_2', 10);
  define('부루마블_등수소조각_3', 0);
}
if (!defined('부루마블_매각수수료_퍼센트')) {
  /** 땅 매각: 땅값+건물비의 10% 수수료(기존 금고 5%·로또 5%를 소멸) · 나머지 90% 부루마블 머니 환급 · 빈땅 */
  define('부루마블_매각수수료_퍼센트', 10);
  define('부루마블_매각금고_퍼센트', 5);
  define('부루마블_매각로또_퍼센트', 5);
}
if (!defined('부루마블_후발유예바퀴')) {
  /** 중도 합류 후 출발을 이 횟수만큼 지날 때까지 통행료 할인 */
  define('부루마블_후발유예바퀴', 1);
  define('부루마블_후발통행료_퍼센트', 50);
}
if (!defined('부루마블_인수_퍼센트')) {
  /** 남의 땅 도착 시 인수·강제매수가 = (땅값+건물비) × 이 비율(웃돈) */
  define('부루마블_인수_퍼센트', 150);
  define('부루마블_인수제한초', 20);
}
if (!defined('부루마블_인수거절_한도')) {
  /** 땅마다 주인이 인수 제안을 거절할 수 있는 횟수. 넘으면 다음 도착자는 강제매수 */
  define('부루마블_인수거절_한도', 1);
}
if (!defined('부루마블_세금_퍼센트')) {
  /** 세금 칸: 보유 땅값+건물비의 이 비율(%). 땅이 없으면 면제 */
  define('부루마블_세금_퍼센트', 10);
}
if (!defined('부루마블_미굴림_파산회')) {
  /** 주사위 미굴림 이 횟수에 강제 파산 */
  define('부루마블_미굴림_파산회', 5);
}
if (!defined('부루마블_파산제한초')) {
  /** 통행료·세금 부족 시 땅 매도/파산 고르는 시간(초) · 만료 시 전체 파산 */
  define('부루마블_파산제한초', 30);
}
if (!defined('부루마블_모집초')) {
  /** 민호 모집시작 후 참가 신청을 받는 시간(초) */
  define('부루마블_모집초', 1800);
}
if (!defined('부루마블_모집연장초')) {
  /** 민호 모집연장 1회당 늘어나는 시간(초) */
  define('부루마블_모집연장초', 600);
}
if (!defined('부루마블_모집알람초')) {
  /** 모집 알람 재전송 최소 간격(초) · 친구 전원 가능 */
  define('부루마블_모집알람초', 60);
}
if (!defined('부루마블_참가비_퍼센트')) {
  /** 참가비 = 스냅샷 게임냥 시총의 이 비율. 취소 불가 */
  define('부루마블_참가비_퍼센트', 0.01);
}

if (!function_exists('burumable_테이블보장')) {
  function burumable_테이블보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    @db_query("CREATE TABLE IF NOT EXISTS `tb_game_burumable_world` (
      `idx` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
      `state` MEDIUMTEXT NOT NULL,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    burumable_머니컬럼보장();
  }
}

if (!function_exists('burumable_머니컬럼보장')) {
  /** 회원별 부루마블 머니. 잔액은 새 판마다 스냅샷의 10%씩 전원 동일 지급 */
  function burumable_머니컬럼보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = @db_select("SHOW COLUMNS FROM tb_member LIKE 'burumable_money'");
    if (!empty($col)) {
      return;
    }
    @db_query("ALTER TABLE tb_member ADD COLUMN `burumable_money` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '부루마블 머니'");
  }
}

if (!function_exists('burumable_보드')) {
  /** @return list<array<string,mixed>> */
  function burumable_보드(): array {
    $s = static function (int $id, string $name, int $pw, int $rw, string $group, string $color): array {
      return [
        'id' => $id,
        'name' => $name,
        'type' => 'land',
        'pw' => $pw,
        'rw' => $rw,
        'price' => '0',
        'rent' => '0',
        'group' => $group,
        'color' => $color,
      ];
    };
    $sp = static function (int $id, string $name, string $type, string $color): array {
      return ['id' => $id, 'name' => $name, 'type' => $type, 'pw' => 0, 'rw' => 0, 'price' => '0', 'rent' => '0', 'group' => '', 'color' => $color];
    };
    return [
      $sp(0, '출발', 'go', '#6ee7b7'),
      $s(1, '타이베이', 250, 120, 'a', '#92400e'),
      $s(2, '마닐라', 300, 133, 'a', '#92400e'),
      $sp(3, '찬스', 'chance', '#fbbf24'),
      $s(4, '베이징', 400, 125, 'a', '#92400e'),
      $s(5, '방콕', 450, 133, 'b', '#38bdf8'),
      $s(6, '카이로', 500, 140, 'b', '#38bdf8'),
      $sp(7, '세금', 'tax', '#c4b5fd'),
      $s(8, '두바이', 780, 150, 'b', '#38bdf8'),
      $s(9, '아테네', 650, 138, 'c', '#f472b6'),
      $s(10, '로마', 920, 143, 'c', '#f472b6'),
      $s(11, '독도', 800, 150, 'c', '#f472b6'),
      $sp(12, '무인도', 'jail', '#94a3b8'),
      $s(13, '코펜하겐', 850, 141, 'd', '#fb923c'),
      $s(14, '베를린', 1100, 156, 'd', '#fb923c'),
      $sp(15, '찬스', 'chance', '#fbbf24'),
      $s(16, '취리히', 1180, 160, 'd', '#fb923c'),
      $s(17, '빈', 1050, 162, 'e', '#c084fc'),
      $s(18, '프라하', 1100, 164, 'e', '#c084fc'),
      $s(19, '부다페스트', 1200, 167, 'e', '#c084fc'),
      $s(20, '런던', 1650, 169, 'f', '#ef4444'),
      $s(21, '파리', 1700, 171, 'f', '#ef4444'),
      $sp(22, '찬스', 'chance', '#fbbf24'),
      $s(23, '암스테르담', 1600, 173, 'f', '#ef4444'),
      $sp(24, '주차장', 'park', '#64748b'),
      $s(25, '시드니', 1600, 175, 'g', '#facc15'),
      $s(26, '멜버른', 1700, 176, 'g', '#facc15'),
      $sp(27, '세금', 'tax', '#c4b5fd'),
      $s(28, '오클랜드', 1800, 178, 'g', '#facc15'),
      $s(29, '싱가포르', 2000, 184, 'h', '#2dd4bf'),
      $s(30, '홍콩', 2100, 189, 'h', '#2dd4bf'),
      $s(31, '상하이', 2150, 190, 'h', '#2dd4bf'),
      $s(32, '도쿄', 2300, 195, 'i', '#a3e635'),
      $s(33, '오사카', 2100, 200, 'i', '#a3e635'),
      $sp(34, '찬스', 'chance', '#fbbf24'),
      $s(35, '교토', 2200, 200, 'i', '#a3e635'),
      $sp(36, '무인도행', 'gotojail', '#94a3b8'),
      $s(37, '밴쿠버', 2250, 204, 'j', '#60a5fa'),
      $s(38, '토론토', 2300, 209, 'j', '#60a5fa'),
      $s(39, '뉴욕', 2650, 208, 'j', '#60a5fa'),
      $s(40, '하와이', 2450, 220, 'k', '#e879f9'),
      $s(41, '엘에이', 2700, 224, 'k', '#e879f9'),
      $sp(42, '찬스', 'chance', '#fbbf24'),
      $s(43, '마이애미', 2600, 231, 'k', '#e879f9'),
      $s(44, '부산', 2700, 241, 'l', '#818cf8'),
      $s(45, '제주', 2800, 268, 'l', '#818cf8'),
      $sp(46, '세금', 'tax', '#c4b5fd'),
      $s(47, '서울', 3000, 300, 'l', '#818cf8'),
    ];
  }
}

if (!function_exists('burumable_국기')) {
  /** 나라 한글명 → ISO 3166-1 alpha-2 (윈도우에서 국기 이모지가 글자로 깨져서 이미지로 표시) */
  function burumable_국기(string $name): string {
    static $map = [
      '타이베이' => 'tw',
      '마닐라' => 'ph',
      '베이징' => 'cn',
      '방콕' => 'th',
      '카이로' => 'eg',
      '두바이' => 'ae',
      '아테네' => 'gr',
      '로마' => 'it',
      '독도' => 'kr',
      '코펜하겐' => 'dk',
      '베를린' => 'de',
      '취리히' => 'ch',
      '빈' => 'at',
      '프라하' => 'cz',
      '부다페스트' => 'hu',
      '런던' => 'gb',
      '파리' => 'fr',
      '암스테르담' => 'nl',
      '시드니' => 'au',
      '멜버른' => 'au',
      '오클랜드' => 'nz',
      '싱가포르' => 'sg',
      '홍콩' => 'hk',
      '상하이' => 'cn',
      '도쿄' => 'jp',
      '오사카' => 'jp',
      '교토' => 'jp',
      '밴쿠버' => 'ca',
      '토론토' => 'ca',
      '뉴욕' => 'us',
      '하와이' => 'us',
      '엘에이' => 'us',
      '마이애미' => 'us',
      '부산' => 'kr',
      '제주' => 'kr',
      '서울' => 'kr',
    ];
    return $map[$name] ?? '';
  }
}

if (!function_exists('burumable_칸수')) {
  function burumable_칸수(): int {
    return count(burumable_보드());
  }
}

if (!function_exists('burumable_격자')) {
  function burumable_격자(): int {
    return intdiv(burumable_칸수(), 4) + 1;
  }
}

if (!function_exists('burumable_특수칸')) {
  function burumable_특수칸(string $type): int {
    foreach (burumable_보드() as $t) {
      if (($t['type'] ?? '') === $type) {
        return (int)$t['id'];
      }
    }
    return 0;
  }
}

if (!function_exists('burumable_칸좌표')) {
  function burumable_칸좌표(int $id): array {
    $n = burumable_칸수();
    $side = intdiv($n, 4);
    $id = (($id % $n) + $n) % $n;
    if ($id <= $side) {
      return [$id, $side];
    }
    if ($id < 2 * $side) {
      return [$side, $side - ($id - $side)];
    }
    if ($id <= 3 * $side) {
      return [$side - ($id - 2 * $side), 0];
    }
    return [0, $id - 3 * $side];
  }
}

if (!function_exists('burumable_냥')) {
  function burumable_냥($n): string {
    if (function_exists('냥_정수문자열')) {
      $s = 냥_정수문자열($n);
    } else {
      $s = preg_replace('/[^\d]/', '', (string)$n) ?: '0';
      $s = ltrim($s, '0');
      $s = ($s === '' ? '0' : $s);
    }
    return ($s === '' || $s === '-') ? '0' : $s;
  }
}

if (!function_exists('burumable_비교')) {
  function burumable_비교($a, $b): int {
    $a = burumable_냥($a);
    $b = burumable_냥($b);
    if (function_exists('bccomp')) {
      return (int)bccomp($a, $b, 0);
    }
    if (strlen($a) !== strlen($b)) {
      return strlen($a) < strlen($b) ? -1 : 1;
    }
    return $a === $b ? 0 : ($a < $b ? -1 : 1);
  }
}

if (!function_exists('burumable_더하기')) {
  function burumable_더하기($a, $b): string {
    $a = burumable_냥($a);
    $b = burumable_냥($b);
    if (function_exists('bcadd')) {
      return burumable_냥(bcadd($a, $b, 0));
    }
    return burumable_냥((string)((int)$a + (int)$b));
  }
}

if (!function_exists('burumable_빼기')) {
  function burumable_빼기($a, $b): string {
    $a = burumable_냥($a);
    $b = burumable_냥($b);
    if (function_exists('bcsub') && function_exists('bccomp')) {
      $r = bcsub($a, $b, 0);
      return bccomp($r, '0', 0) < 0 ? '0' : burumable_냥($r);
    }
    $n = (int)$a - (int)$b;
    return burumable_냥((string)max(0, $n));
  }
}

if (!function_exists('burumable_곱')) {
  function burumable_곱($a, int $n): string {
    if ($n <= 0) {
      return '0';
    }
    $a = burumable_냥($a);
    if (function_exists('bcmul')) {
      return burumable_냥(bcmul($a, (string)$n, 0));
    }
    return burumable_냥((string)((int)$a * $n));
  }
}

if (!function_exists('burumable_몫')) {
  /** floor(a * num / den) */
  function burumable_몫($a, int $num, int $den): string {
    if ($num < 1 || $den < 1) {
      return '0';
    }
    $a = burumable_냥($a);
    if (function_exists('bcmul') && function_exists('bcdiv')) {
      return burumable_냥(bcdiv(bcmul($a, (string)$num, 0), (string)$den, 0));
    }
    return burumable_냥((string)(int)floor(((int)$a * $num) / $den));
  }
}

if (!function_exists('burumable_퍼센트금액')) {
  /** floor(snap * percent) — percent는 0.40=40% (냥_비율내림과 동일). 최소 1이 아니라 0 */
  function burumable_퍼센트금액($snap, float $percent): string {
    $snap = burumable_냥($snap);
    if ($snap === '0' || $percent <= 0) {
      return '0';
    }
    if (function_exists('냥_비율내림')) {
      $amt = 냥_정수문자열(냥_비율내림($snap, $percent));
      return ($amt === '' || $amt === '-') ? '0' : $amt;
    }
    $pctInt = (int)round($percent * 100);
    if ($pctInt < 1) {
      $pctInt = 1;
    }
    if (function_exists('bcmul') && function_exists('bcdiv')) {
      return burumable_냥(bcdiv(bcmul($snap, (string)$pctInt, 0), '100', 0));
    }
    if (strlen($snap) <= 15) {
      return burumable_냥((string)(int)floor(((float)$snap * $pctInt) / 100.0));
    }
    return '0';
  }
}

if (!function_exists('burumable_스냅샷냥')) {
  function burumable_스냅샷냥(): string {
    $s = '0';
    if (function_exists('시세기준_스냅샷_로드')) {
      $row = 시세기준_스냅샷_로드();
      $s = burumable_냥($row['게임냥'] ?? 0);
    }
    if ($s === '0' && function_exists('강화비용_전체게임냥')) {
      $s = burumable_냥(강화비용_전체게임냥());
    }
    if ($s === '0' && function_exists('시세기준_게임냥_문자열')) {
      $s = burumable_냥(시세기준_게임냥_문자열());
    }
    return $s;
  }
}

if (!function_exists('burumable_도시할증')) {
  /** 주요 도시 땅값 할증(%). 100=기본(스냅샷 10% 기준). 서울·뉴욕 등은 시작금보다 비싸게 */
  function burumable_도시할증(string $name, string $group = ''): int {
    static $byName = [
      '서울' => 180,
      '제주' => 160,
      '부산' => 155,
      '뉴욕' => 200,
      '도쿄' => 185,
      '런던' => 195,
      '파리' => 195,
      '홍콩' => 180,
      '싱가포르' => 180,
      '상하이' => 160,
      '엘에이' => 180,
      '하와이' => 155,
      '마이애미' => 150,
      '로마' => 170,
      '두바이' => 175,
      '암스테르담' => 150,
      '베를린' => 145,
      '취리히' => 150,
    ];
    if (isset($byName[$name])) {
      return $byName[$name];
    }
    static $byGroup = [
      'f' => 115,
      'g' => 110,
      'h' => 125,
      'i' => 125,
      'j' => 135,
      'k' => 125,
      'l' => 145,
    ];
    return $byGroup[$group] ?? 100;
  }
}

if (!function_exists('burumable_시작퍼센트뽑기')) {
  /** 땅값·지급 공통 비율. 지금은 스냅샷 10% */
  function burumable_시작퍼센트뽑기(): float {
    $pct = defined('부루마블_지급_퍼센트') ? (float)부루마블_지급_퍼센트 : 0.10;
    if ($pct <= 0) {
      $pct = 0.10;
    }
    return $pct;
  }
}

if (!function_exists('burumable_퍼센트표시')) {
  function burumable_퍼센트표시(float $pct): string {
    return rtrim(rtrim(number_format($pct * 100, 2, '.', ''), '0'), '.') . '%';
  }
}

if (!function_exists('burumable_시세퍼센트읽기')) {
  /** 이 판에 고정된 비율. 없으면 시작/스냅샷으로 추정, 그래도 없으면 지급 비율 */
  function burumable_시세퍼센트읽기(?array $q): float {
    $fallback = defined('부루마블_지급_퍼센트') ? (float)부루마블_지급_퍼센트 : 0.10;
    if (!is_array($q)) {
      return $fallback;
    }
    if (isset($q['pct']) && (float)$q['pct'] > 0) {
      return (float)$q['pct'];
    }
    $snap = burumable_냥($q['snap'] ?? 0);
    $start = burumable_냥($q['start'] ?? 0);
    if ($snap !== '0' && $start !== '0' && function_exists('bcdiv')) {
      $r = (float)bcdiv($start, $snap, 4);
      if ($r > 0) {
        return $r;
      }
    }
    return $fallback;
  }
}

if (!function_exists('burumable_시세표')) {
  /**
   * @param float|int|string|null $pct 이 판에 고정할 땅값·지급 비율. null이면 스냅샷 10%
   * @return array{snap:string,start:string,pct:float,grant:string,grant_pct:float,salary:string,bank:string,hospital:string,tax_min:string,tax_max:string,lands:array<string,array{price:string,rent:string}>}
   */
  function burumable_시세표(?string $snap = null, $pct = null): array {
    $snap = $snap === null ? burumable_스냅샷냥() : burumable_냥($snap);
    $grantPct = defined('부루마블_지급_퍼센트') ? (float)부루마블_지급_퍼센트 : 0.10;
    if ($grantPct <= 0) {
      $grantPct = 0.10;
    }
    if ($pct === null || $pct === '' || (float)$pct <= 0) {
      $pct = $grantPct;
    } else {
      $pct = (float)$pct;
    }
    $start = $snap === '0' ? (string)부루마블_최소시작금 : burumable_퍼센트금액($snap, $pct);
    if ($start === '0' || burumable_비교($start, 부루마블_최소시작금) < 0) {
      $start = (string)부루마블_최소시작금;
    }
    $grant = $start;
    $salary = burumable_몫($grant, (int)부루마블_월급_시작비, 100);
    if ($salary === '0') {
      $salary = burumable_몫($grant, 15, 100);
    }
    $lands = [];
    foreach (burumable_보드() as $t) {
      if (($t['type'] ?? '') !== 'land') {
        continue;
      }
      $pw = (int)($t['pw'] ?? 0);
      $rw = (int)($t['rw'] ?? 0);
      $price = burumable_몫($start, $pw, 10000);
      if ($price === '0') {
        $price = burumable_몫($start, max(1, $pw), 10000);
        if ($price === '0') {
          $price = '10000';
        }
      }
      $prem = function_exists('burumable_도시할증')
        ? burumable_도시할증((string)($t['name'] ?? ''), (string)($t['group'] ?? ''))
        : 100;
      if ($prem > 100) {
        $price = burumable_몫($price, $prem, 100);
      }
      $rent = burumable_몫($price, $rw, 1000);
      if ($rent === '0') {
        $rent = burumable_몫($price, max(1, $rw), 1000);
        if ($rent === '0') {
          $rent = burumable_몫($price, 1, 10);
        }
      }
      $lands[(string)$t['id']] = ['price' => $price, 'rent' => $rent];
    }
    return [
      'snap' => $snap,
      'start' => $start,
      'pct' => $pct,
      'grant' => $grant,
      'grant_pct' => $pct,
      'city_prem' => 2,
      'salary' => $salary,
      'bank' => burumable_몫($start, 5, 100),
      'hospital' => burumable_몫($start, 4, 100),
      'tax_min' => burumable_몫($start, 25, 1000),
      'tax_max' => burumable_몫($start, 15, 100),
      'lands' => $lands,
    ];
  }
}

if (!function_exists('burumable_시세깨짐')) {
  function burumable_시세깨짐($q): bool {
    if (!is_array($q) || empty($q['lands']) || !is_array($q['lands'])) {
      return true;
    }
    $start = burumable_냥($q['start'] ?? 0);
    if ($start === '0' || burumable_비교($start, '100') <= 0) {
      return true;
    }
    $n = 0;
    $ones = 0;
    foreach ($q['lands'] as $row) {
      $n++;
      $p = burumable_냥($row['price'] ?? 0);
      if ($p === '0' || $p === '1') {
        $ones++;
      }
    }
    return $n > 0 && $ones === $n;
  }
}

if (!function_exists('burumable_시세복구')) {
  /** 깨진 시세(전부 1)를 다시 계산하고, 현금은 시작금 비율로 맞춤 */
  function burumable_시세복구(array &$state): bool {
    if (!burumable_시세깨짐($state['quote'] ?? null)) {
      return false;
    }
    $oldStart = burumable_냥($state['quote']['start'] ?? 0);
    if ($oldStart === '0') {
      $oldStart = '1';
    }
    $oldSnap = $state['quote']['snap'] ?? null;
    $oldPct = $state['quote']['pct'] ?? null;
    if ($oldPct === null || $oldPct === '' || (float)$oldPct <= 0) {
      $oldPct = burumable_시작퍼센트뽑기();
    }
    $q = burumable_시세표($oldSnap !== null && $oldSnap !== '' ? (string)$oldSnap : null, burumable_시작퍼센트뽑기());
    $newStart = burumable_냥($q['start'] ?? 0);
    if ($newStart === '0') {
      $newStart = (string)부루마블_최소시작금;
      $q['start'] = $newStart;
    }
    $state['quote'] = $q;
    burumable_로그($state, '🔧 시세 복구 · 땅값 기준 ' . burumable_만표시($newStart));
    return true;
  }
}

if (!function_exists('burumable_시세비율맞춤')) {
  /** 땅값 기준을 지급과 같은 스냅샷 10%로 맞춤 */
  function burumable_시세비율맞춤(array &$state): bool {
    $wantPct = defined('부루마블_지급_퍼센트') ? (float)부루마블_지급_퍼센트 : 0.10;
    if ($wantPct <= 0) {
      $wantPct = 0.10;
    }
    $q = $state['quote'] ?? null;
    if (!is_array($q)) {
      return false;
    }
    $snap = burumable_냥($q['snap'] ?? 0);
    if ($snap === '0') {
      return false;
    }
    $havePct = isset($q['pct']) ? (float)$q['pct'] : 0.0;
    $haveGrantPct = isset($q['grant_pct']) ? (float)$q['grant_pct'] : 0.0;
    $havePrem = (int)($q['city_prem'] ?? 0);
    $wantPrem = 2;
    if (abs($havePct - $wantPct) <= 0.0001 && abs($haveGrantPct - $wantPct) <= 0.0001 && $havePrem >= $wantPrem) {
      return false;
    }
    $newQ = burumable_시세표($snap, $wantPct);
    $state['quote'] = $newQ;
    if (is_array($state['pending'] ?? null) && (($state['pending']['kind'] ?? '') === 'buy')) {
      $tid = (int)($state['pending']['tile'] ?? -1);
      $tile = burumable_칸시세($state, burumable_보드()[$tid] ?? ['id' => $tid, 'type' => 'land']);
      $state['pending']['price'] = burumable_냥($tile['price'] ?? 0);
    }
    if (is_array($state['pending'] ?? null) && in_array((string)($state['pending']['kind'] ?? ''), ['takeover', 'takeover_offer'], true)
      && function_exists('burumable_인수금액')) {
      $tid = (int)($state['pending']['tile'] ?? -1);
      if ($tid >= 0) {
        $state['pending']['price'] = burumable_인수금액($state, $tid);
        $on = (string)($state['pending']['owner'] ?? '');
        $tile = burumable_칸시세($state, burumable_보드()[$tid] ?? ['id' => $tid, 'type' => 'land']);
        if ($on !== '' && function_exists('burumable_통행료액')) {
          $from = (string)($state['pending']['from'] ?? '');
          $pi = $from !== '' ? burumable_닉찾기($state, $from) : -1;
          if ($pi >= 0) {
            $state['pending']['rent'] = burumable_통행료액($state, $pi, $tile, $on);
          }
        }
      }
    }
    $log = ($havePrem < $wantPrem && abs($havePct - $wantPct) <= 0.0001)
      ? ('📈 유명 도시 땅값 인상 · ' . burumable_만표시($newQ['start']))
      : ('📉 땅값 기준 스냅샷 ' . burumable_퍼센트표시($wantPct) . ' · 주요 도시 할증 · ' . burumable_만표시($newQ['start']));
    burumable_로그($state, $log);
    return true;
  }
}

if (!function_exists('burumable_시세보장')) {
  function burumable_시세보장(array &$state): array {
    $q = $state['quote'] ?? null;
    if (burumable_시세깨짐($q)) {
      burumable_시세복구($state);
      $q = $state['quote'] ?? burumable_시세표();
    }
    return is_array($q) ? $q : burumable_시세표();
  }
}

if (!function_exists('burumable_칸시세')) {
  function burumable_칸시세(array $state, array $tile): array {
    $q = $state['quote'] ?? null;
    if (!is_array($q) || empty($q['lands'])) {
      $pct = burumable_시세퍼센트읽기(is_array($state['quote'] ?? null) ? $state['quote'] : null);
      $q = burumable_시세표($state['quote']['snap'] ?? null, $pct);
    }
    $id = (string)($tile['id'] ?? '');
    $row = $q['lands'][$id] ?? null;
    if (is_array($row)) {
      $tile['price'] = burumable_냥($row['price'] ?? 0);
      $tile['rent'] = burumable_냥($row['rent'] ?? 0);
    }
    $keep = (int)($state['land_keep'][$id] ?? $state['land_keep'][(int)$id] ?? 100);
    if ($keep > 0 && $keep < 100) {
      $tile['price'] = burumable_몫($tile['price'] ?? 0, $keep, 100);
      $tile['rent'] = burumable_몫($tile['rent'] ?? 0, $keep, 100);
    }
    return $tile;
  }
}

if (!function_exists('burumable_칸유지퍼센트')) {
  function burumable_칸유지퍼센트(array $state, $tid): int {
    $id = (string)$tid;
    $keep = (int)($state['land_keep'][$id] ?? $state['land_keep'][(int)$tid] ?? 100);
    if ($keep < 1 || $keep > 100) {
      return 100;
    }
    return $keep;
  }
}

if (!function_exists('burumable_양도땅값복구')) {
  /**
   * 양도로 깎인 땅값을 한 바퀴(턴 +1)마다 원래 시세의 5%p씩 회복.
   * 한 번 양도(80%)면 4턴 후 100%. 이번 턴에 막 양도한 칸은 다음 턴부터.
   */
  function burumable_양도땅값복구(array &$state): bool {
    if (!empty($state['over'])) {
      return false;
    }
    if (empty($state['land_keep']) || !is_array($state['land_keep'])) {
      return false;
    }
    $add = defined('부루마블_양도땅값복구_퍼센트') ? max(0, (int)부루마블_양도땅값복구_퍼센트) : 5;
    if ($add < 1) {
      return false;
    }
    $T = max(0, (int)($state['turn'] ?? 0));
    if (!isset($state['land_keep_at']) || !is_array($state['land_keep_at'])) {
      $state['land_keep_at'] = [];
    }
    $board = burumable_보드();
    $parts = [];
    $changed = false;
    foreach ($state['land_keep'] as $id => $raw) {
      $keep = (int)$raw;
      if ($keep >= 100) {
        unset($state['land_keep'][$id], $state['land_keep_at'][$id]);
        $changed = true;
        continue;
      }
      if ($keep < 1) {
        continue;
      }
      if (!array_key_exists($id, $state['land_keep_at'])) {
        $state['land_keep_at'][$id] = $T;
        $changed = true;
        continue;
      }
      $last = (int)$state['land_keep_at'][$id];
      $steps = $T - $last;
      if ($steps < 1) {
        continue;
      }
      $keep = min(100, $keep + ($add * $steps));
      $name = (string)($board[(int)$id]['name'] ?? ('칸' . $id));
      if ($keep >= 100) {
        unset($state['land_keep'][$id], $state['land_keep_at'][$id]);
        $parts[] = $name . ' 원상';
      } else {
        $state['land_keep'][$id] = $keep;
        $state['land_keep_at'][$id] = $T;
        $parts[] = $name . ' ' . $keep . '%';
      }
      $changed = true;
    }
    if ($parts !== []) {
      burumable_로그($state, '📈 양도 땅값 회복 +' . $add . '% · ' . implode(' · ', $parts));
    }
    return $changed;
  }
}

if (!function_exists('burumable_시작금')) {
  function burumable_시작금(?array $state = null): string {
    if ($state && !empty($state['quote']['start'])) {
      return burumable_냥($state['quote']['start']);
    }
    $q = is_array($state) ? ($state['quote'] ?? null) : null;
    $pct = burumable_시세퍼센트읽기($q);
    return burumable_냥(burumable_시세표($q['snap'] ?? null, $pct)['start']);
  }
}

if (!function_exists('burumable_지급퍼센트')) {
  function burumable_지급퍼센트(?array $state = null): float {
    if (is_array($state) && isset($state['quote']['grant_pct']) && (float)$state['quote']['grant_pct'] > 0) {
      return (float)$state['quote']['grant_pct'];
    }
    return defined('부루마블_지급_퍼센트') ? (float)부루마블_지급_퍼센트 : 0.10;
  }
}

if (!function_exists('burumable_지급시작금')) {
  /** 전원에게 나눠 주는 부루마블 머니 = 스냅샷의 10% (판마다 동결) */
  function burumable_지급시작금(?array $state = null): string {
    if ($state && isset($state['quote']['grant']) && burumable_냥($state['quote']['grant']) !== '0') {
      return burumable_냥($state['quote']['grant']);
    }
    $q = is_array($state) ? ($state['quote'] ?? null) : null;
    $snap = burumable_냥(is_array($q) ? ($q['snap'] ?? 0) : 0);
    if ($snap === '0') {
      $snap = burumable_스냅샷냥();
    }
    $pct = burumable_지급퍼센트($state);
    $grant = $snap === '0' ? (string)부루마블_최소시작금 : burumable_퍼센트금액($snap, $pct);
    if ($grant === '0' || burumable_비교($grant, 부루마블_최소시작금) < 0) {
      $grant = (string)부루마블_최소시작금;
    }
    return $grant;
  }
}

if (!function_exists('burumable_월급')) {
  function burumable_월급(array $state): string {
    $s = burumable_냥($state['quote']['salary'] ?? 0);
    $pct = burumable_시세퍼센트읽기($state['quote'] ?? null);
    return $s === '0' ? burumable_냥(burumable_시세표($state['quote']['snap'] ?? null, $pct)['salary']) : $s;
  }
}

if (!function_exists('burumable_만표시')) {
  function burumable_만표시($n, bool $좁게 = false): string {
    if ($좁게 && function_exists('wallet_fmt_game_compact')) {
      return (string)wallet_fmt_game_compact($n);
    }
    if (function_exists('wallet_fmt_game')) {
      return (string)wallet_fmt_game($n);
    }
    if (function_exists('게임냥_안전표시')) {
      return (string)게임냥_안전표시($n, '');
    }
    return burumable_냥($n);
  }
}

if (!function_exists('burumable_지갑준비')) {
  function burumable_지갑준비(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    burumable_머니컬럼보장();
    $swap = __DIR__ . '/swap.inc.php';
    if (is_file($swap)) {
      include_once $swap;
    }
  }
}

if (!function_exists('burumable_포인트원문')) {
  function burumable_포인트원문(string $nick): string {
    burumable_머니컬럼보장();
    $nick = trim($nick);
    if ($nick === '') {
      return '0';
    }
    $esc = addslashes($nick);
    $row = @db_select("SELECT CAST(CAST(IFNULL(burumable_money, 0) AS DECIMAL(65,0)) AS CHAR) AS burumable_money FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    $raw = trim((string)($row['burumable_money'] ?? '0'));
    return $raw === '' ? '0' : $raw;
  }
}

if (!function_exists('burumable_포인트양수')) {
  function burumable_포인트양수(string $nick): string {
    $raw = burumable_포인트원문($nick);
    if ($raw !== '' && $raw[0] === '-') {
      return '0';
    }
    return burumable_냥($raw);
  }
}

if (!function_exists('burumable_본방냥')) {
  function burumable_본방냥(string $nick): float {
    $esc = addslashes(trim($nick));
    $row = @db_select("SELECT CAST(IFNULL(newpoint, 0) AS CHAR) AS np FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    return round((float)($row['np'] ?? 0), 1);
  }
}

if (!function_exists('burumable_본냥표시')) {
  function burumable_본냥표시($n): string {
    if (function_exists('newpoint표시')) {
      return (string)newpoint표시($n);
    }
    if (function_exists('wallet_fmt_new')) {
      return (string)wallet_fmt_new($n);
    }
    return (string)round((float)$n, 1);
  }
}

if (!function_exists('burumable_내본냥공개')) {
  /** @return array{my_np:float,my_np_fmt:string,can_swap:bool,swap_pt_fmt:string,swap_del_fmt:string} */
  function burumable_내본냥공개(string $nick): array {
    $np = $nick !== '' ? burumable_본방냥($nick) : 0.0;
    $out = [
      'my_np' => $np,
      'my_np_fmt' => burumable_본냥표시($np),
      'can_swap' => $np >= 0.1,
      'swap_pt_fmt' => '',
      'swap_del_fmt' => '',
    ];
    if (!$out['can_swap'] || !function_exists('스왑_견적계산')) {
      return $out;
    }
    $견적 = 스왑_견적계산('np2pt', $np, true, null, false);
    if (empty($견적['ok'])) {
      return $out;
    }
    $out['swap_pt_fmt'] = burumable_만표시($견적['지급_pt'] ?? 0);
    $del = (float)($견적['삭제_np'] ?? 0);
    if ($del >= 0.1) {
      $out['swap_del_fmt'] = burumable_본냥표시($del);
    }
    return $out;
  }
}

if (!function_exists('burumable_신불인가')) {
  function burumable_신불인가(string $nick): bool {
    $nick = trim($nick);
    if ($nick === '') {
      return false;
    }
    $raw = burumable_포인트원문($nick);
    return $raw !== '' && $raw[0] === '-';
  }
}

if (!function_exists('burumable_게임냥없음')) {
  function burumable_게임냥없음(string $nick): bool {
    $nick = trim($nick);
    if ($nick === '') {
      return false;
    }
    return burumable_비교(burumable_포인트양수($nick), '0') <= 0;
  }
}

if (!function_exists('burumable_최저땅값')) {
  /** 이번 판 나라 칸 중 가장 싼 땅값 */
  function burumable_최저땅값(?array $state = null): string {
    $min = '';
    $board = burumable_보드();
    foreach ($board as $t) {
      if ((string)($t['type'] ?? '') !== 'land') {
        continue;
      }
      $tile = is_array($state) ? burumable_칸시세($state, $t) : $t;
      $p = burumable_냥($tile['price'] ?? 0);
      if ($p === '0') {
        continue;
      }
      if ($min === '' || burumable_비교($p, $min) < 0) {
        $min = $p;
      }
    }
    return $min === '' ? '0' : $min;
  }
}

if (!function_exists('burumable_땅값부족인가')) {
  /** 보유 부루마블 머니가 제일 싼 나라 땅값 미만이면 true */
  function burumable_땅값부족인가(string $nick, ?array $state = null): bool {
    $nick = trim($nick);
    if ($nick === '') {
      return false;
    }
    $min = burumable_최저땅값($state);
    if ($min === '0') {
      return false;
    }
    return burumable_비교(burumable_포인트양수($nick), $min) < 0;
  }
}

if (!function_exists('burumable_턴스킵인가')) {
  /** 신불 턴 넘김 없음. 파산(out)만 차례에서 제외 */
  function burumable_턴스킵인가(string $nick, ?array $state = null): bool {
    unset($nick, $state);
    return false;
  }
}

if (!function_exists('burumable_턴스킵로그')) {
  function burumable_턴스킵로그(array &$state, string $nick): void {
    burumable_로그($state, '🆘 ' . $nick . ' 신불 · 회복될 때까지 턴 넘김');
  }
}

if (!function_exists('burumable_신불적용')) {
  function burumable_신불적용(string $nick): bool {
    $nick = trim($nick);
    if ($nick === '') {
      return false;
    }
    $raw = burumable_포인트원문($nick);
    return $raw !== '' && $raw[0] === '-';
  }
}

if (!function_exists('burumable_입금')) {
  function burumable_입금(string $nick, $amt): void {
    burumable_머니컬럼보장();
    $amt = burumable_냥($amt);
    if ($amt === '0') {
      return;
    }
    $esc = addslashes(trim($nick));
    $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($amt) : $amt;
    if (!preg_match('/^\d+$/', (string)$sql)) {
      return;
    }
    db_query("UPDATE tb_member SET burumable_money = CAST(IFNULL(burumable_money, 0) AS DECIMAL(65,0)) + CAST('{$sql}' AS DECIMAL(65,0)) WHERE name = '{$esc}' LIMIT 1");
  }
}

if (!function_exists('burumable_잔액설정')) {
  /** 부루마블 머니를 이 금액으로 맞춤 (새 판 동일 시작금) */
  function burumable_잔액설정(string $nick, $amt): void {
    burumable_머니컬럼보장();
    $nick = trim($nick);
    if ($nick === '') {
      return;
    }
    $amt = burumable_냥($amt);
    $esc = addslashes($nick);
    $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($amt) : $amt;
    if (!preg_match('/^\d+$/', (string)$sql)) {
      return;
    }
    db_query("UPDATE tb_member SET burumable_money = CAST('{$sql}' AS DECIMAL(65,0)) WHERE name = '{$esc}' LIMIT 1");
  }
}

if (!function_exists('burumable_출금')) {
  function burumable_출금(string $nick, $amt, bool $force = false): bool {
    burumable_머니컬럼보장();
    $amt = burumable_냥($amt);
    if ($amt === '0') {
      return true;
    }
    $esc = addslashes(trim($nick));
    $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($amt) : $amt;
    if (!preg_match('/^\d+$/', (string)$sql)) {
      return false;
    }
    if ($force) {
      $ok = db_query("UPDATE tb_member SET burumable_money = CAST(IFNULL(burumable_money, 0) AS DECIMAL(65,0)) - CAST('{$sql}' AS DECIMAL(65,0)) WHERE name = '{$esc}' LIMIT 1");
      return $ok !== false;
    }
    $have = burumable_포인트양수($nick);
    if (burumable_비교($have, $amt) < 0) {
      return false;
    }
    $ok = db_query("UPDATE tb_member
      SET burumable_money = CAST(IFNULL(burumable_money, 0) AS DECIMAL(65,0)) - CAST('{$sql}' AS DECIMAL(65,0))
      WHERE name = '{$esc}'
        AND CAST(IFNULL(burumable_money, 0) AS DECIMAL(65,0)) >= CAST('{$sql}' AS DECIMAL(65,0))
      LIMIT 1");
    return $ok !== false && burumable_비교(burumable_포인트양수($nick), $have) < 0;
  }
}

if (!function_exists('burumable_자동스왑')) {
  /** 본방냥 자동 스왑 없음. 부루마블 머니만 봄. */
  function burumable_자동스왑(string $nick, $need): array {
    $need = burumable_냥($need);
    $have = burumable_포인트양수($nick);
    if ($need === '0' || burumable_비교($have, $need) >= 0) {
      return ['ok' => true, 'swapped' => false, 'msg' => ''];
    }
    return ['ok' => false, 'swapped' => false, 'msg' => 부루마블_머니이름 . '가 부족해요.'];
  }
}

if (!function_exists('burumable_본냥전체스왑')) {
  /** 본방냥 → 부루마블 머니 스왑 없음 */
  function burumable_본냥전체스왑(string $nick): array {
    return ['ok' => false, 'data' => '부루마블에서는 본방냥 스왑을 하지 않아요.'];
  }
}

if (!function_exists('burumable_현금맞춤')) {
  function burumable_현금맞춤(array &$state, int $i): void {
    if (!isset($state['players'][$i])) {
      return;
    }
    $name = (string)($state['players'][$i]['name'] ?? '');
    $raw = burumable_포인트원문($name);
    $state['players'][$i]['cash'] = ($raw !== '' && $raw[0] === '-') ? '0' : burumable_냥($raw);
    $state['players'][$i]['sinbul'] = false;
  }
}

if (!function_exists('burumable_색')) {
  function burumable_색(int $i): string {
    $c = ['#6ee7b7', '#fbbf24', '#f472b6', '#60a5fa', '#c4b5fd', '#fb923c', '#34d399', '#f87171', '#a3e635', '#22d3ee', '#e879f9', '#facc15'];
    return $c[$i % count($c)];
  }
}

if (!function_exists('burumable_로그')) {
  function burumable_로그(array &$state, string $msg): void {
    $log = isset($state['log']) && is_array($state['log']) ? $state['log'] : [];
    array_unshift($log, $msg);
    $state['log'] = array_slice($log, 0, 20);
  }
}

if (!function_exists('burumable_민호인가')) {
  function burumable_민호인가(string $nick): bool {
    $nick = trim($nick);
    if ($nick === '민호') {
      return true;
    }
    if (function_exists('getTwoCharNick') && getTwoCharNick($nick) === '민호') {
      return true;
    }
    return false;
  }
}

if (!function_exists('burumable_회원목록')) {
  /** 민호 먼저 · 그다음 가입 오래된 순 (민호는 status 무관) */
  function burumable_회원목록(): array {
    $rs = @db_query("
      SELECT idx, name, regdate
      FROM tb_member
      WHERE TRIM(IFNULL(name, '')) NOT IN ('', '오픈')
        AND (status = 0 OR name = '민호')
      ORDER BY CASE WHEN name = '민호' THEN 0 ELSE 1 END,
               regdate ASC, idx ASC
    ");
    $list = [];
    if ($rs) {
      while ($r = db_fetch($rs)) {
        $n = trim((string)($r['name'] ?? ''));
        if ($n === '') {
          continue;
        }
        $list[] = [
          'midx' => (int)($r['idx'] ?? 0),
          'name' => $n,
          'regdate' => (string)($r['regdate'] ?? ''),
        ];
      }
    }
    return $list;
  }
}

if (!function_exists('burumable_슬롯')) {
  function burumable_슬롯(array $m, int $i, $cash = null): array {
    $name = (string)$m['name'];
    if ($cash !== null) {
      burumable_잔액설정($name, $cash);
      $live = burumable_냥($cash);
    } else {
      $live = burumable_포인트양수($name);
    }
    return [
      'midx' => (int)($m['midx'] ?? 0),
      'name' => $name,
      'cash' => $live,
      'pos' => 0,
      'jail' => 0,
      'out' => false,
      'broke' => false,
      'quit' => false,
      'color' => burumable_색($i),
      'sinbul' => false,
      'turn_sec' => defined('부루마블_턴제한초') ? (int)부루마블_턴제한초 : 60,
      'interest' => '0',
      'found_shard' => 0,
      'found_eun' => 0,
      'skip_roll' => 0,
      'grace_go' => 0,
      'rent_cut' => 0,
      'force_buy' => 0,
    ];
  }
}

if (!function_exists('burumable_닉찾기')) {
  function burumable_닉찾기(array $state, string $nick): int {
    foreach ($state['players'] as $i => $p) {
      if ((string)($p['name'] ?? '') === $nick) {
        return (int)$i;
      }
    }
    return -1;
  }
}

if (!function_exists('burumable_현재닉')) {
  function burumable_현재닉(array $state): string {
    $i = (int)($state['cur'] ?? 0);
    return (string)($state['players'][$i]['name'] ?? '');
  }
}

if (!function_exists('burumable_명단동기화')) {
  /** 신규 회원은 맨 뒤에 합류 · 이미 섞인 턴 순서는 유지 · 땅 주인은 닉 기준 */
  function burumable_명단동기화(array &$state): bool {
    if (!empty($state['roster_locked']) || !empty($state['recruiting'])) {
      return false;
    }
    $members = burumable_회원목록();
    if ($members === []) {
      return false;
    }
    $memberByName = [];
    foreach ($members as $m) {
      $n = (string)($m['name'] ?? '');
      if ($n !== '') {
        $memberByName[$n] = $m;
      }
    }
    $curName = burumable_현재닉($state);
    $old = [];
    foreach ($state['players'] as $p) {
      $old[(string)$p['name']] = $p;
    }
    $changed = false;
    $new = [];
    foreach ($state['players'] as $p) {
      $name = (string)($p['name'] ?? '');
      if ($name === '' || !isset($memberByName[$name])) {
        $changed = true;
        continue;
      }
      $p['midx'] = (int)$memberByName[$name]['midx'];
      $new[] = $p;
      unset($memberByName[$name]);
    }
    foreach ($memberByName as $m) {
      $name = (string)$m['name'];
      $start = burumable_지급시작금($state);
      $slot = burumable_슬롯($m, count($new), $start);
      if (empty($state['over']) && empty($old[$name])) {
        $grace = defined('부루마블_후발유예바퀴') ? max(0, (int)부루마블_후발유예바퀴) : 1;
        $slot['grace_go'] = $grace;
        $pct = defined('부루마블_후발통행료_퍼센트') ? max(0, min(100, (int)부루마블_후발통행료_퍼센트)) : 50;
        $msg = '👋 ' . $name . ' 합류 · 시작금 ' . burumable_만표시($start);
        if ($grace > 0) {
          $msg .= ' · ' . $grace . '바퀴 통행료 ' . $pct . '%';
        }
        burumable_로그($state, $msg);
      }
      $new[] = $slot;
      $changed = true;
    }
    if (count($new) !== count($state['players'])) {
      $changed = true;
    }
    foreach ($new as $i => &$p) {
      $p['color'] = burumable_색($i);
    }
    unset($p);
    $state['players'] = $new;
    $idx = burumable_닉찾기($state, $curName);
    $state['cur'] = $idx >= 0 ? $idx : 0;
    if (burumable_현재닉($state) !== $curName) {
      $state['turn_at'] = time();
      $changed = true;
    }
    return $changed;
  }
}

if (!function_exists('burumable_새게임')) {
  function burumable_새게임(string $seedNick = '', int $seedMidx = 0, ?array $applicants = null): array {
    $fromApply = is_array($applicants);
    $members = [];
    if ($fromApply) {
      $seen = [];
      foreach ($applicants as $a) {
        if (!is_array($a)) {
          continue;
        }
        $n = trim((string)($a['name'] ?? ''));
        if ($n === '' || isset($seen[$n])) {
          continue;
        }
        $seen[$n] = true;
        $members[] = [
          'midx' => (int)($a['midx'] ?? 0),
          'name' => $n,
          'regdate' => (string)($a['regdate'] ?? ''),
        ];
      }
    } else {
      $members = burumable_회원목록();
    }
    $hasMinho = false;
    foreach ($members as $m) {
      if (burumable_민호인가((string)$m['name'])) {
        $hasMinho = true;
        break;
      }
    }
    if (!$fromApply && !$hasMinho) {
      $row = @db_select("SELECT idx, name, regdate FROM tb_member WHERE name = '민호' LIMIT 1");
      if (!empty($row['name'])) {
        array_unshift($members, [
          'midx' => (int)($row['idx'] ?? 0),
          'name' => '민호',
          'regdate' => (string)($row['regdate'] ?? ''),
        ]);
      } elseif (burumable_민호인가($seedNick)) {
        array_unshift($members, ['midx' => $seedMidx, 'name' => $seedNick, 'regdate' => '']);
      }
    }
    if (!$fromApply && $members === [] && $seedNick !== '') {
      $members[] = ['midx' => $seedMidx, 'name' => $seedNick, 'regdate' => ''];
    }
    if (count($members) > 1) {
      shuffle($members);
      $members = array_values($members);
    }
    $quote = burumable_시세표();
    $grant = $quote['grant'] ?? burumable_지급시작금(['quote' => $quote]);
    $grantPct표시 = burumable_퍼센트표시((float)($quote['grant_pct'] ?? 0.10));
    $players = [];
    foreach ($members as $i => $m) {
      $players[] = burumable_슬롯($m, $i, $grant);
    }
    $first = $players[0]['name'] ?? '민호';
    $state = [
      'turn' => 0,
      'cur' => 0,
      'players' => $players,
      'owner' => [],
      'build' => [],
      'land_keep' => [],
      'land_keep_at' => [],
      'takeover_refuse' => [],
      'rent_inbox' => [],
      'quote' => $quote,
      'pending' => null,
      'over' => false,
      'paused' => false,
      'winner' => '',
      'log' => ['🎲 부루마블 시작 · 순서 랜덤 · ' . $first . '부터 · 전원 스냅샷 ' . $grantPct표시 . '씩 ' . burumable_만표시($grant) . ' 동일 지급 · 땅값도 같은 기준'],
      'dice' => [0, 0],
      'turn_at' => time(),
      'acted' => false,
      'again' => false,
      'doubles' => 0,
      'turn_clock_ver' => 2,
      'interest_turn' => 0,
      'found_shard' => 0,
      'found_eun' => 0,
      'equal_start' => true,
      'roster_locked' => $fromApply,
      'recruiting' => false,
      'applicants' => $fromApply ? array_values($applicants) : [],
      'entry_n' => count($players),
    ];
    if ($players !== []) {
      burumable_턴알림($state);
      if (function_exists('burumable_시작본방알림')) {
        burumable_시작본방알림($state);
      }
    }
    return $state;
  }
}

if (!function_exists('burumable_새판환급')) {
  /**
   * 새 판 전에 땅·건물 50% + 누적 이자 환급.
   * @return list<array{name:string,land:string,interest:string,total:string,pct:int}>
   */
  function burumable_새판환급(?array $state): array {
    $rows = [];
    if (!is_array($state) || empty($state['players']) || !is_array($state['players'])) {
      return $rows;
    }
    foreach ($state['players'] as $i => $p) {
      if (!is_array($p) || !empty($p['out'])) {
        continue;
      }
      $name = trim((string)($p['name'] ?? ''));
      if ($name === '') {
        continue;
      }
      $sold = $state['prize_sold'] ?? [];
      $ranked = $state['prize_rank_nicks'] ?? [];
      if ((is_array($sold) && in_array($name, $sold, true))
        || (is_array($ranked) && in_array($name, $ranked, true))) {
        continue;
      }
      $환급 = burumable_기권환급액($state, (int)$i);
      $total = burumable_냥($환급['total'] ?? '0');
      if (burumable_비교($total, '1') < 0) {
        continue;
      }
      burumable_입금($name, $total);
      if (function_exists('지급로그')) {
        지급로그('부루마블-새판환급', $name, $name, 0, $total);
      }
      $rows[] = [
        'name' => $name,
        'land' => burumable_냥($환급['land'] ?? '0'),
        'interest' => burumable_냥($환급['interest'] ?? '0'),
        'total' => $total,
        'pct' => (int)($환급['pct'] ?? 50),
      ];
    }
    return $rows;
  }
}

if (!function_exists('burumable_이전내역삭제')) {
  /** 새 판 전에 이전 판 지급로그·대기알림을 전부 지움 */
  function burumable_이전내역삭제(): void {
    @db_query("DELETE FROM tb_point_log WHERE status LIKE '부루마블%' AND status NOT IN ('부루마블-참가', '부루마블-참가환불')");
    if (function_exists('info2알림_테이블_보장')) {
      info2알림_테이블_보장();
    }
    @db_query("DELETE FROM tb_info2_alarm WHERE item LIKE 'burumable%'");
  }
}

if (!function_exists('burumable_새판시작')) {
  function burumable_새판시작(string $seedNick = '', int $seedMidx = 0, ?array $old = null): array {
    $apps = [];
    if (is_array($old) && !empty($old['applicants']) && is_array($old['applicants'])) {
      $apps = $old['applicants'];
    }
    if ($apps === []) {
      return is_array($old) ? $old : burumable_대기방();
    }
    burumable_이전내역삭제();
    $state = burumable_새게임($seedNick, $seedMidx, $apps);
    $grant = burumable_지급시작금($state);
    $n = count($state['players'] ?? []);
    $grantPct표시 = burumable_퍼센트표시(burumable_지급퍼센트($state));
    burumable_홍보알림(
      "🎲 부루마블\n새 판 · 참가 {$n}명 · 전원 스냅샷 {$grantPct표시}씩 " . 부루마블_머니이름 . ' ' . burumable_만표시($grant) . " 동일 지급",
      'burumable_reset'
    );
    return $state;
  }
}

if (!function_exists('burumable_우승인덱스')) {
  /** 생존자 중 자산(머니+땅+건물+이자) 1위. 없으면 -1 */
  function burumable_우승인덱스(array $state): int {
    $best = '-1';
    $bestI = -1;
    foreach ((array)($state['players'] ?? []) as $i => $p) {
      if (!is_array($p) || !empty($p['out'])) {
        continue;
      }
      $name = trim((string)($p['name'] ?? ''));
      if ($name === '') {
        continue;
      }
      $v = burumable_자산($state, (int)$i);
      if ($best === '-1' || burumable_비교($v, $best) > 0) {
        $best = $v;
        $bestI = (int)$i;
      }
    }
    return $bestI;
  }
}

if (!function_exists('burumable_다음1등회차')) {
  function burumable_다음1등회차(): int {
    $coinInc = __DIR__ . '/burumable_coin.inc.php';
    if (is_file($coinInc)) {
      require_once $coinInc;
    }
    if (function_exists('부루마블주화_다음회차')) {
      return 부루마블주화_다음회차();
    }
    $max = 0;
    $rs = @db_query("SELECT sname FROM tb_item WHERE sname LIKE '제%회부루마블1등'");
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $n = trim((string)($row['sname'] ?? ''));
        if (preg_match('/^제(\d+)회부루마블1등$/u', $n, $m)) {
          $max = max($max, (int)$m[1]);
        }
      }
    }
    return $max + 1;
  }
}

if (!function_exists('burumable_1등아이템보장')) {
  function burumable_1등아이템보장(string $sname): bool {
    $sname = trim($sname);
    if ($sname === '' || !preg_match('/^제\d+회부루마블1등$/u', $sname)) {
      return false;
    }
    $coinInc = __DIR__ . '/burumable_coin.inc.php';
    if (is_file($coinInc)) {
      require_once $coinInc;
    }
    if (function_exists('부루마블주화_스키마보장')) {
      부루마블주화_스키마보장();
    }
    return true;
  }
}

if (!function_exists('burumable_게임냥입금')) {
  function burumable_게임냥입금(string $nick, $amt): void {
    $nick = trim($nick);
    $amt = burumable_냥($amt);
    if ($nick === '' || $amt === '0') {
      return;
    }
    $esc = addslashes($nick);
    $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($amt) : $amt;
    if (!preg_match('/^\d+$/', (string)$sql)) {
      return;
    }
    db_query("UPDATE tb_member SET point = CAST(IFNULL(point, 0) AS DECIMAL(65,0)) + CAST('{$sql}' AS DECIMAL(65,0)) WHERE name = '{$esc}' LIMIT 1");
  }
}

if (!function_exists('burumable_게임냥원문')) {
  function burumable_게임냥원문(string $nick): string {
    $esc = addslashes(trim($nick));
    if ($esc === '') {
      return '0';
    }
    $row = @db_select("SELECT CAST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)) AS CHAR) AS pt FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    $raw = trim((string)($row['pt'] ?? '0'));
    return $raw === '' ? '0' : $raw;
  }
}

if (!function_exists('burumable_게임냥양수')) {
  function burumable_게임냥양수(string $nick): string {
    $raw = burumable_게임냥원문($nick);
    if ($raw !== '' && $raw[0] === '-') {
      return '0';
    }
    return burumable_냥($raw);
  }
}

if (!function_exists('burumable_게임냥출금')) {
  function burumable_게임냥출금(string $nick, $amt): bool {
    $nick = trim($nick);
    $amt = burumable_냥($amt);
    if ($nick === '' || $amt === '0') {
      return true;
    }
    $esc = addslashes($nick);
    $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($amt) : $amt;
    if (!preg_match('/^\d+$/', (string)$sql)) {
      return false;
    }
    $have = burumable_게임냥양수($nick);
    if (burumable_비교($have, $amt) < 0) {
      return false;
    }
    $ok = db_query("UPDATE tb_member
      SET point = CAST(IFNULL(point, 0) AS DECIMAL(65,0)) - CAST('{$sql}' AS DECIMAL(65,0))
      WHERE name = '{$esc}'
        AND CAST(IFNULL(point, 0) AS DECIMAL(65,0)) >= CAST('{$sql}' AS DECIMAL(65,0))
      LIMIT 1");
    return $ok !== false && burumable_비교(burumable_게임냥양수($nick), $have) < 0;
  }
}

if (!function_exists('burumable_대기방')) {
  function burumable_대기방(): array {
    $quote = burumable_시세표();
    return [
      'turn' => 0,
      'cur' => 0,
      'players' => [],
      'owner' => [],
      'build' => [],
      'land_keep' => [],
      'land_keep_at' => [],
      'takeover_refuse' => [],
      'rent_inbox' => [],
      'quote' => $quote,
      'pending' => null,
      'over' => true,
      'paused' => false,
      'winner' => '',
      'end_kind' => 'lobby',
      'recruiting' => false,
      'applicants' => [],
      'log' => ['🎲 부루마블 · 민호가 모집시작을 누르면 30분 동안 참가할 수 있어요'],
      'dice' => [0, 0],
      'turn_at' => time(),
      'acted' => false,
      'again' => false,
      'doubles' => 0,
      'turn_clock_ver' => 2,
      'interest_turn' => 0,
      'found_shard' => 0,
      'found_eun' => 0,
      'equal_start' => false,
      'roster_locked' => true,
    ];
  }
}

if (!function_exists('burumable_참가비액')) {
  function burumable_참가비액(?array $state = null): string {
    if (is_array($state) && isset($state['recruit_fee']) && burumable_냥($state['recruit_fee']) !== '0') {
      return burumable_냥($state['recruit_fee']);
    }
    $snap = '0';
    if (is_array($state) && isset($state['recruit_snap'])) {
      $snap = burumable_냥($state['recruit_snap']);
    }
    if ($snap === '0') {
      $snap = burumable_스냅샷냥();
    }
    $pct = defined('부루마블_참가비_퍼센트') ? (float)부루마블_참가비_퍼센트 : 0.01;
    return burumable_퍼센트금액($snap, $pct);
  }
}

if (!function_exists('burumable_모집초')) {
  function burumable_모집초(): int {
    return defined('부루마블_모집초') ? max(60, (int)부루마블_모집초) : 1800;
  }
}

if (!function_exists('burumable_모집연장초')) {
  function burumable_모집연장초(): int {
    return defined('부루마블_모집연장초') ? max(60, (int)부루마블_모집연장초) : 600;
  }
}

if (!function_exists('burumable_알람링크문구')) {
  /** 채팅 알림용 · 개인 code 없이 연구실 .지갑 안내 */
  function burumable_알람링크문구(): string {
    return "코드가 없으면 연구실에서 공창닉으로 입장 후 .지갑\n지갑 발급 → 바로가기 「부루마블」";
  }
}

if (!function_exists('burumable_코드없음화면_출력')) {
  function burumable_코드없음화면_출력(): void {
    $lab = defined('부루마블_연구실주소') ? trim((string)부루마블_연구실주소) : 'https://open.kakao.com/o/gQIh7cIi';
    $lab표시 = htmlspecialchars($lab, ENT_QUOTES, 'UTF-8');
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo '<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>부루마블</title>
<style>
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fee500;
    color: #191919;
    font-family: "Apple SD Gothic Neo", "Noto Sans KR", sans-serif;
    padding: 24px 16px;
  }
  .card {
    width: 100%;
    max-width: 380px;
    background: #fff;
    border-radius: 22px;
    padding: 28px 22px 24px;
    text-align: center;
    box-shadow: 0 10px 28px rgba(0,0,0,0.08);
  }
  .emoji { font-size: 2.2rem; margin-bottom: 10px; }
  h1 {
    margin: 0 0 8px;
    font-size: 1.35rem;
    font-weight: 800;
    letter-spacing: -0.03em;
  }
  p.lead {
    margin: 0 0 16px;
    color: #5c5c5c;
    font-size: 0.92rem;
    line-height: 1.55;
  }
  ol {
    margin: 0 0 20px;
    padding: 14px 14px 14px 34px;
    text-align: left;
    background: #fff8cc;
    border-radius: 14px;
    color: #191919;
    font-size: 0.92rem;
    font-weight: 700;
    line-height: 1.7;
  }
  ol code {
    font-family: inherit;
    background: #191919;
    color: #fee500;
    padding: 1px 7px;
    border-radius: 6px;
    font-size: 0.88rem;
  }
  a.btn {
    display: block;
    background: #191919;
    color: #fee500;
    text-decoration: none;
    font-weight: 800;
    font-size: 1.02rem;
    border-radius: 14px;
    padding: 14px 16px;
  }
  .url {
    margin-top: 14px;
    font-size: 0.78rem;
    color: #8a8a8a;
    word-break: break-all;
  }
</style>
</head>
<body>
  <div class="card">
    <div class="emoji">♟️</div>
    <h1>부루마블</h1>
    <p class="lead">코드가 있어야 참가할 수 있어요.<br>아래 순서로 지갑을 받아 주세요.</p>
    <ol>
      <li>연구실에 <strong>공창 닉네임</strong>으로 입장</li>
      <li><code>.지갑</code> 입력해서 지갑 발급</li>
      <li>지갑 바로가기에서 <strong>부루마블</strong> 접속</li>
    </ol>
    <a class="btn" href="' . $lab표시 . '">연구실 입장</a>
    <div class="url">' . $lab표시 . '</div>
  </div>
</body>
</html>';
    exit;
  }
}

if (!function_exists('burumable_모집남은초')) {
  function burumable_모집남은초(array $state): int {
    if (empty($state['recruiting'])) {
      return 0;
    }
    $limit = (int)($state['recruit_sec'] ?? 0);
    if ($limit < 1) {
      $limit = burumable_모집초();
    }
    $at = (int)($state['recruit_at'] ?? 0);
    if ($at < 1) {
      return $limit;
    }
    return max(0, $limit - (time() - $at));
  }
}

if (!function_exists('burumable_모집열림')) {
  function burumable_모집열림(array $state): bool {
    return !empty($state['recruiting']) && burumable_모집남은초($state) > 0;
  }
}

if (!function_exists('burumable_신청했나')) {
  function burumable_신청했나(array $state, string $nick): bool {
    $nick = trim($nick);
    if ($nick === '' || empty($state['applicants']) || !is_array($state['applicants'])) {
      return false;
    }
    foreach ($state['applicants'] as $a) {
      if (is_array($a) && trim((string)($a['name'] ?? '')) === $nick) {
        return true;
      }
    }
    return false;
  }
}

if (!function_exists('burumable_모집시작')) {
  function burumable_모집시작(array &$state, string $nick): array {
    if (!burumable_민호인가($nick)) {
      return ['ok' => false, 'msg' => '모집은 민호만 할 수 있어요.'];
    }
    if (!empty($state['recruiting']) && burumable_모집남은초($state) > 0) {
      return ['ok' => false, 'msg' => '이미 모집 중이에요.'];
    }
    $apps = is_array($state['applicants'] ?? null) ? $state['applicants'] : [];
    if (!empty($state['recruiting']) && $apps !== []) {
      return ['ok' => false, 'msg' => '참가 신청이 있어요. 판을 시작해 주세요.'];
    }
    $playing = empty($state['over']) && empty($state['recruiting'])
      && ((int)($state['turn'] ?? 0) > 0 || !empty($state['owner']));
    if ($playing) {
      return ['ok' => false, 'msg' => '진행 중인 판은 종료한 뒤에 모집할 수 있어요.'];
    }
    $snap = burumable_스냅샷냥();
    $pct = defined('부루마블_참가비_퍼센트') ? (float)부루마블_참가비_퍼센트 : 0.01;
    $fee = burumable_퍼센트금액($snap, $pct);
    if ($fee === '0') {
      return ['ok' => false, 'msg' => '스냅샷 시총을 읽지 못했어요.'];
    }
    $sec = burumable_모집초();
    $state['recruiting'] = true;
    $state['recruit_at'] = time();
    $state['recruit_sec'] = $sec;
    $state['recruit_snap'] = $snap;
    $state['recruit_fee'] = $fee;
    $state['applicants'] = [];
    $state['over'] = true;
    $state['paused'] = true;
    $state['pending'] = null;
    $분 = (int)floor($sec / 60);
    $feeFmt = burumable_만표시($fee);
    burumable_로그($state, '📣 모집 시작 · ' . $분 . '분 · 참가비 게임냥 ' . $feeFmt . ' (시총 1%) · 개인 취소 불가');
    $msg = "🎲 부루마블 모집\n{$분}분 동안 참가 신청\n참가비 게임냥 {$feeFmt} (스냅샷 시총 1%)\n참가하면 본인이 취소할 수 없어요";
    $msg .= "\n" . burumable_알람링크문구();
    burumable_홍보알림($msg, 'burumable_recruit');
    if (function_exists('burumable_본방알림')) {
      burumable_본방알림($msg, 'burumable_recruit');
    }
    return ['ok' => true, 'msg' => $분 . '분 동안 참가 신청을 받아요. 참가비 게임냥 ' . $feeFmt . ' · 본인 취소 불가 · 모집 취소 시 전액 환불'];
  }
}

if (!function_exists('burumable_모집연장')) {
  function burumable_모집연장(array &$state, string $nick): array {
    if (!burumable_민호인가($nick)) {
      return ['ok' => false, 'msg' => '모집 연장은 민호만 할 수 있어요.'];
    }
    if (empty($state['recruiting'])) {
      return ['ok' => false, 'msg' => '지금은 모집 중이 아니에요.'];
    }
    $add = burumable_모집연장초();
    $limit = (int)($state['recruit_sec'] ?? 0);
    if ($limit < 1) {
      $limit = burumable_모집초();
    }
    $at = (int)($state['recruit_at'] ?? 0);
    if ($at < 1) {
      $at = time();
      $state['recruit_at'] = $at;
    }
    $state['recruit_sec'] = $limit + $add;
    $left = burumable_모집남은초($state);
    $분 = (int)floor($add / 60);
    $남분 = (int)floor($left / 60);
    $남초 = $left % 60;
    $남은 = $남분 . '분 ' . ($남초 < 10 ? '0' : '') . $남초 . '초';
    $n = is_array($state['applicants'] ?? null) ? count($state['applicants']) : 0;
    burumable_로그($state, '⏰ 모집 연장 +' . $분 . '분 · 남은 ' . $남은 . ' · 참가 ' . $n . '명');
    $msg = "🎲 부루마블\n⏰ 모집 시간 +{$분}분\n남은 시간 {$남은}\n\n" . burumable_알람링크문구();
    burumable_홍보알림($msg, 'burumable_recruit_extend');
    return ['ok' => true, 'msg' => '모집 시간을 ' . $분 . '분 연장했어요. 남은 시간 ' . $남은];
  }
}

if (!function_exists('burumable_모집알람재전송')) {
  function burumable_모집알람재전송(array &$state, string $nick): array {
    $nick = trim($nick);
    if ($nick === '') {
      return ['ok' => false, 'msg' => '가방에서 접속한 뒤 보내 주세요.'];
    }
    if (empty($state['recruiting'])) {
      return ['ok' => false, 'msg' => '지금은 모집 중이 아니에요.'];
    }
    $wait = defined('부루마블_모집알람초') ? max(10, (int)부루마블_모집알람초) : 60;
    $last = (int)($state['recruit_remind_at'] ?? 0);
    $ago = time() - $last;
    if ($last > 0 && $ago < $wait) {
      return ['ok' => false, 'msg' => '조금 뒤에 다시 보내 주세요. (' . ($wait - $ago) . '초)'];
    }
    $n = is_array($state['applicants'] ?? null) ? count($state['applicants']) : 0;
    $left = burumable_모집남은초($state);
    if ($left > 0) {
      $남분 = (int)floor($left / 60);
      $남초 = $left % 60;
      $남은 = $남분 . '분 ' . ($남초 < 10 ? '0' : '') . $남초 . '초';
      $msg = "🎲 부루마블\n⏰ 모집 중\n남은 시간 {$남은}\n\n" . burumable_알람링크문구();
    } else {
      $남은 = '종료';
      $msg = "🎲 부루마블\n⏰ 모집 시간 종료\n참가 {$n}명\n\n" . burumable_알람링크문구();
    }
    $state['recruit_remind_at'] = time();
    burumable_로그($state, '📣 모집 알람 · ' . $nick . ' · 남은 ' . $남은 . ' · 참가 ' . $n . '명');
    burumable_홍보알림($msg, 'burumable_recruit_remind');
    return ['ok' => true, 'msg' => '게임방에 모집 알람을 다시 보냈어요.'];
  }
}

if (!function_exists('burumable_참가신청')) {
  function burumable_참가신청(array &$state, string $nick, int $midx = 0): array {
    $nick = trim($nick);
    if ($nick === '' || $midx < 1) {
      return ['ok' => false, 'msg' => '연구실에서 공창닉으로 입장 후 .지갑을 입력해 주세요. 코드가 있어야 참가할 수 있어요.'];
    }
    if (empty($state['recruiting'])) {
      return ['ok' => false, 'msg' => '지금은 모집 중이 아니에요.'];
    }
    if (burumable_모집남은초($state) < 1) {
      return ['ok' => false, 'msg' => '모집 시간이 끝났어요.'];
    }
    if (burumable_신청했나($state, $nick)) {
      return ['ok' => false, 'msg' => '이미 참가했어요. 취소할 수 없어요.'];
    }
    $fee = burumable_참가비액($state);
    if ($fee === '0') {
      return ['ok' => false, 'msg' => '참가비를 계산하지 못했어요.'];
    }
    $have = burumable_게임냥양수($nick);
    if (burumable_비교($have, $fee) < 0) {
      return ['ok' => false, 'msg' => '게임냥이 부족해요. 참가비 ' . burumable_만표시($fee) . '이 필요해요. (보유 ' . burumable_만표시($have) . ')'];
    }
    if (!burumable_게임냥출금($nick, $fee)) {
      return ['ok' => false, 'msg' => '게임냥 차감에 실패했어요. 잔액을 확인해 주세요.'];
    }
    if (function_exists('지급로그')) {
      지급로그('부루마블-참가', $nick, $nick, 0, $fee);
    }
    if (!isset($state['applicants']) || !is_array($state['applicants'])) {
      $state['applicants'] = [];
    }
    $state['applicants'][] = [
      'name' => $nick,
      'midx' => $midx,
      'at' => time(),
      'fee' => $fee,
    ];
    $n = count($state['applicants']);
    burumable_로그($state, '✍️ ' . $nick . ' 참가 · 게임냥 ' . burumable_만표시($fee) . ' 차감 · ' . $n . '명');
    $joinMsg = "🎲 부루마블\n✍️ {$nick} 참가 · {$n}명";
    $needSmall = defined('부루마블_시상인원_소') ? (int)부루마블_시상인원_소 : 5;
    $needBig = defined('부루마블_시상인원_대') ? (int)부루마블_시상인원_대 : 10;
    if (($n === $needSmall || $n === $needBig) && function_exists('burumable_은총조각표문구')) {
      $표 = burumable_은총조각표문구($n);
      if ($표 !== '') {
        $joinMsg .= "\n" . $표;
      }
    }
    burumable_홍보알림($joinMsg, 'burumable_join');
    return ['ok' => true, 'msg' => '참가했어요. 게임냥 ' . burumable_만표시($fee) . ' 차감 · 본인이 취소할 수는 없어요.'];
  }
}

if (!function_exists('burumable_모집취소')) {
  function burumable_모집취소(array &$state, string $nick): array {
    if (!burumable_민호인가($nick)) {
      return ['ok' => false, 'msg' => '모집 취소는 민호만 할 수 있어요.'];
    }
    if (empty($state['recruiting'])) {
      return ['ok' => false, 'msg' => '지금은 모집 중이 아니에요.'];
    }
    $apps = is_array($state['applicants'] ?? null) ? $state['applicants'] : [];
    $n = 0;
    $refunded = '0';
    $defaultFee = burumable_참가비액($state);
    foreach ($apps as $a) {
      if (!is_array($a)) {
        continue;
      }
      $who = trim((string)($a['name'] ?? ''));
      if ($who === '') {
        continue;
      }
      $fee = burumable_냥($a['fee'] ?? $defaultFee);
      if (burumable_비교($fee, '1') >= 0) {
        burumable_게임냥입금($who, $fee);
        if (function_exists('지급로그')) {
          지급로그('부루마블-참가환불', $who, $who, 0, $fee);
        }
        $refunded = burumable_더하기($refunded, $fee);
      }
      $n++;
    }
    $state['recruiting'] = false;
    $state['recruit_at'] = 0;
    $state['recruit_sec'] = 0;
    $state['recruit_snap'] = '';
    $state['recruit_fee'] = '0';
    $state['applicants'] = [];
    $state['paused'] = false;
    $state['pending'] = null;
    if ((string)($state['end_kind'] ?? '') === '') {
      $state['end_kind'] = 'lobby';
    }
    $state['over'] = true;
    $log = '🚫 모집 취소';
    if ($n > 0) {
      $log .= ' · 참가비 전액 환불 ' . $n . '명 · ' . burumable_만표시($refunded);
    } else {
      $log .= ' · 환불할 참가자 없음';
    }
    burumable_로그($state, $log);
    $msg = "🎲 부루마블\n🚫 모집 취소";
    if ($n > 0) {
      $msg .= "\n참가비 전액 환불 {$n}명 · " . burumable_만표시($refunded);
    }
    burumable_홍보알림($msg, 'burumable_recruit_cancel');
    if (function_exists('burumable_본방알림')) {
      burumable_본방알림($msg, 'burumable_recruit_cancel');
    }
    $out = '모집을 취소했어요.';
    if ($n > 0) {
      $out .= ' 참가비 게임냥 전액 환불 ' . $n . '명 · ' . burumable_만표시($refunded);
    }
    return ['ok' => true, 'msg' => $out];
  }
}

if (!function_exists('burumable_판시작가능')) {
  function burumable_판시작가능(array $state, bool $force = false): array {
    if (empty($state['recruiting'])) {
      return ['ok' => false, 'msg' => '먼저 모집을 시작해 주세요.'];
    }
    if (!$force && burumable_모집남은초($state) > 0) {
      return ['ok' => false, 'msg' => '모집 30분이 끝난 뒤에 판을 시작할 수 있어요.'];
    }
    $n = 0;
    foreach ((array)($state['applicants'] ?? []) as $a) {
      if (is_array($a) && trim((string)($a['name'] ?? '')) !== '') {
        $n++;
      }
    }
    if ($n < 1) {
      return ['ok' => false, 'msg' => '참가자가 없어요. 모집을 다시 시작해 주세요.'];
    }
    return ['ok' => true, 'msg' => ''];
  }
}

if (!function_exists('burumable_우승땅비우기')) {
  function burumable_우승땅비우기(array &$state, string $name): int {
    $name = trim($name);
    $n = 0;
    if ($name === '' || empty($state['owner']) || !is_array($state['owner'])) {
      return 0;
    }
    foreach (array_keys($state['owner']) as $tid) {
      if ((string)$state['owner'][$tid] !== $name) {
        continue;
      }
      unset($state['owner'][$tid]);
      if (isset($state['build'][$tid])) {
        unset($state['build'][$tid]);
      }
      if (isset($state['land_keep'][$tid])) {
        unset($state['land_keep'][$tid]);
      }
      if (isset($state['land_keep_at'][$tid])) {
        unset($state['land_keep_at'][$tid]);
      }
      if (function_exists('burumable_인수거절리셋')) {
        burumable_인수거절리셋($state, (int)$tid);
      }
      $n++;
    }
    return $n;
  }
}

if (!function_exists('burumable_우승도시목록')) {
  /**
   * 1등 보유 땅·건물 100% 매각 내역.
   * @return list<array{id:int,name:string,build:string,lv:int,value:string,value_fmt:string}>
   */
  function burumable_우승도시목록(array $state, int $pi): array {
    $name = trim((string)($state['players'][$pi]['name'] ?? ''));
    $rows = [];
    if ($name === '' || empty($state['owner']) || !is_array($state['owner'])) {
      return $rows;
    }
    $board = burumable_보드();
    foreach ($state['owner'] as $tid => $on) {
      if ((string)$on !== $name) {
        continue;
      }
      $id = (int)$tid;
      $raw = $board[$id] ?? ['id' => $id, 'name' => (string)$tid, 'type' => 'land'];
      $tile = function_exists('burumable_칸시세') ? burumable_칸시세($state, $raw) : $raw;
      $lv = function_exists('burumable_건물단계') ? burumable_건물단계($state, $id) : 0;
      $val = function_exists('burumable_칸부동산') ? burumable_칸부동산($state, $id) : '0';
      $rows[] = [
        'id' => $id,
        'name' => (string)($tile['name'] ?? $raw['name'] ?? ''),
        'build' => function_exists('burumable_건물이름') ? burumable_건물이름($lv) : '',
        'lv' => $lv,
        'value' => $val,
        'value_fmt' => burumable_만표시($val),
      ];
    }
    usort($rows, static function ($a, $b) {
      return burumable_비교((string)($b['value'] ?? '0'), (string)($a['value'] ?? '0'));
    });
    return $rows;
  }
}

if (!function_exists('burumable_신청인원')) {
  function burumable_신청인원(?array $state): int {
    $n = 0;
    if (!is_array($state)) {
      return 0;
    }
    foreach ((array)($state['applicants'] ?? []) as $a) {
      if (is_array($a) && trim((string)($a['name'] ?? '')) !== '') {
        $n++;
      }
    }
    return $n;
  }
}

if (!function_exists('burumable_시상인원')) {
  /** 모집 중이면 신청자 수, 시작된 판은 시작 인원(entry_n). */
  function burumable_시상인원(?array $state): int {
    if (!is_array($state)) {
      return 0;
    }
    if (!empty($state['recruiting'])) {
      return burumable_신청인원($state);
    }
    $entry = (int)($state['entry_n'] ?? 0);
    if ($entry > 0) {
      return $entry;
    }
    $n = 0;
    foreach ((array)($state['players'] ?? []) as $p) {
      if (is_array($p) && trim((string)($p['name'] ?? '')) !== '') {
        $n++;
      }
    }
    return $n;
  }
}

if (!function_exists('burumable_등수보상표')) {
  /**
   * 1~5등 은총·은총조각·땅 매도%.
   * 5~9명: 1~3등 · 10명 이상: 1~5등.
   * $n < 1 이면 10명 이상 표.
   * @return array<int,array{eunchong:int,shard:int,land_pct:int}>
   */
  function burumable_등수보상표(int $n = 0): array {
    $land = [
      1 => defined('부루마블_등수매도_1') ? (int)부루마블_등수매도_1 : 100,
      2 => defined('부루마블_등수매도_2') ? (int)부루마블_등수매도_2 : 50,
      3 => defined('부루마블_등수매도_3') ? (int)부루마블_등수매도_3 : 10,
      4 => defined('부루마블_등수매도_4') ? (int)부루마블_등수매도_4 : 0,
      5 => defined('부루마블_등수매도_5') ? (int)부루마블_등수매도_5 : 0,
    ];
    $bigEun = [
      1 => defined('부루마블_등수은총_1') ? (int)부루마블_등수은총_1 : 5,
      2 => defined('부루마블_등수은총_2') ? (int)부루마블_등수은총_2 : 2,
      3 => defined('부루마블_등수은총_3') ? (int)부루마블_등수은총_3 : 1,
      4 => defined('부루마블_등수은총_4') ? (int)부루마블_등수은총_4 : 0,
      5 => defined('부루마블_등수은총_5') ? (int)부루마블_등수은총_5 : 0,
    ];
    $bigShard = [
      1 => defined('부루마블_등수조각_1') ? (int)부루마블_등수조각_1 : 30,
      2 => defined('부루마블_등수조각_2') ? (int)부루마블_등수조각_2 : 20,
      3 => defined('부루마블_등수조각_3') ? (int)부루마블_등수조각_3 : 10,
      4 => defined('부루마블_등수조각_4') ? (int)부루마블_등수조각_4 : 10,
      5 => defined('부루마블_등수조각_5') ? (int)부루마블_등수조각_5 : 5,
    ];
    $smallEun = [
      1 => defined('부루마블_등수소은총_1') ? (int)부루마블_등수소은총_1 : 3,
      2 => defined('부루마블_등수소은총_2') ? (int)부루마블_등수소은총_2 : 2,
      3 => defined('부루마블_등수소은총_3') ? (int)부루마블_등수소은총_3 : 1,
      4 => 0,
      5 => 0,
    ];
    $smallShard = [
      1 => defined('부루마블_등수소조각_1') ? (int)부루마블_등수소조각_1 : 20,
      2 => defined('부루마블_등수소조각_2') ? (int)부루마블_등수소조각_2 : 10,
      3 => defined('부루마블_등수소조각_3') ? (int)부루마블_등수소조각_3 : 0,
      4 => 0,
      5 => 0,
    ];
    $needBig = defined('부루마블_시상인원_대') ? (int)부루마블_시상인원_대 : 10;
    $needSmall = defined('부루마블_시상인원_소') ? (int)부루마블_시상인원_소 : 5;
    $euns = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $shards = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    if ($n < 1 || $n >= $needBig) {
      $euns = $bigEun;
      $shards = $bigShard;
    } elseif ($n >= $needSmall) {
      $euns = $smallEun;
      $shards = $smallShard;
    }
    $out = [];
    for ($i = 1; $i <= 5; $i++) {
      $out[$i] = [
        'eunchong' => (int)($euns[$i] ?? 0),
        'shard' => (int)($shards[$i] ?? 0),
        'land_pct' => (int)($land[$i] ?? 0),
      ];
    }
    return $out;
  }
}

if (!function_exists('burumable_등수보상문구')) {
  function burumable_등수보상문구(array $spec): string {
    $eun = (int)($spec['eunchong'] ?? 0);
    $shard = (int)($spec['shard'] ?? 0);
    $parts = [];
    if ($eun > 0) {
      $parts[] = '은총 ' . $eun . '개';
    }
    if ($shard > 0) {
      $parts[] = '조각 ' . $shard . '개';
    }
    return implode(' + ', $parts);
  }
}

if (!function_exists('burumable_시상표공개')) {
  /**
   * 모집/진행 화면에 띄울 시상 표. 5명 미만이면 빈 표.
   * @return array{n:int,tier:int,rows:list<array{place:int,eunchong:int,shard:int,label:string}>}
   */
  function burumable_시상표공개(int $n): array {
    $needBig = defined('부루마블_시상인원_대') ? (int)부루마블_시상인원_대 : 10;
    $needSmall = defined('부루마블_시상인원_소') ? (int)부루마블_시상인원_소 : 5;
    $tier = 0;
    if ($n >= $needBig) {
      $tier = $needBig;
    } elseif ($n >= $needSmall) {
      $tier = $needSmall;
    }
    $rows = [];
    if ($tier > 0) {
      foreach (burumable_등수보상표($n) as $place => $spec) {
        $eun = (int)($spec['eunchong'] ?? 0);
        $shard = (int)($spec['shard'] ?? 0);
        if ($eun < 1 && $shard < 1) {
          continue;
        }
        $label = function_exists('burumable_등수보상문구') ? burumable_등수보상문구($spec) : '';
        $rows[] = [
          'place' => (int)$place,
          'eunchong' => $eun,
          'shard' => $shard,
          'label' => $label,
        ];
      }
    }
    return ['n' => $n, 'tier' => $tier, 'rows' => $rows];
  }
}

if (!function_exists('burumable_은총조각표문구')) {
  function burumable_은총조각표문구(int $n): string {
    $info = burumable_시상표공개($n);
    if ((int)($info['tier'] ?? 0) < 1 || empty($info['rows'])) {
      return '';
    }
    $lines = ['✨ 시상 (' . (int)$info['tier'] . '명 이상)'];
    foreach ($info['rows'] as $row) {
      $label = trim((string)($row['label'] ?? ''));
      if ($label === '' && function_exists('burumable_등수보상문구')) {
        $label = burumable_등수보상문구($row);
      }
      if ($label === '') {
        continue;
      }
      $lines[] = (int)$row['place'] . '등 ' . $label;
    }
    return implode("\n", $lines);
  }
}

if (!function_exists('burumable_시상공개')) {
  function burumable_시상공개(?array $state): ?array {
    if (!is_array($state) || empty($state['prize_given'])) {
      return null;
    }
    $cities = $state['prize_cities'] ?? [];
    if (!is_array($cities)) {
      $cities = [];
    }
    $land = (string)($state['prize_land'] ?? '0');
    $ranks = $state['prize_ranks'] ?? [];
    if (!is_array($ranks)) {
      $ranks = [];
    }
    $shard = 0;
    $eunchong = 0;
    foreach ($ranks as $r) {
      if ((int)($r['place'] ?? 0) === 1) {
        $shard = (int)($r['shard_got'] ?? $r['shard'] ?? 0);
        $eunchong = (int)($r['eunchong_got'] ?? $r['eunchong'] ?? 0);
        break;
      }
    }
    if ($shard < 1) {
      $shard = (int)($state['prize_shard'] ?? 0);
    }
    if ($eunchong < 1) {
      $eunchong = (int)($state['prize_eunchong'] ?? 0);
    }
    return [
      'item' => (string)($state['prize_item'] ?? ''),
      'land' => $land,
      'land_fmt' => burumable_만표시($land),
      'land_pct' => (int)($state['prize_land_pct'] ?? 100),
      'count' => (int)($state['prize_lands'] ?? count($cities)),
      'round' => (int)($state['prize_round'] ?? 0),
      'cities' => $cities,
      'shard' => $shard,
      'eunchong' => $eunchong,
      'ranks' => $ranks,
    ];
  }
}

if (!function_exists('burumable_미지급시상')) {
  /** 이미 끝난 판에 시상이 안 들어갔으면 지금 지급 */
  function burumable_미지급시상(array &$state): bool {
    if (empty($state['over']) || !empty($state['prize_given'])) {
      return false;
    }
    $w = trim((string)($state['winner'] ?? ''));
    if ($w === '') {
      $wi = function_exists('burumable_우승인덱스') ? burumable_우승인덱스($state) : -1;
      if ($wi < 0 && !empty($state['players']) && is_array($state['players'])) {
        foreach ($state['players'] as $i => $p) {
          if (trim((string)($p['name'] ?? '')) === '미니') {
            $wi = (int)$i;
            break;
          }
        }
      }
      if ($wi >= 0) {
        $state['winner'] = (string)($state['players'][$wi]['name'] ?? '');
      }
    }
    if (trim((string)($state['winner'] ?? '')) === '') {
      return false;
    }
    if (!function_exists('burumable_우승시상')) {
      return false;
    }
    burumable_우승시상($state);
    return !empty($state['prize_given']);
  }
}

if (!function_exists('burumable_우승시상')) {
  /**
   * 1~5등 시상. 1등 아이템 + 은총조각·땅 매도(게임냥).
   * @return array{ok:bool,item:string,land:string,round:int,lands:int,cities:array,ranks:array}
   */
  function burumable_우승시상(array &$state): array {
    $empty = ['ok' => false, 'item' => '', 'land' => '0', 'round' => 0, 'lands' => 0, 'cities' => [], 'ranks' => []];
    if (!empty($state['prize_given'])) {
      return [
        'ok' => true,
        'item' => (string)($state['prize_item'] ?? ''),
        'land' => (string)($state['prize_land'] ?? '0'),
        'round' => (int)($state['prize_round'] ?? 0),
        'lands' => (int)($state['prize_lands'] ?? 0),
        'cities' => is_array($state['prize_cities'] ?? null) ? $state['prize_cities'] : [],
        'ranks' => is_array($state['prize_ranks'] ?? null) ? $state['prize_ranks'] : [],
      ];
    }
    if (function_exists('burumable_등수정리')) {
      burumable_등수정리($state);
    }
    $name = trim((string)($state['winner'] ?? ''));
    $rankRows = is_array($state['ranks'] ?? null) ? $state['ranks'] : [];
    $byPlace = [];
    foreach ($rankRows as $r) {
      if (!is_array($r)) {
        continue;
      }
      $p = (int)($r['place'] ?? 0);
      $n = trim((string)($r['name'] ?? ''));
      if ($p >= 1 && $p <= 5 && $n !== '') {
        $byPlace[$p] = $n;
      }
    }
    if ($name === '' && !empty($byPlace[1])) {
      $name = $byPlace[1];
      $state['winner'] = $name;
    }
    if ($name === '' && $byPlace === []) {
      return $empty;
    }
    $state['prize_given'] = true;
    $state['prize_v'] = 2;

    $bag = dirname(__DIR__) . '/item_bag.inc.php';
    if (is_file($bag)) {
      require_once $bag;
    }

    $entryN = function_exists('burumable_시상인원') ? burumable_시상인원($state) : (int)($state['entry_n'] ?? 0);
    $table = function_exists('burumable_등수보상표') ? burumable_등수보상표($entryN) : [
      1 => ['eunchong' => 5, 'shard' => 30, 'land_pct' => 100],
      2 => ['eunchong' => 2, 'shard' => 20, 'land_pct' => 50],
      3 => ['eunchong' => 1, 'shard' => 10, 'land_pct' => 10],
      4 => ['eunchong' => 0, 'shard' => 10, 'land_pct' => 0],
      5 => ['eunchong' => 0, 'shard' => 5, 'land_pct' => 0],
    ];
    $prizeRanks = [];
    $soldNicks = [];
    $rankNicks = [];
    $firstCities = [];
    $firstLand = '0';
    $firstLands = 0;

    foreach ($table as $place => $spec) {
      $who = $byPlace[$place] ?? ($place === 1 ? $name : '');
      $who = trim((string)$who);
      if ($who === '') {
        continue;
      }
      $rankNicks[] = $who;
      $pi = function_exists('burumable_닉찾기') ? burumable_닉찾기($state, $who) : -1;
      $wantEun = max(0, (int)($spec['eunchong'] ?? 0));
      $wantShard = max(0, (int)($spec['shard'] ?? 0));
      $landPct = max(0, min(100, (int)($spec['land_pct'] ?? 0)));
      $gotEun = 0;
      $gotShard = 0;
      if ($wantEun > 0 && function_exists('burumable_은총지급')) {
        $er = burumable_은총지급($who, $wantEun);
        $gotEun = (int)($er['added'] ?? 0);
      }
      if ($wantShard > 0 && function_exists('burumable_은총조각지급')) {
        $sr = burumable_은총조각지급($who, $wantShard);
        $gotShard = (int)($sr['added'] ?? 0);
      }
      $cities = [];
      $landFull = '0';
      $landPay = '0';
      if ($landPct > 0 && $pi >= 0) {
        $cities = function_exists('burumable_우승도시목록') ? burumable_우승도시목록($state, $pi) : [];
        $landFull = function_exists('burumable_보유부동산') ? burumable_보유부동산($state, $pi) : '0';
        $landPay = ($landPct >= 100)
          ? $landFull
          : (function_exists('burumable_몫') ? burumable_몫($landFull, $landPct, 100) : burumable_퍼센트금액($landFull, $landPct / 100.0));
        foreach ($cities as &$c) {
          $val = (string)($c['value'] ?? '0');
          $pay = ($landPct >= 100)
            ? $val
            : (function_exists('burumable_몫') ? burumable_몫($val, $landPct, 100) : $val);
          $c['payout'] = $pay;
          $c['payout_fmt'] = burumable_만표시($pay);
        }
        unset($c);
        if (burumable_비교($landPay, '1') >= 0) {
          burumable_게임냥입금($who, $landPay);
          if (function_exists('지급로그')) {
            지급로그('부루마블-' . $place . '등매각', $who, $who, 0, $landPay);
          }
        }
        if (function_exists('burumable_우승땅비우기')) {
          burumable_우승땅비우기($state, $who);
        }
        $soldNicks[] = $who;
      }
      if ($place === 1) {
        $firstCities = $cities;
        $firstLand = $landPay;
        $firstLands = count($cities);
      }
      $row = [
        'place' => (int)$place,
        'name' => $who,
        'eunchong' => $wantEun,
        'eunchong_got' => $gotEun,
        'shard' => $wantShard,
        'shard_got' => $gotShard,
        'land_pct' => $landPct,
        'land' => $landPay,
        'land_fmt' => burumable_만표시($landPay),
        'land_full' => $landFull,
        'lands' => count($cities),
        'cities' => $cities,
      ];
      $prizeRanks[] = $row;
      $msg = '🏆 ' . $who . ' ' . $place . '등 보상';
      if ($gotEun > 0) {
        $msg .= ' · 은총 +' . $gotEun;
      } elseif ($wantEun > 0) {
        $msg .= ' · 은총 ' . $wantEun . '개(실패)';
      }
      if ($gotShard > 0) {
        $msg .= ' · 은총조각 +' . $gotShard;
      } elseif ($wantShard > 0) {
        $msg .= ' · 은총조각 ' . $wantShard . '개(한도)';
      }
      if ($landPct > 0 && burumable_비교($landPay, '1') >= 0) {
        $msg .= ' · 땅 ' . $landPct . '% 매각 게임냥 ' . burumable_만표시($landPay);
      }
      burumable_로그($state, $msg);
    }

    $round = 0;
    $item = '';
    $itemOk = false;
    $pi1 = $name !== '' && function_exists('burumable_닉찾기') ? burumable_닉찾기($state, $name) : -1;
    if ($name !== '') {
      $round = burumable_다음1등회차();
      $item = '제' . $round . '회부루마블1등';
      $midx = ($pi1 >= 0) ? (int)($state['players'][$pi1]['midx'] ?? 0) : 0;
      if ($midx < 1) {
        $esc = addslashes($name);
        $row = @db_select("SELECT idx FROM tb_member WHERE name = '{$esc}' LIMIT 1");
        $midx = (int)($row['idx'] ?? 0);
      }
      if ($midx > 0 && burumable_1등아이템보장($item)) {
        if (function_exists('아이템_생성_회원지급')) {
          $r = 아이템_생성_회원지급($midx, $name, $item, 1);
          $itemOk = !empty($r['ok']);
        }
        if (!$itemOk && function_exists('item_bag_ensure_column') && function_exists('item_bag_add')) {
          if (item_bag_ensure_column($item)) {
            $r = item_bag_add($midx, $name, $item, 1);
            $itemOk = !empty($r['ok']);
          }
        }
      }
      if ($itemOk && function_exists('지급로그')) {
        $price = function_exists('아이템_본방시총가_단가') ? 아이템_본방시총가_단가($item) : '0';
        지급로그('부루마블-우승아이템', $name, $name, 0, $price);
      }
    }

    $state['prize_item'] = $itemOk ? $item : '';
    $state['prize_land'] = $firstLand;
    $state['prize_land_pct'] = 100;
    $state['prize_round'] = $round;
    $state['prize_lands'] = $firstLands;
    $state['prize_cities'] = $firstCities;
    $state['prize_ranks'] = $prizeRanks;
    $state['prize_sold'] = array_values(array_unique($soldNicks));
    $state['prize_rank_nicks'] = array_values(array_unique($rankNicks));
    $state['prize_shard'] = 0;
    $state['prize_eunchong'] = 0;
    foreach ($prizeRanks as $pr) {
      if ((int)($pr['place'] ?? 0) === 1) {
        $state['prize_shard'] = (int)($pr['shard_got'] ?? 0);
        $state['prize_eunchong'] = (int)($pr['eunchong_got'] ?? 0);
        break;
      }
    }

    if ($itemOk) {
      burumable_로그($state, '🏆 ' . $name . ' ' . $item);
    }
    foreach ($firstCities as $c) {
      $cname = trim((string)($c['name'] ?? ''));
      if ($cname === '') {
        continue;
      }
      burumable_로그($state, '🏙️ ' . $cname . ' ' . (string)($c['build'] ?? '') . ' ' . (string)($c['value_fmt'] ?? ''));
    }

    $알림 = "🎲 부루마블\n시상";
    if ($name !== '') {
      $알림 = "🎲 부루마블\n1등 " . $name;
    }
    if ($itemOk) {
      $알림 .= "\n" . $item . ' 지급';
    }
    foreach ($prizeRanks as $pr) {
      $line = (int)$pr['place'] . '등 ' . (string)$pr['name'];
      $eg = (int)($pr['eunchong_got'] ?? 0);
      if ($eg > 0) {
        $line .= ' · 은총 +' . $eg;
      }
      $sg = (int)($pr['shard_got'] ?? 0);
      if ($sg > 0) {
        $line .= ' · 은총조각 +' . $sg;
      }
      $lp = (int)($pr['land_pct'] ?? 0);
      if ($lp > 0 && burumable_비교((string)($pr['land'] ?? '0'), '1') >= 0) {
        $line .= ' · 땅 ' . $lp . '% ' . (string)($pr['land_fmt'] ?? '');
      }
      $알림 .= "\n" . $line;
    }
    if (burumable_비교($firstLand, '1') >= 0) {
      $nShow = 0;
      foreach ($firstCities as $c) {
        if ($nShow >= 8) {
          $알림 .= "\n…";
          break;
        }
        $cname = trim((string)($c['name'] ?? ''));
        if ($cname === '') {
          continue;
        }
        $알림 .= "\n" . $cname . ' ' . (string)($c['build'] ?? '') . ' ' . (string)($c['value_fmt'] ?? '');
        $nShow++;
      }
    }
    burumable_홍보알림($알림, 'burumable_prize');

    return [
      'ok' => true,
      'item' => $itemOk ? $item : '',
      'land' => $firstLand,
      'round' => $round,
      'lands' => $firstLands,
      'cities' => $firstCities,
      'ranks' => $prizeRanks,
    ];
  }
}

if (!function_exists('burumable_정산종료')) {
  function burumable_정산종료(array &$state): array {
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    $winI = burumable_우승인덱스($state);
    if ($winI >= 0) {
      $state['winner'] = (string)($state['players'][$winI]['name'] ?? '');
    }
    $state['over'] = true;
    $state['end_kind'] = 'settle';
    if (function_exists('burumable_등수정리')) {
      burumable_등수정리($state);
    }
    $prize = burumable_우승시상($state);
    $refunds = burumable_새판환급($state);
    $state['owner'] = [];
    $state['build'] = [];
    $state['land_keep'] = [];
    $state['land_keep_at'] = [];
    $state['takeover_refuse'] = [];
    $state['rent_inbox'] = [];
    $state['pending'] = null;
    $state['paused'] = false;
    unset($state['pause_turn_left'], $state['pause_buy_left']);
    if (!empty($state['players']) && is_array($state['players'])) {
      foreach ($state['players'] as &$p) {
        $p['interest'] = '0';
      }
      unset($p);
    }
    $pct = $refunds !== [] ? (int)($refunds[0]['pct'] ?? 50) : (defined('부루마블_기권환급_퍼센트') ? (int)부루마블_기권환급_퍼센트 : 50);
    foreach ($refunds as $r) {
      $msg = '💰 ' . $r['name'] . ' 종료 정산 · 땅 ' . $pct . '% ' . burumable_만표시($r['land']);
      if (burumable_비교($r['interest'], '1') >= 0) {
        $msg .= ' · 이자 ' . burumable_만표시($r['interest']);
      }
      burumable_로그($state, $msg);
    }
    $state['over'] = true;
    if (trim((string)($state['winner'] ?? '')) === '') {
      $state['winner'] = (string)($state['players'][$winI]['name'] ?? '');
    }
    burumable_로그($state, '🏁 종료 정산 · 자산 1위 ' . $state['winner']);
    $promo = [];
    foreach ($refunds as $r) {
      $promo[] = $r['name'] . ' ' . burumable_만표시($r['total']);
    }
    $알림 = "🎲 부루마블\n종료 정산 · 땅 {$pct}% 환급";
    if ($promo !== []) {
      $알림 .= "\n" . implode("\n", $promo);
    }
    if ($state['winner'] !== '') {
      $알림 .= "\n우승 " . $state['winner'];
      if (!empty($prize['item'])) {
        $알림 .= ' · ' . $prize['item'];
      }
      foreach ((array)($prize['ranks'] ?? []) as $pr) {
        $pl = (int)($pr['place'] ?? 0);
        if ($pl < 1 || $pl > 5) {
          continue;
        }
        $line = $pl . '등 ' . (string)($pr['name'] ?? '');
        $eg = (int)($pr['eunchong_got'] ?? 0);
        if ($eg > 0) {
          $line .= ' 은총 +' . $eg;
        }
        $sg = (int)($pr['shard_got'] ?? 0);
        if ($sg > 0) {
          $line .= ' 은총조각 +' . $sg;
        }
        if ($pl <= 3 && burumable_비교((string)($pr['land'] ?? '0'), '1') >= 0) {
          $line .= ' 땅' . (int)($pr['land_pct'] ?? 0) . '% ' . (string)($pr['land_fmt'] ?? '');
        }
        $알림 .= "\n" . $line;
      }
    }
    burumable_홍보알림($알림, 'burumable_end');
    return ['ok' => true];
  }
}

if (!function_exists('burumable_일시정지')) {
  function burumable_일시정지(array &$state): array {
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    if (!empty($state['paused'])) {
      return ['ok' => true, 'msg' => '이미 일시멈춤이에요.'];
    }
    $state['pause_turn_left'] = burumable_턴남은초($state);
    $state['pause_buy_left'] = burumable_구매남은초($state);
    $state['paused'] = true;
    burumable_로그($state, '⏸ 일시멈춤');
    burumable_홍보알림("🎲 부루마블\n⏸ 일시멈춤", 'burumable_pause');
    return ['ok' => true];
  }
}

if (!function_exists('burumable_이어하기')) {
  function burumable_이어하기(array &$state): array {
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    if (empty($state['paused'])) {
      return ['ok' => false, 'msg' => '일시멈춤이 아니에요.'];
    }
    $turnLeft = max(0, (int)($state['pause_turn_left'] ?? burumable_턴제한초($state)));
    $buyLeft = max(0, (int)($state['pause_buy_left'] ?? 0));
    $state['paused'] = false;
    unset($state['pause_turn_left'], $state['pause_buy_left']);
    $limit = burumable_턴제한초($state);
    $state['turn_at'] = time() - max(0, $limit - $turnLeft);
    if (is_array($state['pending'] ?? null)) {
      $plimit = burumable_대기제한초($state);
      $state['pending']['at'] = time() - max(0, $plimit - $buyLeft);
    }
    burumable_로그($state, '▶ 이어하기');
    burumable_홍보알림("🎲 부루마블\n▶ 이어하기 · " . burumable_현재닉($state) . ' 님 차례', 'burumable_resume');
    return ['ok' => true];
  }
}

if (!function_exists('burumable_로드')) {
  function burumable_로드(): ?array {
    burumable_테이블보장();
    $row = @db_select('SELECT state FROM tb_game_burumable_world WHERE idx = 1 LIMIT 1');
    if (empty($row['state'])) {
      return null;
    }
    $state = json_decode((string)$row['state'], true);
    if (!is_array($state)) {
      return null;
    }
    if (!isset($state['build']) || !is_array($state['build'])) {
      $state['build'] = [];
    }
    if (!isset($state['land_keep']) || !is_array($state['land_keep'])) {
      $state['land_keep'] = [];
    }
    if (!isset($state['land_keep_at']) || !is_array($state['land_keep_at'])) {
      $state['land_keep_at'] = [];
    }
    if (!isset($state['takeover_refuse']) || !is_array($state['takeover_refuse'])) {
      $state['takeover_refuse'] = [];
    }
    if (!isset($state['rent_inbox']) || !is_array($state['rent_inbox'])) {
      $state['rent_inbox'] = [];
    }
    $n = burumable_칸수();
    if (!empty($state['players']) && is_array($state['players'])) {
      foreach ($state['players'] as &$p) {
        $p['pos'] = ((int)($p['pos'] ?? 0) % $n + $n) % $n;
        $p['cash'] = burumable_냥($p['cash'] ?? 0);
        $p['interest'] = burumable_냥($p['interest'] ?? 0);
        $p['found_shard'] = max(0, (int)($p['found_shard'] ?? 0));
        $p['found_eun'] = max(0, (int)($p['found_eun'] ?? 0));
        $p['skip_roll'] = max(0, (int)($p['skip_roll'] ?? 0));
        $p['grace_go'] = max(0, (int)($p['grace_go'] ?? 0));
        $p['rent_cut'] = max(0, (int)($p['rent_cut'] ?? 0));
        $p['force_buy'] = max(0, (int)($p['force_buy'] ?? 0));
      }
      unset($p);
    }
    return $state;
  }
}

if (!function_exists('burumable_저장')) {
  function burumable_저장(array $state): void {
    burumable_테이블보장();
    $json = addslashes(json_encode($state, JSON_UNESCAPED_UNICODE));
    @db_query("
      INSERT INTO tb_game_burumable_world (idx, state, updated_at)
      VALUES (1, '{$json}', NOW())
      ON DUPLICATE KEY UPDATE state = '{$json}', updated_at = NOW()
    ");
  }
}

if (!function_exists('burumable_락')) {
  function burumable_락(): bool {
    $r = @db_select("SELECT GET_LOCK('burumable_world', 5) AS l");
    return (int)($r['l'] ?? 0) === 1;
  }
}

if (!function_exists('burumable_락해제')) {
  function burumable_락해제(): void {
    @db_query("SELECT RELEASE_LOCK('burumable_world')");
  }
}

if (!function_exists('burumable_보장로드')) {
  function burumable_보장로드(string $seedNick = '', int $seedMidx = 0, string $action = ''): array {
    $state = burumable_로드();
    if ($state === null) {
      $state = function_exists('burumable_대기방') ? burumable_대기방() : burumable_새게임($seedNick, $seedMidx);
      burumable_저장($state);
      return $state;
    }
    $needSave = false;
    $wantPct = defined('부루마블_지급_퍼센트') ? (float)부루마블_지급_퍼센트 : 0.10;
    $hadLandPct = (float)($state['quote']['pct'] ?? 0);
    $hadGrantPct = (float)($state['quote']['grant_pct'] ?? 0);
    if (burumable_시세깨짐($state['quote'] ?? null)) {
      if (burumable_시세복구($state)) {
        $needSave = true;
      }
    }
    if (function_exists('burumable_등수정리') && burumable_등수정리($state)) {
      $needSave = true;
    }
    if (function_exists('burumable_미지급시상') && burumable_미지급시상($state)) {
      $needSave = true;
    }
    if (function_exists('burumable_시세비율맞춤') && burumable_시세비율맞춤($state)) {
      $needSave = true;
    }
    if (empty($state['quote']['start']) || empty($state['quote']['lands'])) {
      if (!empty($state['recruiting']) || (string)($state['end_kind'] ?? '') === 'lobby') {
        $state['quote'] = burumable_시세표();
        $needSave = true;
      } else {
        $state = burumable_새게임($seedNick, $seedMidx);
        burumable_저장($state);
        return $state;
      }
    }
    if (empty($state['over']) && empty($state['recruiting'])) {
      if (burumable_명단동기화($state)) {
        $needSave = true;
      }
      $wantGrantPct = defined('부루마블_지급_퍼센트') ? (float)부루마블_지급_퍼센트 : 0.10;
      if (empty($state['owner']) && (int)($state['turn'] ?? 0) === 0
        && (empty($state['equal_start']) || abs($hadGrantPct - $wantGrantPct) > 0.0001 || abs($hadLandPct - $wantGrantPct) > 0.0001)
        && function_exists('burumable_시작금전원지급') && burumable_시작금전원지급($state)) {
        $needSave = true;
      }
      if (empty($state['paused']) && function_exists('burumable_개인턴제한맞추기') && burumable_개인턴제한맞추기($state)) {
        $needSave = true;
      }
      if (function_exists('burumable_땅이자소급') && burumable_땅이자소급($state)) {
        $needSave = true;
      }
      if (function_exists('burumable_양도땅값복구') && burumable_양도땅값복구($state)) {
        $needSave = true;
      }
      if (function_exists('burumable_신불파산정리') && burumable_신불파산정리($state)) {
        $needSave = true;
      }
      $live = in_array($action, ['roll', 'buy', 'skip', 'build', 'chance', 'travel', 'forcebuy', 'sell', 'give', 'interest', 'broke'], true) ? $seedNick : '';
      if (empty($state['paused']) && function_exists('burumable_턴만료처리') && burumable_턴만료처리($state, $live)) {
        $needSave = true;
      }
      if (function_exists('burumable_종료확인')) {
        $wasOver = !empty($state['over']);
        burumable_종료확인($state);
        if (!$wasOver && !empty($state['over'])) {
          $needSave = true;
        }
      }
    }
    if ($needSave) {
      burumable_저장($state);
    }
    return $state;
  }
}

if (!function_exists('burumable_시작금전원지급')) {
  /** 참가자 전원 부루마블 머니를 스냅샷 10%씩 맞춤 */
  function burumable_시작금전원지급(array &$state): bool {
    $grantPct = defined('부루마블_지급_퍼센트') ? (float)부루마블_지급_퍼센트 : 0.10;
    $snap = burumable_냥($state['quote']['snap'] ?? 0);
    if ($snap === '0') {
      $snap = burumable_스냅샷냥();
    }
    $grant = $snap === '0' ? (string)부루마블_최소시작금 : burumable_퍼센트금액($snap, $grantPct);
    if ($grant === '0' || burumable_비교($grant, 부루마블_최소시작금) < 0) {
      $grant = (string)부루마블_최소시작금;
    }
    $state['quote'] = burumable_시세표($snap === '0' ? null : $snap, $grantPct);
    $grant = burumable_냥($state['quote']['grant'] ?? $grant);
    $n = 0;
    foreach ($state['players'] as $i => $p) {
      $name = trim((string)($p['name'] ?? ''));
      if ($name === '' || !empty($p['out'])) {
        continue;
      }
      burumable_잔액설정($name, $grant);
      burumable_현금맞춤($state, $i);
      $n++;
    }
    $state['equal_start'] = true;
    if ($n > 0) {
      burumable_로그($state, '💰 전원 스냅샷 ' . burumable_퍼센트표시($grantPct) . '씩 ' . burumable_만표시($grant) . ' 동일 지급 · 땅값도 같은 기준 (' . $n . '명)');
    }
    return true;
  }
}

if (!function_exists('burumable_칸부동산')) {
  /** 한 칸의 땅값 + 펜션/호텔/랜드마크 건설비 */
  function burumable_칸부동산(array $state, int $tid): string {
    $raw = burumable_보드()[$tid] ?? ['id' => $tid, 'type' => 'land'];
    if ((string)($raw['type'] ?? '') !== 'land') {
      return '0';
    }
    $tile = burumable_칸시세($state, $raw);
    $sum = burumable_냥($tile['price'] ?? 0);
    $lv = burumable_건물단계($state, $tid);
    if ($lv >= 1) {
      $sum = burumable_더하기($sum, burumable_건물비($tile, 1));
    }
    if ($lv >= 2) {
      $sum = burumable_더하기($sum, burumable_건물비($tile, 2));
    }
    if ($lv >= 3) {
      $sum = burumable_더하기($sum, burumable_건물비($tile, 3));
    }
    return $sum;
  }
}

if (!function_exists('burumable_보유부동산')) {
  /** 땅값 + 펜션/호텔/랜드마크 건설비 (현금 제외) */
  function burumable_보유부동산(array $state, int $i): string {
    $name = (string)($state['players'][$i]['name'] ?? '');
    $sum = '0';
    if ($name === '' || empty($state['owner']) || !is_array($state['owner'])) {
      return $sum;
    }
    foreach ($state['owner'] as $tid => $on) {
      if ((string)$on !== $name) {
        continue;
      }
      $sum = burumable_더하기($sum, burumable_칸부동산($state, (int)$tid));
    }
    return $sum;
  }
}

if (!function_exists('burumable_세금퍼센트')) {
  function burumable_세금퍼센트(): int {
    return defined('부루마블_세금_퍼센트') ? max(0, (int)부루마블_세금_퍼센트) : 10;
  }
}

if (!function_exists('burumable_세금액')) {
  /**
   * 보유 땅값+건물 기준 세금. 땅 0칸이면 면제.
   * @return array{amt:string,lands:int,base:string,pct:int,exempt:bool}
   */
  function burumable_세금액(array $state, int $pi): array {
    $pct = burumable_세금퍼센트();
    $empty = ['amt' => '0', 'lands' => 0, 'base' => '0', 'pct' => $pct, 'exempt' => true];
    if ($pi < 0 || empty($state['players'][$pi]) || !empty($state['players'][$pi]['out'])) {
      return $empty;
    }
    $name = trim((string)($state['players'][$pi]['name'] ?? ''));
    if ($name === '') {
      return $empty;
    }
    $lands = function_exists('burumable_보유땅수') ? burumable_보유땅수($state, $name) : 0;
    if ($lands < 1) {
      return $empty;
    }
    $base = burumable_보유부동산($state, $pi);
    $amt = burumable_몫($base, $pct, 100);
    if (burumable_비교($amt, '1') < 0) {
      return ['amt' => '0', 'lands' => $lands, 'base' => $base, 'pct' => $pct, 'exempt' => true];
    }
    return ['amt' => $amt, 'lands' => $lands, 'base' => $base, 'pct' => $pct, 'exempt' => false];
  }
}

if (!function_exists('burumable_퍼센트퍼밀')) {
  /** 0.5% → 5 (분모 1000) */
  function burumable_퍼센트퍼밀($pct): int {
    $s = trim((string)$pct);
    if ($s === '' || $s === '0') {
      return 0;
    }
    if (function_exists('bcmul') && function_exists('bcadd')) {
      return max(0, (int)bcadd(bcmul($s, '10', 4), '0', 0));
    }
    return max(0, (int)round(((float)$s) * 10));
  }
}

if (!function_exists('burumable_칸이자퍼밀')) {
  /** 빈땅 0.5% · 펜션 +0.1% · 호텔 +0.4% · 랜드마크 +1.0%(아래 단계는 겹치지 않음) */
  function burumable_칸이자퍼밀(int $lv): int {
    $base = defined('부루마블_이자_퍼센트') ? burumable_퍼센트퍼밀(부루마블_이자_퍼센트) : 5;
    if ($lv >= 3) {
      $add = defined('부루마블_이자_랜드마크_추가') ? burumable_퍼센트퍼밀(부루마블_이자_랜드마크_추가) : 10;
      return $base + $add;
    }
    if ($lv >= 2) {
      $add = defined('부루마블_이자_호텔_추가') ? burumable_퍼센트퍼밀(부루마블_이자_호텔_추가) : 4;
      return $base + $add;
    }
    if ($lv >= 1) {
      $add = defined('부루마블_이자_펜션_추가') ? burumable_퍼센트퍼밀(부루마블_이자_펜션_추가) : 1;
      return $base + $add;
    }
    return $base;
  }
}

if (!function_exists('burumable_이자안내')) {
  function burumable_이자안내(): string {
    $base = defined('부루마블_이자_퍼센트') ? (string)부루마블_이자_퍼센트 : '0.5';
    $pen = defined('부루마블_이자_펜션_추가') ? (string)부루마블_이자_펜션_추가 : '0.1';
    $hot = defined('부루마블_이자_호텔_추가') ? (string)부루마블_이자_호텔_추가 : '0.4';
    $lm = defined('부루마블_이자_랜드마크_추가') ? (string)부루마블_이자_랜드마크_추가 : '1.0';
    return $base . '% · 펜션 +' . $pen . '% · 호텔 +' . $hot . '% · 랜드마크 +' . $lm . '%';
  }
}

if (!function_exists('burumable_플레이어땅이자')) {
  /** 한 바퀴분 이자. $rounds 바퀴면 그만큼 곱함. 땅값 × 칸 비율 */
  function burumable_플레이어땅이자(array $state, int $i, int $rounds = 1): string {
    $rounds = max(1, $rounds);
    $name = (string)($state['players'][$i]['name'] ?? '');
    $sum = '0';
    if ($name === '' || empty($state['owner']) || !is_array($state['owner'])) {
      return $sum;
    }
    $board = burumable_보드();
    foreach ($state['owner'] as $tid => $on) {
      if ((string)$on !== $name) {
        continue;
      }
      $tid = (int)$tid;
      $raw = $board[$tid] ?? ['id' => $tid, 'type' => 'land'];
      if ((string)($raw['type'] ?? '') !== 'land') {
        continue;
      }
      $tile = burumable_칸시세($state, $raw);
      $permille = burumable_칸이자퍼밀(burumable_건물단계($state, $tid)) * $rounds;
      $pay = burumable_몫($tile['price'] ?? 0, $permille, 1000);
      $sum = burumable_더하기($sum, $pay);
    }
    return $sum;
  }
}

if (!function_exists('burumable_누적이자')) {
  function burumable_누적이자(array $state, int $i): string {
    return burumable_냥($state['players'][$i]['interest'] ?? 0);
  }
}

if (!function_exists('burumable_땅이자적립')) {
  /**
   * $rounds 바퀴분 이자를 현재 보유 땅 기준으로 누적.
   * @return bool 적립이 있었으면 true
   */
  function burumable_땅이자적립(array &$state, int $rounds, bool $소급 = false): bool {
    if (!empty($state['over'])) {
      return false;
    }
    $rounds = max(1, $rounds);
    $parts = [];
    foreach ($state['players'] as $i => &$p) {
      if (!empty($p['out'])) {
        continue;
      }
      $name = trim((string)($p['name'] ?? ''));
      if ($name === '') {
        continue;
      }
      $pay = function_exists('burumable_플레이어땅이자') ? burumable_플레이어땅이자($state, $i, $rounds) : '0';
      if (burumable_비교($pay, '1') < 0) {
        continue;
      }
      $p['interest'] = burumable_더하기(burumable_냥($p['interest'] ?? 0), $pay);
      $parts[] = $name . ' +' . burumable_만표시($pay);
    }
    unset($p);
    if ($parts === []) {
      return false;
    }
    $안내 = function_exists('burumable_이자안내') ? burumable_이자안내() : '0.5%';
    if ($소급) {
      burumable_로그($state, '🏠 지난 ' . $rounds . '턴 땅 이자 소급 (' . $안내 . ') · ' . implode(' · ', $parts));
    } else {
      burumable_로그($state, '🏠 턴 ' . (int)($state['turn'] ?? 0) . ' 땅 이자 적립 (' . $안내 . ') · ' . implode(' · ', $parts));
    }
    return true;
  }
}

if (!function_exists('burumable_땅이자소급')) {
  /** 이자 기능 전에 지나간 턴을 현재 보유 땅 기준으로 한 번에 적립. 변경되면 true */
  function burumable_땅이자소급(array &$state): bool {
    if (!empty($state['over'])) {
      return false;
    }
    $turn = max(0, (int)($state['turn'] ?? 0));
    $paid = array_key_exists('interest_turn', $state) ? max(0, (int)$state['interest_turn']) : 0;
    if ($paid >= $turn) {
      return false;
    }
    $missing = $turn - $paid;
    burumable_땅이자적립($state, $missing, true);
    $state['interest_turn'] = $turn;
    return true;
  }
}

if (!function_exists('burumable_땅이자지급')) {
  /** 한 바퀴가 돌 때 포기·파산하지 않은 땅 주인에게 보유 부동산의 N%를 누적 이자로 적립 */
  function burumable_땅이자지급(array &$state): void {
    burumable_땅이자적립($state, 1, false);
    $state['interest_turn'] = max(0, (int)($state['turn'] ?? 0));
  }
}

if (!function_exists('burumable_땅이자수령')) {
  function burumable_땅이자수령(array &$state, string $nick, string $to = 'money'): array {
    $pi = burumable_닉찾기($state, $nick);
    if ($pi < 0) {
      return ['ok' => false, 'msg' => '참가자가 아니에요.'];
    }
    if (!empty($state['players'][$pi]['out'])) {
      return ['ok' => false, 'msg' => '이번 판을 포기한 뒤에는 이자를 받을 수 없어요.'];
    }
    $amt = burumable_누적이자($state, $pi);
    if (burumable_비교($amt, '1') < 0) {
      return ['ok' => false, 'msg' => '받을 이자가 없어요.'];
    }
    $to = strtolower(trim($to));
    if (in_array($to, ['pt', 'point', '게임냥', 'game'], true)) {
      $to = 'pt';
    } else {
      $to = 'money';
    }
    $pend = $state['pending'] ?? null;
    $distress = is_array($pend) && ($pend['kind'] ?? '') === 'distress'
      && trim((string)($pend['from'] ?? $pend['wait'] ?? '')) === $nick;
    if ($distress) {
      $to = 'money';
    }
    $moneyName = defined('부루마블_머니이름') ? 부루마블_머니이름 : '부루마블 머니';
    if ($to === 'pt') {
      if (!function_exists('burumable_게임냥입금')) {
        return ['ok' => false, 'msg' => '게임냥 지급을 할 수 없어요.'];
      }
      burumable_게임냥입금($nick, $amt);
      $label = '게임냥';
      if (function_exists('지급로그')) {
        지급로그('부루마블-땅이자-게임냥', $nick, $nick, $amt, 0);
      }
    } else {
      burumable_입금($nick, $amt);
      $label = $moneyName;
      if (function_exists('지급로그')) {
        지급로그('부루마블-땅이자', $nick, $nick, 0, $amt);
      }
    }
    $state['players'][$pi]['interest'] = '0';
    burumable_현금맞춤($state, $pi);
    burumable_로그($state, '💰 ' . $nick . ' 땅 이자 수령 ' . burumable_만표시($amt) . ' · ' . $label);
    if (function_exists('burumable_홍보알림')) {
      burumable_홍보알림("🎲 부루마블\n💰 {$nick} 땅 이자 " . burumable_만표시($amt) . "\n{$label}", 'burumable_interest');
    }
    if (function_exists('burumable_빚청산시도')) {
      burumable_빚청산시도($state);
    }
    return ['ok' => true, 'msg' => '땅 이자 ' . burumable_만표시($amt) . '를 ' . $label . '로 받았어요.'];
  }
}

if (!function_exists('burumable_땅이자정산')) {
  /** 종료 시 남은 누적 이자를 부루마블 머니로 지급 */
  function burumable_땅이자정산(array &$state): void {
    foreach ($state['players'] as $i => &$p) {
      if (!empty($p['out'])) {
        $p['interest'] = '0';
        continue;
      }
      $name = trim((string)($p['name'] ?? ''));
      $amt = burumable_냥($p['interest'] ?? 0);
      if ($name === '' || burumable_비교($amt, '1') < 0) {
        $p['interest'] = '0';
        continue;
      }
      burumable_입금($name, $amt);
      $p['interest'] = '0';
      burumable_현금맞춤($state, $i);
      if (function_exists('지급로그')) {
        지급로그('부루마블-땅이자', $name, $name, 0, $amt);
      }
      burumable_로그($state, '💰 ' . $name . ' 종료 정산 이자 ' . burumable_만표시($amt));
    }
    unset($p);
  }
}

if (!function_exists('burumable_자산')) {
  function burumable_자산(array $state, int $i): string {
    $name = (string)($state['players'][$i]['name'] ?? '');
    $sum = burumable_더하기(burumable_포인트양수($name), burumable_보유부동산($state, $i));
    return burumable_더하기($sum, burumable_누적이자($state, $i));
  }
}

if (!function_exists('burumable_생존닉목록')) {
  /** @return list<string> */
  function burumable_생존닉목록(array $state): array {
    $names = [];
    foreach ((array)($state['players'] ?? []) as $p) {
      if (!is_array($p) || !empty($p['out'])) {
        continue;
      }
      $n = trim((string)($p['name'] ?? ''));
      if ($n !== '') {
        $names[] = $n;
      }
    }
    return $names;
  }
}

if (!function_exists('burumable_로그에서탈락복원')) {
  /**
   * 로그에서 파산·포기 시각순(오래된 것 먼저).
   * @return list<array{name:string,how:string}>
   */
  function burumable_로그에서탈락복원(array $state): array {
    $out = [];
    $seen = [];
    $logs = $state['log'] ?? [];
    if (!is_array($logs)) {
      return $out;
    }
    foreach (array_reverse($logs) as $line) {
      $line = trim((string)$line);
      $name = '';
      $how = '';
      if (preg_match('/^💀\s+(.+?)\s+파산/u', $line, $m)) {
        $name = trim((string)$m[1]);
        $how = '파산';
      } elseif (preg_match('/^(.+?)\s+이번 판 포기/u', $line, $m)) {
        $name = trim((string)$m[1]);
        $how = '포기';
      }
      if ($name === '' || isset($seen[$name])) {
        continue;
      }
      $seen[$name] = true;
      $out[] = ['name' => $name, 'how' => $how];
    }
    return $out;
  }
}

if (!function_exists('burumable_결승확정')) {
  /**
   * 생존이 5명 이하가 되면 그 명단을 결승으로 고정.
   * 이미 4명 이하면 로그에서 최근 탈락을 채워 5명을 복원한다.
   */
  function burumable_결승확정(array &$state): bool {
    $have = $state['finalists'] ?? null;
    if (is_array($have) && $have !== []) {
      return false;
    }
    $alive = burumable_생존닉목록($state);
    $n = count($alive);
    if ($n > 5) {
      return false;
    }
    if ($n < 1 && empty($state['over'])) {
      return false;
    }
    $elim = [];
    $finalists = $alive;
    if ($n < 5) {
      $need = 5 - $n;
      $fromLog = burumable_로그에서탈락복원($state);
      $picked = [];
      for ($i = count($fromLog) - 1; $i >= 0 && count($picked) < $need; $i--) {
        $nm = trim((string)($fromLog[$i]['name'] ?? ''));
        if ($nm === '' || in_array($nm, $alive, true) || in_array($nm, $finalists, true)) {
          continue;
        }
        if (function_exists('burumable_닉찾기') && burumable_닉찾기($state, $nm) < 0) {
          continue;
        }
        $picked[] = $fromLog[$i];
      }
      $picked = array_reverse($picked);
      foreach ($picked as $e) {
        $elim[] = $e;
        $finalists[] = (string)$e['name'];
      }
    }
    $uniq = [];
    foreach ($finalists as $nm) {
      $nm = trim((string)$nm);
      if ($nm !== '' && !in_array($nm, $uniq, true)) {
        $uniq[] = $nm;
      }
    }
    if ($uniq === []) {
      return false;
    }
    $state['finalists'] = $uniq;
    if ($elim !== []) {
      $state['elim_order'] = $elim;
    } elseif (!isset($state['elim_order']) || !is_array($state['elim_order'])) {
      $state['elim_order'] = [];
    }
    if ($n === 5) {
      burumable_로그($state, '🎯 결승 5명 · ' . implode(' · ', $alive));
    }
    return true;
  }
}

if (!function_exists('burumable_결승탈락기록')) {
  /** 결승 5명 중 파산·포기하면 5→2등 순으로 기록 */
  function burumable_결승탈락기록(array &$state, string $nick, string $how = '파산'): bool {
    $nick = trim($nick);
    if ($nick === '') {
      return false;
    }
    burumable_결승확정($state);
    $finalists = $state['finalists'] ?? [];
    if (!is_array($finalists) || !in_array($nick, $finalists, true)) {
      return false;
    }
    $elim = $state['elim_order'] ?? [];
    if (!is_array($elim)) {
      $elim = [];
    }
    foreach ($elim as $e) {
      $en = is_array($e) ? trim((string)($e['name'] ?? '')) : trim((string)$e);
      if ($en === $nick) {
        return false;
      }
    }
    $how = ($how === '포기' || $how === 'quit') ? '포기' : '파산';
    $elim[] = ['name' => $nick, 'how' => $how];
    $state['elim_order'] = $elim;
    $place = count($finalists) - (count($elim) - 1);
    if ($place >= 2 && $place <= 5) {
      burumable_로그($state, '📌 ' . $nick . ' ' . $place . '등 · ' . $how);
    }
    return true;
  }
}

if (!function_exists('burumable_등수정리')) {
  /**
   * 결승 탈락순 + 남은 사람 자산순으로 1~5등.
   * @return bool 변경 여부
   */
  function burumable_등수정리(array &$state): bool {
    $old = $state['ranks'] ?? null;
    if (empty($state['over'])) {
      return burumable_결승확정($state);
    }
    burumable_결승확정($state);
    $finalists = $state['finalists'] ?? [];
    if (!is_array($finalists) || $finalists === []) {
      $finalists = burumable_생존닉목록($state);
      $w = trim((string)($state['winner'] ?? ''));
      if ($w !== '' && !in_array($w, $finalists, true)) {
        array_unshift($finalists, $w);
      }
      $state['finalists'] = $finalists;
    }
    $elim = $state['elim_order'] ?? [];
    if (!is_array($elim)) {
      $elim = [];
    }
    $placed = [];
    $nPool = count($finalists);
    $ei = 0;
    foreach ($elim as $e) {
      $nm = is_array($e) ? trim((string)($e['name'] ?? '')) : trim((string)$e);
      $how = is_array($e) ? (string)($e['how'] ?? '파산') : '파산';
      if ($nm === '' || isset($placed[$nm]) || !in_array($nm, $finalists, true)) {
        continue;
      }
      $place = $nPool - $ei;
      $ei++;
      if ($place < 1) {
        continue;
      }
      $placed[$nm] = [
        'place' => $place,
        'name' => $nm,
        'how' => ($how === '포기') ? '포기' : '파산',
      ];
    }
    $left = [];
    foreach ($finalists as $nm) {
      $nm = trim((string)$nm);
      if ($nm === '' || isset($placed[$nm])) {
        continue;
      }
      $pi = function_exists('burumable_닉찾기') ? burumable_닉찾기($state, $nm) : -1;
      $left[] = [
        'name' => $nm,
        'asset' => ($pi >= 0 && function_exists('burumable_자산')) ? burumable_자산($state, $pi) : '0',
      ];
    }
    usort($left, static function ($a, $b) {
      $c = burumable_비교((string)($b['asset'] ?? '0'), (string)($a['asset'] ?? '0'));
      if ($c !== 0) {
        return $c;
      }
      return strcmp((string)$a['name'], (string)$b['name']);
    });
    $howLeft = count($left) <= 1 ? '우승' : '자산';
    foreach ($left as $k => $row) {
      $placed[$row['name']] = [
        'place' => $k + 1,
        'name' => $row['name'],
        'how' => $k === 0 ? '우승' : $howLeft,
      ];
    }
    $rows = array_values($placed);
    usort($rows, static function ($a, $b) {
      return ((int)$a['place']) <=> ((int)$b['place']);
    });
    $trim = [];
    foreach ($rows as $r) {
      $p = (int)$r['place'];
      if ($p < 1 || $p > 5) {
        continue;
      }
      $how = (string)($r['how'] ?? '');
      $label = $how === '포기' ? '포기' : ($how === '자산' ? '자산' : ($how === '우승' ? '우승' : '파산'));
      $trim[] = [
        'place' => $p,
        'name' => (string)$r['name'],
        'how' => $how === '포기' ? '포기' : ($how === '자산' ? '자산' : ($how === '우승' ? '우승' : '파산')),
        'how_label' => $label,
      ];
    }
    $state['ranks'] = $trim;
    foreach ($trim as $r) {
      if ((int)$r['place'] === 1 && trim((string)$r['name']) !== '') {
        $state['winner'] = (string)$r['name'];
        break;
      }
    }
    return $old !== $trim;
  }
}

if (!function_exists('burumable_등수공개')) {
  /** @return list<array{place:int,name:string,how:string,how_label:string}> */
  function burumable_등수공개(?array $state): array {
    if (!is_array($state)) {
      return [];
    }
    $rows = $state['ranks'] ?? [];
    if (!is_array($rows) || $rows === []) {
      return [];
    }
    $out = [];
    foreach ($rows as $r) {
      if (!is_array($r)) {
        continue;
      }
      $p = (int)($r['place'] ?? 0);
      $n = trim((string)($r['name'] ?? ''));
      if ($p < 1 || $p > 5 || $n === '') {
        continue;
      }
      $how = (string)($r['how'] ?? '');
      $label = (string)($r['how_label'] ?? '');
      if ($label === '') {
        $label = $how === '포기' ? '포기' : ($how === '자산' ? '자산' : ($how === '우승' ? '우승' : '파산'));
      }
      $out[] = [
        'place' => $p,
        'name' => $n,
        'how' => $how,
        'how_label' => $label,
      ];
    }
    usort($out, static function ($a, $b) {
      return ((int)$a['place']) <=> ((int)$b['place']);
    });
    $prizes = [];
    foreach ((array)($state['prize_ranks'] ?? []) as $pr) {
      if (!is_array($pr)) {
        continue;
      }
      $pp = (int)($pr['place'] ?? 0);
      if ($pp >= 1 && $pp <= 5) {
        $prizes[$pp] = $pr;
      }
    }
    if ($prizes !== []) {
      foreach ($out as &$row) {
        $pr = $prizes[(int)$row['place']] ?? null;
        if (!is_array($pr)) {
          continue;
        }
        $row['shard'] = (int)($pr['shard'] ?? 0);
        $row['shard_got'] = (int)($pr['shard_got'] ?? 0);
        $row['land_pct'] = (int)($pr['land_pct'] ?? 0);
        $row['land'] = (string)($pr['land'] ?? '0');
        $row['land_fmt'] = (string)($pr['land_fmt'] ?? '');
        $row['lands'] = (int)($pr['lands'] ?? 0);
      }
      unset($row);
    }
    return $out;
  }
}

if (!function_exists('burumable_종료확인')) {
  function burumable_종료확인(array &$state): void {
    if (!empty($state['over'])) {
      return;
    }
    if (function_exists('burumable_결승확정')) {
      burumable_결승확정($state);
    }
    $alive = [];
    foreach ($state['players'] as $i => $p) {
      if (empty($p['out'])) {
        $alive[] = $i;
      }
    }
    if (count($alive) <= 1) {
      $win = $alive[0] ?? 0;
      $state['over'] = true;
      $state['end_kind'] = 'last';
      $state['winner'] = (string)($state['players'][$win]['name'] ?? '');
      $state['pending'] = null;
      if (function_exists('burumable_땅이자정산')) {
        burumable_땅이자정산($state);
      }
      if (function_exists('burumable_등수정리')) {
        burumable_등수정리($state);
      }
      $prize = function_exists('burumable_우승시상') ? burumable_우승시상($state) : ['item' => '', 'land' => '0'];
      if (function_exists('burumable_발견이력초기화')) {
        burumable_발견이력초기화($state);
      }
      burumable_로그($state, '🏁 ' . $state['winner'] . ' 우승!');
      if (trim((string)$state['winner']) !== '') {
        $알림 = "🎲 부루마블\n우승 " . $state['winner'];
        if (!empty($prize['item'])) {
          $알림 .= "\n" . $prize['item'];
        }
        foreach ((array)($prize['ranks'] ?? []) as $pr) {
          $pl = (int)($pr['place'] ?? 0);
          if ($pl < 1 || $pl > 5) {
            continue;
          }
          $line = $pl . '등 ' . (string)($pr['name'] ?? '');
          $eg = (int)($pr['eunchong_got'] ?? 0);
          if ($eg > 0) {
            $line .= ' 은총 +' . $eg;
          }
          $sg = (int)($pr['shard_got'] ?? 0);
          if ($sg > 0) {
            $line .= ' 은총조각 +' . $sg;
          }
          $알림 .= "\n" . $line;
        }
        if (burumable_비교((string)($prize['land'] ?? '0'), '1') >= 0) {
          $알림 .= "\n건물 매각 게임냥 " . burumable_만표시($prize['land']);
        }
        burumable_홍보알림($알림, 'burumable_end');
      }
      return;
    }
    if ((int)$state['turn'] >= 부루마블_최대턴) {
      $bestI = burumable_우승인덱스($state);
      if ($bestI < 0) {
        $bestI = 0;
      }
      $state['over'] = true;
      $state['end_kind'] = 'turn';
      $state['winner'] = (string)($state['players'][$bestI]['name'] ?? '');
      $state['pending'] = null;
      if (function_exists('burumable_땅이자정산')) {
        burumable_땅이자정산($state);
      }
      if (function_exists('burumable_등수정리')) {
        burumable_등수정리($state);
      }
      $prize = function_exists('burumable_우승시상') ? burumable_우승시상($state) : ['item' => '', 'land' => '0'];
      if (function_exists('burumable_발견이력초기화')) {
        burumable_발견이력초기화($state);
      }
      burumable_로그($state, '⏰ 턴 종료 · 자산 1위 ' . $state['winner'] . ' 우승!');
      if (trim((string)$state['winner']) !== '') {
        $알림 = "🎲 부루마블\n턴 종료 · 우승 " . $state['winner'];
        if (!empty($prize['item'])) {
          $알림 .= "\n" . $prize['item'];
        }
        foreach ((array)($prize['ranks'] ?? []) as $pr) {
          $pl = (int)($pr['place'] ?? 0);
          if ($pl < 1 || $pl > 5) {
            continue;
          }
          $line = $pl . '등 ' . (string)($pr['name'] ?? '');
          $eg = (int)($pr['eunchong_got'] ?? 0);
          if ($eg > 0) {
            $line .= ' 은총 +' . $eg;
          }
          $sg = (int)($pr['shard_got'] ?? 0);
          if ($sg > 0) {
            $line .= ' 은총조각 +' . $sg;
          }
          $알림 .= "\n" . $line;
        }
        if (burumable_비교((string)($prize['land'] ?? '0'), '1') >= 0) {
          $알림 .= "\n건물 매각 게임냥 " . burumable_만표시($prize['land']);
        }
        burumable_홍보알림($알림, 'burumable_end');
      }
    }
  }
}

if (!function_exists('burumable_홍보알림')) {
  /** 홍보방(info2) 알림 큐 */
  function burumable_홍보알림(string $msg, string $item = 'burumable'): void {
    $msg = trim($msg);
    if ($msg === '') {
      return;
    }
    if (function_exists('info2알림_등록')) {
      info2알림_등록($msg, $item !== '' ? $item : 'burumable');
    }
  }
}

if (!function_exists('burumable_본방알림')) {
  /** 본방(tb_lotto_info) 알림 큐 */
  function burumable_본방알림(string $msg, string $item = 'burumable'): void {
    $msg = trim($msg);
    if ($msg === '') {
      return;
    }
    if (function_exists('본방알림_등록')) {
      본방알림_등록($msg, $item !== '' ? $item : 'burumable');
    }
  }
}

if (!function_exists('burumable_시작본방알림')) {
  /** 새 판 시작 시 본방에 링크 포함 알림 */
  function burumable_시작본방알림(array $state): void {
    $link = defined('부루마블_페이지주소') ? 부루마블_페이지주소 : 'http://49.247.160.164/page/burumable.php';
    $grant = function_exists('burumable_지급시작금') ? burumable_지급시작금($state) : '0';
    $n = count($state['players'] ?? []);
    $first = function_exists('burumable_현재닉') ? burumable_현재닉($state) : '';
    $grantPct표시 = function_exists('burumable_퍼센트표시')
      ? burumable_퍼센트표시(burumable_지급퍼센트($state))
      : '10%';
    $money = defined('부루마블_머니이름') ? 부루마블_머니이름 : '부루마블 머니';
    $msg = "🎲 부루마블 시작!\n";
    if ($first !== '') {
      $msg .= $first . '부터 · ';
    }
    $msg .= "전원 스냅샷 {$grantPct표시}씩 {$money} " . burumable_만표시($grant) . ' 동일 지급';
    if ($n > 0) {
      $msg .= "\n{$n}명";
    }
    $msg .= "\n" . (function_exists('burumable_알람링크문구')
      ? burumable_알람링크문구()
      : $link);
    burumable_본방알림($msg, 'burumable_start');
  }
}

if (!function_exists('burumable_다음닉')) {
  /** 지금 차례 다음으로 주사위 돌릴 사람 (포기·파산 제외) */
  function burumable_다음닉(array $state): string {
    $players = $state['players'] ?? [];
    $n = count($players);
    if ($n < 2) {
      return '';
    }
    $cur = (int)($state['cur'] ?? 0);
    for ($k = 1; $k < $n; $k++) {
      $i = ($cur + $k) % $n;
      if (!empty($players[$i]['out'])) {
        continue;
      }
      $nm = trim((string)($players[$i]['name'] ?? ''));
      if ($nm === '') {
        continue;
      }
      return $nm;
    }
    return '';
  }
}

if (!function_exists('burumable_턴알림')) {
  /** 홍보방(info2) 알림 큐 — 누구 차례인지 */
  function burumable_턴알림(array $state): void {
    if (!empty($state['over'])) {
      return;
    }
    $nm = function_exists('burumable_현재닉') ? burumable_현재닉($state) : '';
    $nm = trim((string)$nm);
    if ($nm === '') {
      return;
    }
    $limit = burumable_턴제한초($state);
    $다음 = burumable_다음닉($state);
    $msg = "🎲 부루마블\n[ {$nm} ] 님 차례예요.";
    if ($다음 !== '') {
      $msg .= "\n다음 [ {$다음} ]";
    }
    $msg .= "\n" . burumable_초표시($limit) . " 안에 주사위를 돌려 주세요.";
    burumable_홍보알림($msg, 'burumable_' . $nm);
  }
}

if (!function_exists('burumable_턴이어가기')) {
  /** 더블이면 같은 사람이 한 번 더, 아니면 다음 턴 */
  function burumable_턴이어가기(array &$state): void {
    if (!empty($state['over'])) {
      $state['again'] = false;
      $state['doubles'] = 0;
      return;
    }
    if (!empty($state['pending'])) {
      return;
    }
    $pi = (int)($state['cur'] ?? 0);
    $nick = burumable_현재닉($state);
    $jail = !empty($state['players'][$pi]['jail']);
    $out = !empty($state['players'][$pi]['out']);
    if (!empty($state['again']) && $nick !== '' && !$jail && !$out) {
      $state['again'] = false;
      $state['acted'] = false;
      $state['turn_at'] = time();
      burumable_로그($state, '🎲 ' . $nick . ' 더블! 한 번 더');
      burumable_홍보알림("🎲 부루마블\n{$nick} 더블! 한 번 더 돌려 주세요.", 'burumable_double');
      return;
    }
    $state['again'] = false;
    $state['doubles'] = 0;
    burumable_다음($state);
  }
}

if (!function_exists('burumable_다음')) {
  function burumable_다음(array &$state): void {
    $state['again'] = false;
    $state['doubles'] = 0;
    $n = count($state['players']);
    if ($n < 1) {
      return;
    }
    for ($k = 0; $k < $n; $k++) {
      $state['cur'] = ((int)$state['cur'] + 1) % $n;
      if ((int)$state['cur'] === 0) {
        $state['turn'] = (int)$state['turn'] + 1;
        if (function_exists('burumable_양도땅값복구')) {
          burumable_양도땅값복구($state);
        }
        if (function_exists('burumable_땅이자지급')) {
          burumable_땅이자지급($state);
        }
      }
      if (empty($state['players'][$state['cur']]['out'])) {
        $state['turn_at'] = time();
        $state['acted'] = false;
        burumable_턴알림($state);
        return;
      }
    }
  }
}

if (!function_exists('burumable_초표시')) {
  function burumable_초표시(int $sec): string {
    $sec = max(0, $sec);
    if ($sec >= 60 && $sec % 60 === 0) {
      return ((int)($sec / 60)) . '분';
    }
    if ($sec >= 60) {
      return intdiv($sec, 60) . '분 ' . ($sec % 60) . '초';
    }
    return $sec . '초';
  }
}

if (!function_exists('burumable_턴제한초')) {
  function burumable_턴제한초(?array $state = null): int {
    $max = defined('부루마블_턴제한초') ? max(10, (int)부루마블_턴제한초) : 60;
    $min = defined('부루마블_턴최소초') ? max(1, (int)부루마블_턴최소초) : 10;
    if ($min > $max) {
      $min = $max;
    }
    if (!is_array($state)) {
      return $max;
    }
    $pi = (int)($state['cur'] ?? 0);
    if (isset($state['players'][$pi]['turn_sec'])) {
      return max($min, min($max, (int)$state['players'][$pi]['turn_sec']));
    }
    return $max;
  }
}

if (!function_exists('burumable_개인턴제한맞추기')) {
  /** 사람별 턴초. ver2: 공용 10초 잔재 전원 60초로 한 번 초기화 */
  function burumable_개인턴제한맞추기(array &$state): bool {
    $max = defined('부루마블_턴제한초') ? (int)부루마블_턴제한초 : 60;
    $ver = 2;
    $changed = false;
    if (!isset($state['players']) || !is_array($state['players'])) {
      return false;
    }
    if ((int)($state['turn_clock_ver'] ?? 0) < $ver) {
      foreach ($state['players'] as $i => $p) {
        if (!is_array($p)) {
          continue;
        }
        $state['players'][$i]['turn_sec'] = $max;
      }
      unset($state['turn_sec']);
      $state['turn_at'] = time();
      $state['acted'] = false;
      $state['turn_clock_ver'] = $ver;
      burumable_로그($state, '⏱️ 전원 턴 ' . $max . '초로 초기화');
      return true;
    }
    foreach ($state['players'] as $i => $p) {
      if (!is_array($p) || isset($p['turn_sec'])) {
        continue;
      }
      $state['players'][$i]['turn_sec'] = $max;
      $changed = true;
    }
    return $changed;
  }
}

if (!function_exists('burumable_턴제한리셋')) {
  function burumable_턴제한리셋(array &$state): void {
    $max = defined('부루마블_턴제한초') ? (int)부루마블_턴제한초 : 60;
    $pi = (int)($state['cur'] ?? 0);
    if (isset($state['players'][$pi]) && is_array($state['players'][$pi])) {
      $state['players'][$pi]['turn_sec'] = $max;
    }
    $state['turn_at'] = time();
  }
}

if (!function_exists('burumable_턴제한줄이기')) {
  /** 주사위 안 돌리고 시간 초과 → 그 사람 다음 턴만 -10초 (최소 10초) */
  function burumable_턴제한줄이기(array &$state): int {
    $min = defined('부루마블_턴최소초') ? max(1, (int)부루마블_턴최소초) : 10;
    $step = defined('부루마블_턴감초') ? max(1, (int)부루마블_턴감초) : 10;
    $pi = (int)($state['cur'] ?? 0);
    $next = max($min, burumable_턴제한초($state) - $step);
    if (isset($state['players'][$pi]) && is_array($state['players'][$pi])) {
      $state['players'][$pi]['turn_sec'] = $next;
    }
    return $next;
  }
}

if (!function_exists('burumable_구매제한초')) {
  function burumable_구매제한초(): int {
    return defined('부루마블_구매제한초') ? max(5, (int)부루마블_구매제한초) : 20;
  }
}

if (!function_exists('burumable_건설제한초')) {
  function burumable_건설제한초(): int {
    return defined('부루마블_건설제한초') ? max(5, (int)부루마블_건설제한초) : 30;
  }
}

if (!function_exists('burumable_찬스제한초')) {
  function burumable_찬스제한초(): int {
    return defined('부루마블_찬스제한초') ? max(5, (int)부루마블_찬스제한초) : 30;
  }
}

if (!function_exists('burumable_인수제한초')) {
  function burumable_인수제한초(): int {
    return defined('부루마블_인수제한초') ? max(5, (int)부루마블_인수제한초) : 20;
  }
}

if (!function_exists('burumable_여행제한초')) {
  function burumable_여행제한초(): int {
    return defined('부루마블_여행제한초') ? max(10, (int)부루마블_여행제한초) : 40;
  }
}

if (!function_exists('burumable_대기종류인가')) {
  function burumable_대기종류인가($kind): bool {
    return in_array((string)$kind, ['buy', 'build', 'chance', 'travel', 'forcebuy', 'takeover', 'takeover_offer', 'distress'], true);
  }
}

if (!function_exists('burumable_대기제한초')) {
  function burumable_대기제한초(?array $state = null): int {
    $pend = is_array($state) ? ($state['pending'] ?? null) : null;
    $kind = is_array($pend) ? (string)($pend['kind'] ?? '') : '';
    if ($kind === 'build') {
      return burumable_건설제한초();
    }
    if ($kind === 'chance') {
      return burumable_찬스제한초();
    }
    if ($kind === 'travel' || $kind === 'forcebuy') {
      return burumable_여행제한초();
    }
    if ($kind === 'takeover' || $kind === 'takeover_offer') {
      return burumable_인수제한초();
    }
    if ($kind === 'distress') {
      return burumable_파산제한초();
    }
    return burumable_구매제한초();
  }
}

if (!function_exists('burumable_구매남은초')) {
  function burumable_구매남은초(array $state): int {
    if (!empty($state['paused'])) {
      return max(0, (int)($state['pause_buy_left'] ?? 0));
    }
    $pend = $state['pending'] ?? null;
    if (!is_array($pend) || !burumable_대기종류인가($pend['kind'] ?? '')) {
      return 0;
    }
    $limit = burumable_대기제한초($state);
    $at = (int)($pend['at'] ?? 0);
    if ($at < 1) {
      return $limit;
    }
    return max(0, $limit - (time() - $at));
  }
}

if (!function_exists('burumable_턴남은초')) {
  function burumable_턴남은초(array $state): int {
    if (!empty($state['over'])) {
      return 0;
    }
    if (!empty($state['paused'])) {
      return max(0, (int)($state['pause_turn_left'] ?? 0));
    }
    $limit = burumable_턴제한초($state);
    $at = (int)($state['turn_at'] ?? 0);
    if ($at < 1) {
      return $limit;
    }
    return max(0, $limit - (time() - $at));
  }
}

if (!function_exists('burumable_미굴림파산회')) {
  function burumable_미굴림파산회(): int {
    return defined('부루마블_미굴림_파산회') ? max(2, (int)부루마블_미굴림_파산회) : 5;
  }
}

if (!function_exists('burumable_미굴림퍼센트')) {
  /** 미굴림 횟수별 머니 차감 %. 파산 회차는 0 */
  function burumable_미굴림퍼센트(int $miss): int {
    if ($miss < 1 || $miss >= burumable_미굴림파산회()) {
      return 0;
    }
    if ($miss === 1) {
      return 10;
    }
    if ($miss === 2) {
      return 30;
    }
    return 50;
  }
}

if (!function_exists('burumable_미굴림안내')) {
  function burumable_미굴림안내(): string {
    return '1회 머니 10% · 2회 30% · 3회·4회 50% · 5회 강제 파산';
  }
}

if (!function_exists('burumable_미굴림벌')) {
  /**
   * 미굴림 1회: 머니 10% · 2회: 30% · 3~4회: 50% · 5회: 강제 파산
   * @return array{broke:bool,note:string}
   */
  function burumable_미굴림벌(array &$state, int $pi): array {
    if (!isset($state['players'][$pi]) || !is_array($state['players'][$pi])) {
      return ['broke' => false, 'note' => ''];
    }
    if (!empty($state['players'][$pi]['out'])) {
      return ['broke' => false, 'note' => ''];
    }
    $name = trim((string)($state['players'][$pi]['name'] ?? ''));
    $miss = max(0, (int)($state['players'][$pi]['skip_roll'] ?? 0));
    if ($name === '' || $miss < 1) {
      return ['broke' => false, 'note' => ''];
    }
    $limit = burumable_미굴림파산회();
    if ($miss >= $limit) {
      burumable_파산($state, $name, '미굴림');
      return ['broke' => true, 'note' => '미굴림 ' . $limit . '회 · 파산'];
    }
    $pct = burumable_미굴림퍼센트($miss);
    $have = burumable_포인트양수($name);
    $cut = burumable_몫($have, $pct, 100);
    $note = '미굴림 ' . $miss . '회 · 머니 ' . $pct . '% 차감';
    if (burumable_비교($cut, '1') >= 0) {
      burumable_출금($name, $cut, true);
      burumable_현금맞춤($state, $pi);
      if (function_exists('지급로그')) {
        지급로그('부루마블-미굴림', $name, $name, 0, $cut);
      }
      $note .= ' ' . burumable_만표시($cut);
    } else {
      $note .= ' · 차감할 머니 없음';
    }
    burumable_홍보알림("🎲 부루마블\n⏰ {$name} {$note}", 'burumable_skipcut');
    return ['broke' => false, 'note' => $note];
  }
}

if (!function_exists('burumable_미굴림처리')) {
  /** 미굴림 1회 증가 후 벌 적용 · 다음 사람. $how=시간초과|포기 */
  function burumable_미굴림처리(array &$state, int $pi, string $how = '시간초과'): array {
    if (!isset($state['players'][$pi]) || !is_array($state['players'][$pi])) {
      return ['ok' => false, 'broke' => false, 'msg' => '참가자가 아니에요.'];
    }
    if (!empty($state['players'][$pi]['out'])) {
      return ['ok' => false, 'broke' => false, 'msg' => '이미 빠졌어요.'];
    }
    $name = trim((string)($state['players'][$pi]['name'] ?? ''));
    if ($name === '') {
      return ['ok' => false, 'broke' => false, 'msg' => '참가자가 아니에요.'];
    }
    $state['players'][$pi]['skip_roll'] = max(0, (int)($state['players'][$pi]['skip_roll'] ?? 0)) + 1;
    $miss = max(0, (int)$state['players'][$pi]['skip_roll']);
    $old = burumable_턴제한초($state);
    $new = burumable_턴제한줄이기($state);
    $벌 = function_exists('burumable_미굴림벌') ? burumable_미굴림벌($state, $pi) : ['broke' => false, 'note' => ''];
    $howTxt = ($how === '포기') ? '주사위 굴림 포기' : (burumable_초표시($old) . ' 초과');
    $log = '⏰ ' . $name . ' ' . $howTxt . ' · 미굴림 ' . $miss . '회';
    $note = trim((string)($벌['note'] ?? ''));
    if ($note !== '' && empty($벌['broke'])) {
      $log .= ' · ' . $note;
    }
    if (empty($벌['broke'])) {
      $log .= ' · 다음 턴 ' . burumable_초표시($new);
    }
    burumable_로그($state, $log);
    if ($how === '포기') {
      burumable_홍보알림("🎲 부루마블\n⏭ {$name} 주사위 굴림 포기\n미굴림 {$miss}회", 'burumable_skiproll');
    }
    if (!empty($벌['broke'])) {
      return ['ok' => true, 'broke' => true, 'miss' => $miss, 'msg' => '미굴림 ' . burumable_미굴림파산회() . '회 · 파산했어요.'];
    }
    $state['acted'] = false;
    $state['again'] = false;
    burumable_다음($state);
    burumable_종료확인($state);
    $msg = '주사위를 포기했어요. 미굴림 ' . $miss . '회';
    if ($note !== '') {
      $msg .= ' · ' . $note;
    }
    return ['ok' => true, 'broke' => false, 'miss' => $miss, 'msg' => $msg];
  }
}

if (!function_exists('burumable_턴만료처리')) {
  /** 주사위 시간 초과 시 그 사람 다음 턴 -10초 · 땅 구매/건설 대기 초과 시 패스 · 찬스는 무작위 카드. 변경되면 true */
  function burumable_턴만료처리(array &$state, string $actingNick = ''): bool {
    if (!empty($state['over']) || !empty($state['paused']) || !empty($state['recruiting'])) {
      return false;
    }
    $pend = $state['pending'] ?? null;
    $waiting = is_array($pend) && burumable_대기종류인가($pend['kind'] ?? '');
    $waitNick = $waiting ? trim((string)($pend['wait'] ?? burumable_현재닉($state))) : burumable_현재닉($state);
    if ($waitNick === '') {
      $waitNick = burumable_현재닉($state);
    }
    $self = ($actingNick !== '' && $waitNick === $actingNick);
    if ($waiting) {
      if ((int)($pend['at'] ?? 0) < 1) {
        $state['pending']['at'] = time();
        return true;
      }
      if (burumable_구매남은초($state) > 0) {
        return false;
      }
      if ($self) {
        return false;
      }
      $name = $waitNick !== '' ? $waitNick : burumable_현재닉($state);
      $kind = (string)($pend['kind'] ?? 'buy');
      $limit = burumable_대기제한초($state);
      if ($kind === 'chance') {
        $cards = is_array($pend['cards'] ?? null) ? $pend['cards'] : [];
        $pickId = '';
        if ($cards !== []) {
          $row = $cards[random_int(0, count($cards) - 1)];
          $pickId = (string)($row['pick'] ?? $row['id'] ?? '');
        }
        burumable_로그($state, '⏰ ' . $name . ' ' . burumable_초표시($limit) . ' 초과 · 찬스 무작위');
        if ($pickId !== '' && function_exists('burumable_찬스고르기')) {
          burumable_찬스고르기($state, $name, $pickId, true);
        } else {
          $state['pending'] = null;
          burumable_턴이어가기($state);
          burumable_종료확인($state);
        }
        return true;
      }
      if ($kind === 'travel') {
        $pick = -1;
        $lands = [];
        foreach (burumable_보드() as $t) {
          if ((string)($t['type'] ?? '') === 'land') {
            $lands[] = (int)$t['id'];
          }
        }
        if ($lands !== []) {
          $pick = $lands[random_int(0, count($lands) - 1)];
        }
        burumable_로그($state, '⏰ ' . $name . ' ' . burumable_초표시($limit) . ' 초과 · 세계여행 무작위');
        if ($pick >= 0 && function_exists('burumable_세계여행고르기')) {
          burumable_세계여행고르기($state, $name, $pick, true);
        } else {
          $state['pending'] = null;
          burumable_턴이어가기($state);
          burumable_종료확인($state);
        }
        return true;
      }
      if ($kind === 'forcebuy') {
        burumable_로그($state, '⏰ ' . $name . ' ' . burumable_초표시($limit) . ' 초과 · 강제구매권으로 보관');
        if (function_exists('burumable_강제구매고르기')) {
          burumable_강제구매고르기($state, $name, -1, true);
        } else {
          $state['pending'] = null;
          burumable_턴이어가기($state);
          burumable_종료확인($state);
        }
        return true;
      }
      if ($kind === 'takeover' || $kind === 'takeover_offer') {
        burumable_로그($state, '⏰ ' . $name . ' ' . burumable_초표시($limit) . ' 초과 · ' . ($kind === 'takeover_offer' ? '거절·통행료' : '통행료'));
        if (function_exists('burumable_인수실행')) {
          burumable_인수실행($state, $name, false, true);
        } else {
          $state['pending'] = null;
          burumable_턴이어가기($state);
          burumable_종료확인($state);
        }
        return true;
      }
      if ($kind === 'distress') {
        burumable_로그($state, '⏰ ' . $name . ' ' . burumable_초표시($limit) . ' 초과 · 전체 파산');
        if (function_exists('burumable_파산')) {
          burumable_파산($state, $name, '시간초과');
        } else {
          $state['pending'] = null;
          burumable_턴이어가기($state);
          burumable_종료확인($state);
        }
        return true;
      }
      $state['pending'] = null;
      burumable_로그($state, '⏰ ' . $name . ' ' . burumable_초표시($limit) . ' 초과 · ' . ($kind === 'build' ? '건설 패스' : '땅 패스'));
      burumable_턴이어가기($state);
      burumable_종료확인($state);
      return true;
    }
    $at = (int)($state['turn_at'] ?? 0);
    if ($at < 1) {
      $state['turn_at'] = time();
      return true;
    }
    if (burumable_턴남은초($state) > 0) {
      return false;
    }
    if (!empty($state['acted'])) {
      return false;
    }
    if ($self) {
      return false;
    }
    $pi = (int)($state['cur'] ?? 0);
    if (function_exists('burumable_미굴림처리')) {
      burumable_미굴림처리($state, $pi, '시간초과');
    }
    return true;
  }
}

if (!function_exists('burumable_주사위포기')) {
  /** 내 차례에 주사위를 안 굴리고 넘김. 미굴림 패널티와 동일 */
  function burumable_주사위포기(array &$state, string $nick): array {
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    if (!empty($state['paused'])) {
      return ['ok' => false, 'msg' => '지금은 일시멈춤이에요.'];
    }
    if (!empty($state['recruiting'])) {
      return ['ok' => false, 'msg' => '지금은 참가 모집 중이에요.'];
    }
    if (!empty($state['pending'])) {
      return ['ok' => false, 'msg' => '지금은 주사위 차례가 아니에요.'];
    }
    if (!burumable_내턴인가($state, $nick)) {
      return ['ok' => false, 'msg' => '지금은 ' . burumable_현재닉($state) . ' 님 턴이에요.'];
    }
    $pi = (int)$state['cur'];
    if (!empty($state['players'][$pi]['out'])) {
      return ['ok' => false, 'msg' => '이미 파산했어요.'];
    }
    if (!function_exists('burumable_미굴림처리')) {
      return ['ok' => false, 'msg' => '굴림 포기 기능을 불러올 수 없어요.'];
    }
    return burumable_미굴림처리($state, $pi, '포기');
  }
}

if (!function_exists('burumable_통행료수령기록')) {
  function burumable_통행료수령기록(array &$state, string $owner, string $from, $amt, string $city = ''): void {
    $owner = trim($owner);
    $from = trim($from);
    $amt = burumable_냥($amt);
    $city = trim($city);
    if ($owner === '' || $from === '' || $owner === $from || burumable_비교($amt, '1') < 0) {
      return;
    }
    if (!isset($state['rent_inbox']) || !is_array($state['rent_inbox'])) {
      $state['rent_inbox'] = [];
    }
    if (!isset($state['rent_inbox'][$owner]) || !is_array($state['rent_inbox'][$owner])) {
      $state['rent_inbox'][$owner] = [];
    }
    $state['rent_inbox'][$owner][] = [
      'from' => $from,
      'amt' => $amt,
      'city' => $city,
      'at' => time(),
    ];
    if (count($state['rent_inbox'][$owner]) > 80) {
      $state['rent_inbox'][$owner] = array_slice($state['rent_inbox'][$owner], -80);
    }
  }
}

if (!function_exists('burumable_통행료수령공개')) {
  /**
   * @return list<array{name:string,times:int,total:string,total_fmt:string,last:string}>
   */
  function burumable_통행료수령공개(array $state, string $nick): array {
    $nick = trim($nick);
    $rows = ($nick !== '' && isset($state['rent_inbox'][$nick]) && is_array($state['rent_inbox'][$nick]))
      ? $state['rent_inbox'][$nick]
      : [];
    $by = [];
    foreach ($rows as $r) {
      if (!is_array($r)) {
        continue;
      }
      $from = trim((string)($r['from'] ?? ''));
      if ($from === '') {
        continue;
      }
      if (!isset($by[$from])) {
        $by[$from] = ['name' => $from, 'times' => 0, 'total' => '0', 'last' => ''];
      }
      $by[$from]['times']++;
      $by[$from]['total'] = burumable_더하기($by[$from]['total'], burumable_냥($r['amt'] ?? 0));
      $city = trim((string)($r['city'] ?? ''));
      if ($city !== '') {
        $by[$from]['last'] = $city;
      }
    }
    $out = [];
    foreach ($by as $row) {
      $row['total_fmt'] = burumable_만표시($row['total']);
      $out[] = $row;
    }
    usort($out, static function ($a, $b) {
      $c = burumable_비교($b['total'] ?? '0', $a['total'] ?? '0');
      if ($c !== 0) {
        return $c;
      }
      return ((int)($b['times'] ?? 0)) <=> ((int)($a['times'] ?? 0));
    });
    return $out;
  }
}

if (!function_exists('burumable_지불')) {
  /**
   * 부루마블 머니 차감. 본방냥 자동 스왑 없음.
   * 강제(통행료·세금·병원): 부족하면 땅 매도 레이어. 자산 없거나 시간 초과면 파산.
   * 선택(구매·건설): 부족하면 false.
   */
  function burumable_지불(array &$state, int $from, $amt, int $to = -1, string $why = '', bool $force = true): bool {
    $amt = burumable_냥($amt);
    if (burumable_비교($amt, '1') < 0) {
      return true;
    }
    $name = (string)$state['players'][$from]['name'];
    $have = burumable_포인트양수($name);
    if (burumable_비교($have, $amt) >= 0) {
      if (!burumable_출금($name, $amt, false)) {
        burumable_현금맞춤($state, $from);
        return false;
      }
      if ($to >= 0 && empty($state['players'][$to]['out'])) {
        $toName = (string)$state['players'][$to]['name'];
        burumable_입금($toName, $amt);
        burumable_현금맞춤($state, $to);
        if ($why === '임대' && function_exists('burumable_통행료수령기록')) {
          $city = '';
          $pos = (int)($state['players'][$from]['pos'] ?? -1);
          $board = burumable_보드();
          if (isset($board[$pos])) {
            $city = (string)($board[$pos]['name'] ?? '');
          }
          burumable_통행료수령기록($state, $toName, $name, $amt, $city);
        }
      }
      if (function_exists('지급로그')) {
        지급로그('부루마블-' . ($why !== '' ? $why : '지불'), $name, $to >= 0 ? (string)$state['players'][$to]['name'] : $name, 0, $amt);
      }
      burumable_현금맞춤($state, $from);
      return true;
    }
    if (!$force) {
      burumable_현금맞춤($state, $from);
      return false;
    }
    if (function_exists('burumable_빚부족처리')) {
      burumable_빚부족처리($state, $from, $amt, $to, $why);
      return empty($state['pending']);
    }
    burumable_현금맞춤($state, $from);
    return false;
  }
}

if (!function_exists('burumable_파산제한초')) {
  function burumable_파산제한초(): int {
    return defined('부루마블_파산제한초') ? max(5, (int)부루마블_파산제한초) : 30;
  }
}

if (!function_exists('burumable_빚이름')) {
  function burumable_빚이름(string $why): string {
    if ($why === '임대') {
      return '통행료';
    }
    if ($why === '세금') {
      return '세금';
    }
    if ($why === '병원비') {
      return '병원비';
    }
    if ($why === '과속벌금') {
      return '과속 벌금';
    }
    return $why !== '' ? $why : '빚';
  }
}

if (!function_exists('burumable_빚자산목록')) {
  /** @return list<array<string,mixed>> */
  function burumable_빚자산목록(array $state, string $nick): array {
    $rows = [];
    $nick = trim($nick);
    if ($nick === '') {
      return $rows;
    }
    $board = burumable_보드();
    if (!empty($state['owner']) && is_array($state['owner'])) {
      foreach ($state['owner'] as $tid => $on) {
        if ((string)$on !== $nick) {
          continue;
        }
        $tid = (int)$tid;
        $raw = $board[$tid] ?? null;
        if (!is_array($raw) || (string)($raw['type'] ?? '') !== 'land') {
          continue;
        }
        $tile = burumable_칸시세($state, $raw);
        $gross = function_exists('burumable_칸부동산') ? burumable_칸부동산($state, $tid) : burumable_냥($tile['price'] ?? 0);
        $fee = function_exists('burumable_매각수수료액') ? burumable_매각수수료액($gross) : ['net' => $gross];
        $lv = burumable_건물단계($state, $tid);
        $rows[] = [
          'id' => $tid,
          'type' => 'land',
          'name' => (string)($tile['name'] ?? ''),
          'flag' => burumable_국기((string)($tile['name'] ?? '')),
          'build' => $lv,
          'build_label' => burumable_건물이름($lv),
          'gross' => $gross,
          'gross_fmt' => burumable_만표시($gross),
          'net' => burumable_냥($fee['net'] ?? 0),
          'net_fmt' => burumable_만표시($fee['net'] ?? 0),
        ];
      }
    }
    $pi = burumable_닉찾기($state, $nick);
    $int = $pi >= 0 ? burumable_누적이자($state, $pi) : '0';
    if (burumable_비교($int, '1') >= 0) {
      $rows[] = [
        'id' => 'interest',
        'type' => 'interest',
        'name' => '누적 이자',
        'flag' => '',
        'build' => 0,
        'build_label' => '',
        'gross' => $int,
        'gross_fmt' => burumable_만표시($int),
        'net' => $int,
        'net_fmt' => burumable_만표시($int),
      ];
    }
    return $rows;
  }
}

if (!function_exists('burumable_빚레이어열기')) {
  function burumable_빚레이어열기(array &$state, int $from, $amt, int $to, string $why): void {
    $name = (string)($state['players'][$from]['name'] ?? '');
    $toName = ($to >= 0 && isset($state['players'][$to])) ? (string)$state['players'][$to]['name'] : '';
    $pos = (int)($state['players'][$from]['pos'] ?? 0);
    $label = burumable_빚이름($why);
    $state['pending'] = [
      'kind' => 'distress',
      'why' => $why,
      'why_label' => $label,
      'amt' => burumable_냥($amt),
      'to' => $to,
      'to_name' => $toName,
      'from' => $name,
      'wait' => $name,
      'tile' => $pos,
      'at' => time(),
    ];
    burumable_로그($state, '🆘 ' . $name . ' ' . $label . ' ' . burumable_만표시($amt) . ' 부족 · 땅 매도 후 내기 ' . burumable_초표시(burumable_파산제한초()));
    burumable_홍보알림(
      "🎲 부루마블\n🆘 {$name} {$label} " . burumable_만표시($amt) . " 부족\n" . burumable_초표시(burumable_파산제한초()) . ' 안에 땅을 팔고 내 주세요',
      'burumable_distress'
    );
  }
}

if (!function_exists('burumable_빚부족처리')) {
  function burumable_빚부족처리(array &$state, int $from, $amt, int $to = -1, string $why = ''): void {
    $name = (string)($state['players'][$from]['name'] ?? '');
    if ($name === '' || !empty($state['players'][$from]['out'])) {
      return;
    }
    $amt = burumable_냥($amt);
    $assets = burumable_빚자산목록($state, $name);
    $have = burumable_포인트양수($name);
    if ($assets === [] && burumable_비교($have, '1') < 0) {
      burumable_파산($state, $name, '자산없음', $to, $amt);
      return;
    }
    burumable_빚레이어열기($state, $from, $amt, $to, $why);
  }
}

if (!function_exists('burumable_빚청산시도')) {
  /** 현금이 빚 이상이면 납부 후 턴 진행. 모자라면 레이어를 유지하고, 내기 버튼으로 잔액 납부. */
  function burumable_빚청산시도(array &$state): bool {
    $pend = $state['pending'] ?? null;
    if (!is_array($pend) || (string)($pend['kind'] ?? '') !== 'distress') {
      return false;
    }
    $name = trim((string)($pend['from'] ?? burumable_현재닉($state)));
    $pi = burumable_닉찾기($state, $name);
    if ($pi < 0) {
      $state['pending'] = null;
      return true;
    }
    $amt = burumable_냥($pend['amt'] ?? 0);
    $to = (int)($pend['to'] ?? -1);
    $why = (string)($pend['why'] ?? '지불');
    $have = burumable_포인트양수($name);
    if (burumable_비교($have, $amt) >= 0) {
      $state['pending'] = null;
      $ok = burumable_지불($state, $pi, $amt, $to, $why, false);
      if (!$ok) {
        $state['pending'] = $pend;
        return false;
      }
      burumable_로그($state, '✅ ' . $name . ' ' . burumable_빚이름($why) . ' ' . burumable_만표시($amt) . ' 납부');
      burumable_턴이어가기($state);
      burumable_종료확인($state);
      return true;
    }
    return false;
  }
}

if (!function_exists('burumable_빚납부')) {
  /** 빚 레이어에서 통행료·세금 내기. 모자라고 팔 땅이 없으면 잔액 납부 후 파산. */
  function burumable_빚납부(array &$state, string $nick): array {
    $pend = $state['pending'] ?? null;
    if (!is_array($pend) || (string)($pend['kind'] ?? '') !== 'distress') {
      return ['ok' => false, 'msg' => '낼 빚이 없어요.'];
    }
    $from = trim((string)($pend['from'] ?? $pend['wait'] ?? ''));
    if ($from === '' || $from !== $nick) {
      return ['ok' => false, 'msg' => '빚을 내는 사람만 납부할 수 있어요.'];
    }
    $amt = burumable_냥($pend['amt'] ?? 0);
    $have = burumable_포인트양수($from);
    $why = (string)($pend['why'] ?? '지불');
    $label = burumable_빚이름($why);
    if (burumable_비교($have, $amt) >= 0) {
      if (!burumable_빚청산시도($state)) {
        return ['ok' => false, 'msg' => $label . '을 내지 못했어요.'];
      }
      return ['ok' => true, 'msg' => $label . ' ' . burumable_만표시($amt) . '을 냈어요.'];
    }
    if (burumable_빚자산목록($state, $from) !== []) {
      $short = burumable_빼기($amt, $have);
      return ['ok' => false, 'msg' => '아직 ' . burumable_만표시($short) . '이 부족해요. 땅이나 이자를 더 팔아 주세요.'];
    }
    burumable_파산($state, $from, '잔액납부');
    return ['ok' => true, 'msg' => '남은 머니로 ' . $label . '를 내고 파산했어요.'];
  }
}

if (!function_exists('burumable_파산')) {
  /** 전체 파산 · 땅 빈땅 · 환급 없음 · 이번 판 참가 불가. 통행료 빚이면 남은 머니는 땅 주인에게 */
  function burumable_파산(array &$state, string $nick, string $reason = '', int $creditor = -1, $debt = '0'): array {
    $nick = trim($nick);
    $pi = burumable_닉찾기($state, $nick);
    if ($pi < 0) {
      return ['ok' => false, 'msg' => '참가자가 아니에요.'];
    }
    if (!empty($state['players'][$pi]['out'])) {
      $state['pending'] = null;
      return ['ok' => true, 'msg' => '이미 빠졌어요.'];
    }
    if (function_exists('burumable_결승확정')) {
      burumable_결승확정($state);
    }
    $pend = is_array($state['pending'] ?? null) ? $state['pending'] : null;
    $amt = burumable_냥($debt);
    $to = $creditor;
    $why = '';
    if (is_array($pend) && (string)($pend['kind'] ?? '') === 'distress' && (string)($pend['from'] ?? '') === $nick) {
      $amt = burumable_냥($pend['amt'] ?? $amt);
      $to = (int)($pend['to'] ?? $to);
      $why = (string)($pend['why'] ?? '');
    }
    $int = burumable_누적이자($state, $pi);
    if (burumable_비교($int, '1') >= 0) {
      burumable_입금($nick, $int);
      $state['players'][$pi]['interest'] = '0';
    }
    $have = burumable_포인트양수($nick);
    $pay = $have;
    if (burumable_비교($amt, '1') >= 0 && burumable_비교($have, $amt) > 0) {
      $pay = $amt;
    }
    $toName = '';
    if ($to >= 0 && empty($state['players'][$to]['out'])) {
      $toName = trim((string)($state['players'][$to]['name'] ?? ''));
    }
    if (burumable_비교($pay, '1') >= 0) {
      burumable_출금($nick, $pay, true);
      if ($toName !== '') {
        burumable_입금($toName, $pay);
        burumable_현금맞춤($state, $to);
        if (function_exists('지급로그')) {
          지급로그('부루마블-' . (($why !== '') ? $why : '파산잔액'), $nick, $toName, 0, $pay);
        }
        if ($why === '임대' && function_exists('burumable_통행료수령기록')) {
          $city = '';
          $pos = (int)($state['players'][$pi]['pos'] ?? -1);
          $board = burumable_보드();
          if (isset($board[$pos])) {
            $city = (string)($board[$pos]['name'] ?? '');
          }
          burumable_통행료수령기록($state, $toName, $nick, $pay, $city);
        }
      }
    }
    burumable_잔액설정($nick, '0');
    burumable_현금맞춤($state, $pi);
    foreach ($state['owner'] as $tid => $on) {
      if ((string)$on === $nick) {
        unset($state['owner'][$tid], $state['build'][(string)$tid], $state['build'][$tid]);
        unset($state['land_keep'][(string)$tid], $state['land_keep'][$tid]);
        unset($state['land_keep_at'][(string)$tid], $state['land_keep_at'][$tid]);
        if (function_exists('burumable_인수거절리셋')) {
          burumable_인수거절리셋($state, (int)$tid);
        }
      }
    }
    $state['players'][$pi]['out'] = true;
    $state['players'][$pi]['broke'] = true;
    $state['players'][$pi]['interest'] = '0';
    $state['pending'] = null;
    if (function_exists('burumable_결승탈락기록')) {
      burumable_결승탈락기록($state, $nick, '파산');
    }
    $label = burumable_빚이름($why);
    $whyTxt = $reason === '시간초과' ? '시간 초과'
      : ($reason === '자산없음' ? '팔 땅이 없음'
      : ($reason === '잔액납부' ? '잔액 납부'
      : ($reason === '강제' ? '강제 파산'
      : ($reason === '미굴림' ? ('미굴림 ' . (function_exists('burumable_미굴림파산회') ? burumable_미굴림파산회() : 5) . '회')
      : ($reason === '선택' ? '파산 선택' : '파산')))));
    $로그 = '💀 ' . $nick . ' 파산 · ' . $whyTxt . ' · 이번 판 참가 불가';
    if ($label !== '빚' && burumable_비교($amt, '1') >= 0) {
      $로그 .= ' · ' . $label . ' ' . burumable_만표시($amt);
    }
    if ($toName !== '' && burumable_비교($pay, '1') >= 0) {
      $로그 .= ' · ' . $toName . '에게 잔액 ' . burumable_만표시($pay);
    }
    burumable_로그($state, $로그);
    $알림 = "🎲 부루마블\n💀 {$nick} 파산\n{$whyTxt} · 이번 판 참가 불가";
    if ($toName !== '' && burumable_비교($pay, '1') >= 0) {
      $알림 .= "\n{$toName}에게 잔액 " . burumable_만표시($pay);
    }
    burumable_홍보알림($알림, 'burumable_broke');
    if (burumable_현재닉($state) === $nick) {
      burumable_턴이어가기($state);
    }
    burumable_종료확인($state);
    return ['ok' => true, 'msg' => '파산했어요. 이번 판은 참가할 수 없어요.'];
  }
}

if (!function_exists('burumable_파산실행')) {
  function burumable_파산실행(array &$state, string $nick): array {
    $pend = $state['pending'] ?? null;
    if (!is_array($pend) || (string)($pend['kind'] ?? '') !== 'distress') {
      return ['ok' => false, 'msg' => '지금은 파산할 상황이 아니에요.'];
    }
    $wait = trim((string)($pend['wait'] ?? $pend['from'] ?? ''));
    if ($wait !== '' && $wait !== $nick) {
      return ['ok' => false, 'msg' => '지금은 ' . $wait . ' 님 차례예요.'];
    }
    return burumable_파산($state, $nick, '선택');
  }
}

if (!function_exists('burumable_강제대상대기닉')) {
  function burumable_강제대상대기닉(array $state): string {
    $wait = burumable_현재닉($state);
    $pend = $state['pending'] ?? null;
    if (is_array($pend) && function_exists('burumable_대기종류인가') && burumable_대기종류인가($pend['kind'] ?? '')) {
      $w = trim((string)($pend['wait'] ?? ''));
      if ($w !== '') {
        $wait = $w;
      }
    }
    return trim($wait);
  }
}

if (!function_exists('burumable_강제파산')) {
  function burumable_강제파산(array &$state, string $admin, string $target): array {
    if (!burumable_민호인가($admin)) {
      return ['ok' => false, 'msg' => '민호만 할 수 있어요.'];
    }
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    $target = trim($target);
    if ($target === '') {
      return ['ok' => false, 'msg' => '대상을 고르세요.'];
    }
    $pi = burumable_닉찾기($state, $target);
    if ($pi < 0) {
      return ['ok' => false, 'msg' => '참가자가 아니에요.'];
    }
    if (!empty($state['players'][$pi]['out'])) {
      return ['ok' => false, 'msg' => '이미 빠진 친구예요.'];
    }
    $r = burumable_파산($state, $target, '강제');
    if (!empty($r['ok'])) {
      $pi = burumable_닉찾기($state, $target);
      if ($pi >= 0) {
        $state['players'][$pi]['force_broke'] = true;
        $state['players'][$pi]['broke'] = true;
      }
      $r['msg'] = $target . ' 님을 강제 파산했어요.';
    }
    return $r;
  }
}

if (!function_exists('burumable_파산복구')) {
  /** 파산 참가 복귀 · 땅은 이미 빈땅 · 머니가 없으면 시작금 */
  function burumable_파산복구(array &$state, string $nick): array {
    $nick = trim($nick);
    $pi = burumable_닉찾기($state, $nick);
    if ($pi < 0) {
      return ['ok' => false, 'msg' => '참가자가 아니에요.'];
    }
    if (empty($state['players'][$pi]['out']) && empty($state['players'][$pi]['broke'])) {
      return ['ok' => false, 'msg' => '이미 진행 중인 친구예요.'];
    }
    $state['players'][$pi]['out'] = false;
    $state['players'][$pi]['broke'] = false;
    $state['players'][$pi]['quit'] = false;
    $state['players'][$pi]['force_broke'] = false;
    $state['players'][$pi]['sinbul'] = false;
    $state['players'][$pi]['jail'] = 0;
    $state['players'][$pi]['skip_roll'] = 0;
    $have = burumable_포인트양수($nick);
    if (burumable_비교($have, '1') < 0) {
      $grant = function_exists('burumable_지급시작금') ? burumable_지급시작금($state) : '0';
      if (burumable_비교($grant, '1') >= 0) {
        burumable_잔액설정($nick, $grant);
      }
    }
    burumable_현금맞춤($state, $pi);
    $elim = $state['elim_order'] ?? [];
    if (is_array($elim) && $elim !== []) {
      $keep = [];
      foreach ($elim as $e) {
        $en = is_array($e) ? trim((string)($e['name'] ?? '')) : trim((string)$e);
        if ($en !== '' && $en !== $nick) {
          $keep[] = $e;
        }
      }
      $state['elim_order'] = $keep;
    }
    return ['ok' => true, 'name' => $nick];
  }
}

if (!function_exists('burumable_재진행')) {
  /** 민호: 강제파산 복구 · 끝난 판이면 이어서 진행 (정산종료 판 제외) */
  function burumable_재진행(array &$state, string $admin, string $who = ''): array {
    if (!burumable_민호인가($admin)) {
      return ['ok' => false, 'msg' => '민호만 할 수 있어요.'];
    }
    $who = trim($who);
    $wasOver = !empty($state['over']);
    if ($wasOver && (string)($state['end_kind'] ?? '') === 'settle') {
      return ['ok' => false, 'msg' => '정산 종료한 판은 재진행할 수 없어요. 새 판을 시작해 주세요.'];
    }
    $names = [];
    if ($who !== '') {
      $names[] = $who;
    } elseif ($wasOver) {
      foreach ((array)($state['players'] ?? []) as $p) {
        if (!is_array($p)) {
          continue;
        }
        $n = trim((string)($p['name'] ?? ''));
        if ($n !== '' && !empty($p['force_broke'])) {
          $names[] = $n;
        }
      }
      if ($names === []) {
        foreach ((array)($state['players'] ?? []) as $p) {
          if (!is_array($p) || empty($p['broke']) || !empty($p['quit'])) {
            continue;
          }
          $n = trim((string)($p['name'] ?? ''));
          if ($n !== '') {
            $names[] = $n;
          }
        }
      }
    } else {
      return ['ok' => false, 'msg' => '복구할 친구를 골라 주세요.'];
    }
    if ($names === []) {
      return ['ok' => false, 'msg' => '복구할 파산 친구가 없어요.'];
    }
    $okNames = [];
    foreach ($names as $n) {
      $r = burumable_파산복구($state, $n);
      if (!empty($r['ok'])) {
        $okNames[] = $n;
      }
    }
    if ($okNames === [] && !$wasOver) {
      return ['ok' => false, 'msg' => '복구에 실패했어요.'];
    }
    if ($wasOver) {
      $alive = function_exists('burumable_생존닉목록') ? burumable_생존닉목록($state) : [];
      if (count($alive) < 2) {
        return ['ok' => false, 'msg' => '진행하려면 2명 이상이 필요해요. 파산한 친구를 복구해 주세요.'];
      }
      $state['over'] = false;
      $state['paused'] = false;
      $state['pending'] = null;
      $state['winner'] = '';
      $state['ranks'] = [];
      $state['end_kind'] = '';
      unset($state['pause_turn_left'], $state['pause_buy_left']);
    }
    $cur = burumable_현재닉($state);
    $ci = burumable_닉찾기($state, $cur);
    if ($ci < 0 || !empty($state['players'][$ci]['out'])) {
      $alive = function_exists('burumable_생존닉목록') ? burumable_생존닉목록($state) : [];
      $ni = ($alive !== []) ? burumable_닉찾기($state, $alive[0]) : 0;
      $state['cur'] = $ni >= 0 ? $ni : 0;
    }
    $state['turn_at'] = time();
    $state['acted'] = false;
    $state['again'] = false;
    $label = $okNames !== [] ? implode(', ', $okNames) : '';
    $msg = $wasOver
      ? ('재진행 · ' . ($label !== '' ? ($label . ' 복귀 · ') : '') . burumable_현재닉($state) . ' 님 차례')
      : ($okNames[0] . ' 님이 부활했어요.');
    burumable_로그($state, '▶ ' . $msg);
    burumable_홍보알림("🎲 부루마블\n▶ 재진행" . ($label !== '' ? ("\n{$label} 복귀") : '') . "\n" . burumable_현재닉($state) . ' 님 차례', 'burumable_replay');
    if (function_exists('burumable_턴알림')) {
      burumable_턴알림($state);
    }
    return ['ok' => true, 'msg' => $msg];
  }
}

if (!function_exists('burumable_강제턴넘김')) {
  function burumable_강제턴넘김(array &$state, string $admin, string $target): array {
    if (!burumable_민호인가($admin)) {
      return ['ok' => false, 'msg' => '민호만 할 수 있어요.'];
    }
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    if (!empty($state['paused'])) {
      return ['ok' => false, 'msg' => '일시멈춤 중이에요. 이어한 뒤에 넘길 수 있어요.'];
    }
    $target = trim($target);
    if ($target === '') {
      return ['ok' => false, 'msg' => '대상을 고르세요.'];
    }
    $pi = burumable_닉찾기($state, $target);
    if ($pi < 0) {
      return ['ok' => false, 'msg' => '참가자가 아니에요.'];
    }
    if (!empty($state['players'][$pi]['out'])) {
      return ['ok' => false, 'msg' => '이미 빠진 친구예요.'];
    }
    $wait = burumable_강제대상대기닉($state);
    if ($wait !== $target) {
      return ['ok' => false, 'msg' => '지금은 ' . ($wait !== '' ? $wait : '다른 친구') . ' 님 차례예요.'];
    }
    $pend = $state['pending'] ?? null;
    if (is_array($pend) && function_exists('burumable_대기종류인가') && burumable_대기종류인가($pend['kind'] ?? '')) {
      $state['pending']['at'] = 1;
    } else {
      $state['turn_at'] = 1;
      $state['acted'] = false;
    }
    if (!function_exists('burumable_턴만료처리') || !burumable_턴만료처리($state, '')) {
      return ['ok' => false, 'msg' => '지금은 넘길 수 없어요.'];
    }
    return ['ok' => true, 'msg' => $target . ' 님 턴을 넘겼어요.'];
  }
}

if (!function_exists('burumable_신불파산정리')) {
  /** 예전에 마이너스로 남은 신불자는 이번 판 파산으로 정리 */
  function burumable_신불파산정리(array &$state): bool {
    if (!empty($state['over']) || empty($state['players']) || !is_array($state['players'])) {
      return false;
    }
    $names = [];
    foreach ($state['players'] as $p) {
      if (!empty($p['out'])) {
        continue;
      }
      $nm = trim((string)($p['name'] ?? ''));
      if ($nm !== '' && function_exists('burumable_신불인가') && burumable_신불인가($nm)) {
        $names[] = $nm;
      }
    }
    if ($names === []) {
      return false;
    }
    foreach ($names as $nm) {
      burumable_파산($state, $nm, '신불정리');
    }
    return true;
  }
}

if (!function_exists('burumable_건물최대')) {
  /** 1=펜션 · 2=호텔 · 3=랜드마크 */
  function burumable_건물최대(): int {
    return 3;
  }
}

if (!function_exists('burumable_건물단계')) {
  /** 0=빈땅 · 1=펜션 · 2=호텔 · 3=랜드마크 */
  function burumable_건물단계(array $state, int $tid): int {
    $v = $state['build'][(string)$tid] ?? $state['build'][$tid] ?? 0;
    return max(0, min(burumable_건물최대(), (int)$v));
  }
}

if (!function_exists('burumable_다음건물')) {
  function burumable_다음건물(int $lv): int {
    $max = burumable_건물최대();
    if ($lv < 0) {
      $lv = 0;
    }
    if ($lv >= $max) {
      return $max;
    }
    return $lv + 1;
  }
}

if (!function_exists('burumable_매수불가')) {
  /** 랜드마크는 인수·강제구매 불가 */
  function burumable_매수불가(array $state, int $tid): bool {
    return burumable_건물단계($state, $tid) >= burumable_건물최대();
  }
}

if (!function_exists('burumable_건물비')) {
  function burumable_건물비(array $tile, int $nextLevel): string {
    $p = burumable_냥($tile['price'] ?? 0);
    if ($nextLevel === 1) {
      return burumable_몫($p, 1, 2);
    }
    if ($nextLevel === 2) {
      return $p;
    }
    if ($nextLevel === 3) {
      return burumable_곱($p, 2);
    }
    return '0';
  }
}

if (!function_exists('burumable_건물이름')) {
  function burumable_건물이름(int $lv): string {
    if ($lv >= 3) {
      return '랜드마크';
    }
    if ($lv >= 2) {
      return '호텔';
    }
    if ($lv >= 1) {
      return '펜션';
    }
    return '빈땅';
  }
}

if (!function_exists('burumable_그룹완전체')) {
  function burumable_그룹완전체(array $state, string $ownerNick, string $group): bool {
    if ($group === '' || $ownerNick === '') {
      return false;
    }
    foreach (burumable_보드() as $t) {
      if (($t['group'] ?? '') !== $group) {
        continue;
      }
      $on = (string)($state['owner'][(string)$t['id']] ?? $state['owner'][$t['id']] ?? '');
      if ($on !== $ownerNick) {
        return false;
      }
    }
    return true;
  }
}

if (!function_exists('burumable_렌트')) {
  /** 통행료: 빈땅 / 색완성 2배 / 펜션 5배 / 호텔 12배 / 랜드마크 25배 */
  function burumable_렌트(array $state, array $tile, string $ownerNick): string {
    $tile = burumable_칸시세($state, $tile);
    $rent = burumable_냥($tile['rent'] ?? 0);
    $lv = burumable_건물단계($state, (int)($tile['id'] ?? 0));
    if ($lv >= 3) {
      return burumable_곱($rent, 25);
    }
    if ($lv >= 2) {
      return burumable_곱($rent, 12);
    }
    if ($lv >= 1) {
      return burumable_곱($rent, 5);
    }
    if (burumable_그룹완전체($state, $ownerNick, (string)($tile['group'] ?? ''))) {
      return burumable_곱($rent, 2);
    }
    return $rent;
  }
}

if (!function_exists('burumable_찬스이동')) {
  /** 월급 없이 칸으로 옮긴 뒤 그 칸 효과 적용 */
  function burumable_찬스이동(array &$state, int $pi, int $pos): void {
    $n = burumable_칸수();
    if ($n < 1) {
      return;
    }
    $pos = (($pos % $n) + $n) % $n;
    $state['players'][$pi]['pos'] = $pos;
    $tile = burumable_보드()[$pos] ?? ['name' => ''];
    burumable_로그($state, (string)$state['players'][$pi]['name'] . ' → ' . (string)($tile['name'] ?? ''));
    burumable_칸처리($state, $pi);
    if (function_exists('burumable_도착기록')) {
      burumable_도착기록($state, $pi);
    }
  }
}

if (!function_exists('burumable_보유땅이름들')) {
  /** @return list<string> */
  function burumable_보유땅이름들(array $state, string $nick): array {
    $nick = trim($nick);
    $names = [];
    if ($nick === '' || empty($state['owner']) || !is_array($state['owner'])) {
      return $names;
    }
    $board = burumable_보드();
    foreach ($state['owner'] as $tid => $on) {
      if ((string)$on !== $nick) {
        continue;
      }
      $t = $board[(int)$tid] ?? null;
      if (!is_array($t) || (string)($t['type'] ?? '') !== 'land') {
        continue;
      }
      $nm = trim((string)($t['name'] ?? ''));
      if ($nm !== '') {
        $names[] = $nm;
      }
    }
    return $names;
  }
}

if (!function_exists('burumable_나라수')) {
  function burumable_나라수(): int {
    $n = 0;
    foreach (burumable_보드() as $t) {
      if ((string)($t['type'] ?? '') === 'land') {
        $n++;
      }
    }
    return $n;
  }
}

if (!function_exists('burumable_활성인원')) {
  function burumable_활성인원(array $state): int {
    $n = 0;
    foreach ((array)($state['players'] ?? []) as $p) {
      if (empty($p['out'])) {
        $n++;
      }
    }
    return max(1, $n);
  }
}

if (!function_exists('burumable_땅보유상한')) {
  /** 땅 보유 상한 없음 */
  function burumable_땅보유상한(array $state): int {
    return 0;
  }
}

if (!function_exists('burumable_보유땅수')) {
  function burumable_보유땅수(array $state, string $nick): int {
    $nick = trim($nick);
    if ($nick === '' || empty($state['owner']) || !is_array($state['owner'])) {
      return 0;
    }
    $n = 0;
    foreach ($state['owner'] as $on) {
      if ((string)$on === $nick) {
        $n++;
      }
    }
    return $n;
  }
}

if (!function_exists('burumable_땅더살수있나')) {
  function burumable_땅더살수있나(array $state, string $nick): bool {
    return trim($nick) !== '';
  }
}

if (!function_exists('burumable_후발유예중인가')) {
  function burumable_후발유예중인가(array $state, int $pi): bool {
    return (int)($state['players'][$pi]['grace_go'] ?? 0) > 0;
  }
}

if (!function_exists('burumable_강제구매남음')) {
  function burumable_강제구매남음(array $state, int $pi): int {
    return max(0, (int)($state['players'][$pi]['force_buy'] ?? 0));
  }
}

if (!function_exists('burumable_통행할인남음')) {
  function burumable_통행할인남음(array $state, int $pi): int {
    return max(0, (int)($state['players'][$pi]['rent_cut'] ?? 0));
  }
}

if (!function_exists('burumable_통행할인퍼센트')) {
  function burumable_통행할인퍼센트(): int {
    return defined('부루마블_찬스통행할인_퍼센트') ? max(0, min(100, (int)부루마블_찬스통행할인_퍼센트)) : 50;
  }
}

if (!function_exists('burumable_통행료할인정보')) {
  /** @return array{pct:int,grace:bool,cut:bool,left:int} */
  function burumable_통행료할인정보(array $state, int $pi): array {
    $pct = 100;
    $grace = false;
    $cut = false;
    if (burumable_후발유예중인가($state, $pi)) {
      $g = defined('부루마블_후발통행료_퍼센트') ? max(0, min(100, (int)부루마블_후발통행료_퍼센트)) : 50;
      if ($g < $pct) {
        $pct = $g;
        $grace = true;
      }
    }
    $left = burumable_통행할인남음($state, $pi);
    if ($left > 0) {
      $c = burumable_통행할인퍼센트();
      if ($c < $pct) {
        $pct = $c;
        $cut = true;
        $grace = false;
      } elseif ($c === $pct && !$grace) {
        $cut = true;
      }
    }
    return ['pct' => $pct, 'grace' => $grace, 'cut' => $cut, 'left' => $left];
  }
}

if (!function_exists('burumable_통행료액')) {
  function burumable_통행료액(array $state, int $pi, array $tile, string $ownerNick): string {
    $rent = burumable_렌트($state, $tile, $ownerNick);
    $info = burumable_통행료할인정보($state, $pi);
    if ((int)$info['pct'] >= 100) {
      return $rent;
    }
    return burumable_몫($rent, (int)$info['pct'], 100);
  }
}

if (!function_exists('burumable_인수퍼센트')) {
  function burumable_인수퍼센트(): int {
    return defined('부루마블_인수_퍼센트') ? max(100, (int)부루마블_인수_퍼센트) : 150;
  }
}

if (!function_exists('burumable_인수금액')) {
  function burumable_인수금액(array $state, int $tid): string {
    $gross = burumable_칸부동산($state, $tid);
    return burumable_몫($gross, burumable_인수퍼센트(), 100);
  }
}

if (!function_exists('burumable_통행료적용')) {
  function burumable_통행료적용(array &$state, int $pi, int $tid): void {
    $name = (string)($state['players'][$pi]['name'] ?? '');
    $board = burumable_보드();
    $tile = burumable_칸시세($state, $board[$tid] ?? ['id' => $tid, 'name' => '', 'type' => 'land']);
    $on = (string)($state['owner'][(string)$tid] ?? $state['owner'][$tid] ?? '');
    $oi = $on !== '' ? burumable_닉찾기($state, $on) : -1;
    if ($oi < 0 || !empty($state['players'][$oi]['out'])) {
      return;
    }
    $rent = burumable_통행료액($state, $pi, $tile, $on);
    $info = burumable_통행료할인정보($state, $pi);
    $bld = burumable_건물이름(burumable_건물단계($state, $tid));
    $note = '';
    if (!empty($info['grace'])) {
      $note .= ' · 후발 ' . (int)$info['pct'] . '%';
    } elseif (!empty($info['cut'])) {
      $left = max(0, (int)$info['left'] - 1);
      $note .= ' · 찬스할인 ' . (int)$info['pct'] . '% · ' . $left . '회 남음';
    }
    if (!empty($info['cut'])) {
      $state['players'][$pi]['rent_cut'] = max(0, (int)$info['left'] - 1);
    }
    if (burumable_비교($rent, '1') < 0) {
      burumable_로그($state, '🚧 ' . $name . ' → ' . (string)($tile['name'] ?? '') . ' 통행료 없음' . $note);
      return;
    }
    burumable_로그($state, '🚧 ' . $name . ' → ' . (string)($tile['name'] ?? '') . ' 통행료 ' . burumable_만표시($rent) . ' (' . $on . ' · ' . $bld . ')' . $note);
    burumable_홍보알림("🎲 부루마블\n🚧 {$name} → {$tile['name']} 통행료 " . burumable_만표시($rent) . "\n주인 {$on} · {$bld}" . $note, 'burumable_rent');
    burumable_지불($state, $pi, $rent, $oi, '임대');
  }
}

if (!function_exists('burumable_발견누적')) {
  /** 이번 판에 실제로 지급된 은총조각·은총만 집계 (가방 지급은 그대로) */
  function burumable_발견누적(array &$state, int $pi, string $kind, int $qty): void {
    $qty = max(0, (int)$qty);
    if ($qty < 1) {
      return;
    }
    $key = ($kind === 'eunchong') ? 'found_eun' : 'found_shard';
    $state[$key] = max(0, (int)($state[$key] ?? 0)) + $qty;
    if (isset($state['players'][$pi]) && is_array($state['players'][$pi])) {
      $state['players'][$pi][$key] = max(0, (int)($state['players'][$pi][$key] ?? 0)) + $qty;
    }
  }
}

if (!function_exists('burumable_발견이력초기화')) {
  /** 지급된 은총·조각은 유지하고, 보드에 보이는 이번 판 발견 이력만 0으로 */
  function burumable_발견이력초기화(array &$state): void {
    $state['found_shard'] = 0;
    $state['found_eun'] = 0;
    if (empty($state['players']) || !is_array($state['players'])) {
      return;
    }
    foreach ($state['players'] as &$p) {
      $p['found_shard'] = 0;
      $p['found_eun'] = 0;
    }
    unset($p);
  }
}

if (!function_exists('burumable_은총조각지급')) {
  function burumable_은총조각지급(string $nick, int $qty): array {
    $qty = max(0, $qty);
    if ($qty < 1 || trim($nick) === '') {
      return ['ok' => false, 'added' => 0];
    }
    $ore = __DIR__ . '/mining_ore.inc.php';
    if (!function_exists('mining_ore_add_shards') && is_file($ore)) {
      require_once $ore;
    }
    if (!function_exists('mining_ore_add_shards')) {
      return ['ok' => false, 'added' => 0];
    }
    $r = mining_ore_add_shards($nick, $qty);
    $added = (int)($r['added'] ?? 0);
    if ($added > 0 && function_exists('지급로그')) {
      지급로그('부루마블-은총조각', $nick, $nick, 0, $added);
    }
    return ['ok' => !empty($r['ok']), 'added' => $added, 'shard' => (int)($r['shard'] ?? 0)];
  }
}

if (!function_exists('burumable_은총지급')) {
  function burumable_은총지급(string $nick, int $qty): array {
    $qty = max(0, $qty);
    $nick = trim($nick);
    if ($qty < 1 || $nick === '') {
      return ['ok' => false, 'added' => 0];
    }
    $bag = dirname(__DIR__) . '/item_bag_enhance.inc.php';
    if (!function_exists('bag_은총_가산') && is_file($bag)) {
      require_once $bag;
    }
    if (function_exists('bag_은총_가산')) {
      $r = bag_은총_가산($nick, $qty);
      if (!empty($r['ok'])) {
        if (function_exists('지급로그')) {
          지급로그('부루마블-은총', $nick, $nick, 0, $qty);
        }
        return ['ok' => true, 'added' => $qty];
      }
    }
    $esc = addslashes($nick);
    $ok = @db_query("UPDATE tb_member SET 은총개수 = IFNULL(은총개수, 0) + {$qty} WHERE name = '{$esc}' LIMIT 1");
    if ($ok && function_exists('지급로그')) {
      지급로그('부루마블-은총', $nick, $nick, 0, $qty);
    }
    return ['ok' => (bool)$ok, 'added' => $ok ? $qty : 0];
  }
}

if (!function_exists('burumable_찬스광물')) {
  /** 가진 땅에서 광물 발견. $kind=shard|eunchong */
  function burumable_찬스광물(array &$state, int $pi, string $kind, string $where = '', int $qty = 0): void {
    $name = (string)($state['players'][$pi]['name'] ?? '');
    $lands = burumable_보유땅이름들($state, $name);
    if ($lands === []) {
      burumable_로그($state, '🎴 ' . $name . ' 광물을 찾으려 했지만 가진 땅이 없어요');
      return;
    }
    if ($where === '' || !in_array($where, $lands, true)) {
      $where = $lands[random_int(0, count($lands) - 1)];
    }
    if ($kind === 'eunchong') {
      $qty = $qty > 0 ? min(3, $qty) : random_int(1, 3);
      $r = burumable_은총지급($name, $qty);
      $got = (int)($r['added'] ?? 0);
      if ($got < 1) {
        burumable_로그($state, '🎴 ' . $name . ' ' . $where . '에서 광맥을 찾았지만 은총을 받지 못했어요');
        return;
      }
      burumable_발견누적($state, $pi, 'eunchong', $got);
      burumable_로그($state, '🎴 ' . $name . ' ' . $where . '에서 광맥 발견 · 은총 +' . $got);
      burumable_홍보알림("🎲 부루마블\n🎴 {$name} {$where}에서 광맥 발견\n은총 +{$got}", 'burumable_ore_eun');
      return;
    }
    $qty = $qty > 0 ? min(10, $qty) : random_int(1, 10);
    $r = burumable_은총조각지급($name, $qty);
    $got = (int)($r['added'] ?? 0);
    if ($got < 1) {
      burumable_로그($state, '🎴 ' . $name . ' ' . $where . '에서 광물을 찾았지만 은총조각 한도예요');
      return;
    }
    burumable_발견누적($state, $pi, 'shard', $got);
    burumable_로그($state, '🎴 ' . $name . ' ' . $where . '에서 광물 발견 · 은총조각 +' . $got);
    burumable_홍보알림("🎲 부루마블\n🎴 {$name} {$where}에서 광물 발견\n은총조각 +{$got}", 'burumable_ore_shard');
  }
}

if (!function_exists('burumable_서울칸')) {
  function burumable_서울칸(): int {
    foreach (burumable_보드() as $t) {
      if ((string)($t['name'] ?? '') === '서울') {
        return (int)$t['id'];
      }
    }
    return 47;
  }
}

if (!function_exists('burumable_찬스카드후보')) {
  /** @return list<array<string,mixed>> */
  function burumable_찬스카드후보(array $state, int $pi): array {
    $name = (string)($state['players'][$pi]['name'] ?? '');
    $start = burumable_시작금($state);
    $bank = burumable_냥($state['quote']['bank'] ?? burumable_몫($start, 5, 100));
    $hosp = burumable_냥($state['quote']['hospital'] ?? burumable_몫($start, 4, 100));
    $win = burumable_몫($start, 3, 100);
    $fine = burumable_몫($start, 3, 100);
    $cards = [
      ['id' => 'bank', 'title' => '은행 실수', 'desc' => '+' . burumable_만표시($bank), 'tone' => 'good', 'amt' => $bank],
      ['id' => 'hospital', 'title' => '병원비', 'desc' => burumable_만표시($hosp), 'tone' => 'bad', 'amt' => $hosp],
      ['id' => 'forward3', 'title' => '앞으로 3칸', 'desc' => '월급 포함 이동', 'tone' => 'move'],
      ['id' => 'go', 'title' => '출발로 이동', 'desc' => '월급 ' . burumable_만표시(burumable_월급($state)), 'tone' => 'good'],
      ['id' => 'jail', 'title' => '무인도로', 'desc' => '다음 턴 쉼', 'tone' => 'bad'],
      ['id' => 'back3', 'title' => '뒤로 3칸', 'desc' => '월급 없이 이동', 'tone' => 'move'],
      ['id' => 'lotto', 'title' => '복권 당첨', 'desc' => '+' . burumable_만표시($win), 'tone' => 'good', 'amt' => $win],
      ['id' => 'fine', 'title' => '과속 벌금', 'desc' => burumable_만표시($fine), 'tone' => 'bad', 'amt' => $fine],
      ['id' => 'park', 'title' => '주차장으로', 'desc' => '효과 없는 칸으로 이동', 'tone' => 'move'],
      ['id' => 'seoul', 'title' => '서울로 이동', 'desc' => '서울 칸 효과 적용', 'tone' => 'move'],
      ['id' => 'world', 'title' => '세계여행', 'desc' => '원하는 도시로 이동', 'tone' => 'move'],
      ['id' => 'rentcut', 'title' => '통행료 할인', 'desc' => '3회 50%', 'tone' => 'good', 'qty' => (defined('부루마블_찬스통행할인_횟수') ? max(1, (int)부루마블_찬스통행할인_횟수) : 3), 'pct' => burumable_통행할인퍼센트()],
      ['id' => 'forcebuy', 'title' => '도시 강제구매', 'desc' => '도착한 남의 도시 1회', 'tone' => 'good'],
    ];
    $lands = burumable_보유땅이름들($state, $name);
    if ($lands !== []) {
      $where = $lands[random_int(0, count($lands) - 1)];
      $shardQty = random_int(1, 10);
      $eunQty = random_int(1, 3);
      $cards[] = ['id' => 'shard', 'title' => '광물 발견', 'desc' => $where . ' · 은총조각 +' . $shardQty, 'tone' => 'good', 'where' => $where, 'qty' => $shardQty];
      $cards[] = ['id' => 'eunchong', 'title' => '광맥 발견', 'desc' => $where . ' · 은총 +' . $eunQty, 'tone' => 'good', 'where' => $where, 'qty' => $eunQty];
    }
    return $cards;
  }
}

if (!function_exists('burumable_찬스카드뽑기')) {
  /** @return list<array<string,mixed>> */
  function burumable_찬스카드뽑기(array $state, int $pi): array {
    $all = burumable_찬스카드후보($state, $pi);
    $n = defined('부루마블_찬스카드수') ? max(2, min(6, (int)부루마블_찬스카드수)) : 3;
    shuffle($all);
    $picked = array_slice(array_values($all), 0, $n);
    $out = [];
    foreach ($picked as $i => $card) {
      $card['pick'] = 'c' . ($i + 1);
      $out[] = $card;
    }
    return $out;
  }
}

if (!function_exists('burumable_찬스카드공개')) {
  /** 고르기 전에 효과·등급은 숨기고 찬스1·2·3만 공개 */
  /** @param list<array<string,mixed>> $cards */
  function burumable_찬스카드공개(array $cards): array {
    $out = [];
    $n = 0;
    foreach ($cards as $c) {
      if (!is_array($c)) {
        continue;
      }
      $pick = (string)($c['pick'] ?? '');
      if ($pick === '') {
        $pick = (string)($c['id'] ?? '');
      }
      if ($pick === '') {
        continue;
      }
      $n++;
      $out[] = [
        'id' => $pick,
        'title' => '찬스' . $n,
      ];
    }
    return $out;
  }
}

if (!function_exists('burumable_찬스효과')) {
  function burumable_찬스효과(array &$state, int $pi, array $card): void {
    $p = &$state['players'][$pi];
    $name = (string)$p['name'];
    $id = (string)($card['id'] ?? '');
    if ($id === 'bank') {
      $bank = burumable_냥($card['amt'] ?? ($state['quote']['bank'] ?? 0));
      burumable_입금($name, $bank);
      burumable_현금맞춤($state, $pi);
      burumable_로그($state, '🎴 ' . $name . ' 은행 실수 +' . burumable_만표시($bank));
      return;
    }
    if ($id === 'hospital') {
      $hosp = burumable_냥($card['amt'] ?? 0);
      burumable_로그($state, '🎴 ' . $name . ' 병원비 ' . burumable_만표시($hosp));
      burumable_홍보알림("🎲 부루마블\n🎴 {$name} 병원비 " . burumable_만표시($hosp), 'burumable_hosp');
      burumable_지불($state, $pi, $hosp, -1, '병원비');
      return;
    }
    if ($id === 'forward3') {
      burumable_로그($state, '🎴 ' . $name . ' 앞으로 3칸');
      burumable_이동($state, $pi, 3);
      return;
    }
    if ($id === 'go') {
      burumable_로그($state, '🎴 ' . $name . ' 출발로 이동');
      $p['pos'] = 0;
      $pay = burumable_월급($state);
      burumable_입금($name, $pay);
      burumable_현금맞춤($state, $pi);
      burumable_로그($state, '💵 ' . $name . ' 월급 ' . burumable_만표시($pay));
      return;
    }
    if ($id === 'jail') {
      burumable_로그($state, '🎴 ' . $name . ' 무인도로');
      $p['pos'] = burumable_특수칸('jail');
      $p['jail'] = 1;
      return;
    }
    if ($id === 'back3') {
      burumable_로그($state, '🎴 ' . $name . ' 뒤로 3칸');
      burumable_찬스이동($state, $pi, (int)$p['pos'] - 3);
      return;
    }
    if ($id === 'lotto') {
      $win = burumable_냥($card['amt'] ?? 0);
      burumable_입금($name, $win);
      burumable_현금맞춤($state, $pi);
      burumable_로그($state, '🎴 ' . $name . ' 복권 당첨 +' . burumable_만표시($win));
      burumable_홍보알림("🎲 부루마블\n🎴 {$name} 복권 당첨 +" . burumable_만표시($win), 'burumable_lotto');
      return;
    }
    if ($id === 'fine') {
      $fine = burumable_냥($card['amt'] ?? 0);
      burumable_로그($state, '🎴 ' . $name . ' 과속 벌금 ' . burumable_만표시($fine));
      burumable_홍보알림("🎲 부루마블\n🎴 {$name} 과속 벌금 " . burumable_만표시($fine), 'burumable_fine');
      burumable_지불($state, $pi, $fine, -1, '과속벌금');
      return;
    }
    if ($id === 'park') {
      burumable_로그($state, '🎴 ' . $name . ' 주차장으로');
      burumable_찬스이동($state, $pi, burumable_특수칸('park'));
      return;
    }
    if ($id === 'shard') {
      burumable_찬스광물($state, $pi, 'shard', (string)($card['where'] ?? ''), (int)($card['qty'] ?? 0));
      return;
    }
    if ($id === 'eunchong') {
      burumable_찬스광물($state, $pi, 'eunchong', (string)($card['where'] ?? ''), (int)($card['qty'] ?? 0));
      return;
    }
    if ($id === 'rentcut') {
      $qty = (int)($card['qty'] ?? 0);
      if ($qty < 1) {
        $qty = defined('부루마블_찬스통행할인_횟수') ? max(1, (int)부루마블_찬스통행할인_횟수) : 3;
      }
      $pct = (int)($card['pct'] ?? 0);
      if ($pct < 1 || $pct > 100) {
        $pct = burumable_통행할인퍼센트();
      }
      $p['rent_cut'] = burumable_통행할인남음($state, $pi) + $qty;
      burumable_로그($state, '🎴 ' . $name . ' 통행료 ' . $qty . '회 ' . $pct . '% · 남은 ' . (int)$p['rent_cut'] . '회');
      burumable_홍보알림("🎲 부루마블\n🎴 {$name} 통행료 {$qty}회 {$pct}%\n남은 " . (int)$p['rent_cut'] . '회', 'burumable_rentcut');
      return;
    }
    if ($id === 'forcebuy') {
      $left = burumable_강제구매남음($state, $pi) + 1;
      $p['force_buy'] = $left;
      burumable_로그($state, '🎴 ' . $name . ' 도시 강제구매권 1회 · 남은 ' . $left . '회');
      burumable_홍보알림("🎲 부루마블\n🎴 {$name} 도시 강제구매권 1회\n남의 도시에 도착하면 주인이 거절할 수 없어요", 'burumable_forcebuy');
      return;
    }
    if ($id === 'world') {
      $sec = burumable_초표시(burumable_여행제한초());
      burumable_로그($state, '🎴 ' . $name . ' 세계여행 · ' . $sec . ' 안에 도시 고르기');
      burumable_홍보알림("🎲 부루마블\n🎴 {$name} 세계여행 · 원하는 도시로", 'burumable_world');
      $state['pending'] = [
        'kind' => 'travel',
        'tile' => (int)($p['pos'] ?? 0),
        'at' => time(),
      ];
      return;
    }
    $seoul = burumable_서울칸();
    burumable_로그($state, '🎴 ' . $name . ' 서울로 이동');
    burumable_홍보알림("🎲 부루마블\n🎴 {$name} 서울로 이동", 'burumable_seoul');
    burumable_찬스이동($state, $pi, $seoul);
  }
}

if (!function_exists('burumable_찬스')) {
  function burumable_찬스(array &$state, int $pi): void {
    $cards = burumable_찬스카드뽑기($state, $pi);
    if ($cards === []) {
      burumable_로그($state, '🎴 ' . (string)$state['players'][$pi]['name'] . ' 찬스 카드가 없어요');
      return;
    }
    $pos = (int)($state['players'][$pi]['pos'] ?? 0);
    $state['pending'] = [
      'kind' => 'chance',
      'tile' => $pos,
      'at' => time(),
      'cards' => $cards,
    ];
    $sec = burumable_초표시(burumable_찬스제한초());
    burumable_로그($state, '🎴 ' . (string)$state['players'][$pi]['name'] . ' 찬스 · ' . $sec . ' 안에 카드 고르기');
  }
}

if (!function_exists('burumable_찬스고르기')) {
  function burumable_찬스고르기(array &$state, string $nick, string $cardId, bool $auto = false): array {
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    $pend = $state['pending'] ?? null;
    if (!is_array($pend) || ($pend['kind'] ?? '') !== 'chance') {
      return ['ok' => false, 'msg' => '고를 찬스 카드가 없어요.'];
    }
    if (burumable_현재닉($state) !== $nick) {
      return ['ok' => false, 'msg' => '지금은 ' . burumable_현재닉($state) . ' 님 턴이에요.'];
    }
    if (!$auto && !burumable_내턴인가($state, $nick)) {
      return ['ok' => false, 'msg' => '지금은 ' . burumable_현재닉($state) . ' 님 턴이에요.'];
    }
    $pi = (int)$state['cur'];
    $cardId = trim($cardId);
    $card = null;
    foreach ((array)($pend['cards'] ?? []) as $c) {
      if (!is_array($c)) {
        continue;
      }
      $pick = (string)($c['pick'] ?? '');
      $real = (string)($c['id'] ?? '');
      if ($cardId !== '' && ($cardId === $pick || ($pick === '' && $cardId === $real))) {
        $card = $c;
        break;
      }
    }
    if (!is_array($card)) {
      return ['ok' => false, 'msg' => '나온 카드 중에서 고르세요.'];
    }
    $state['pending'] = null;
    burumable_턴제한리셋($state);
    $stay = burumable_현재닉($state);
    burumable_찬스효과($state, $pi, $card);
    if (empty($state['pending']) && burumable_현재닉($state) === $stay) {
      burumable_턴이어가기($state);
    }
    burumable_종료확인($state);
    return ['ok' => true];
  }
}

if (!function_exists('burumable_도시목록공개')) {
  /** @return list<array<string,mixed>> */
  function burumable_도시목록공개(array $state, string $nick = ''): array {
    $nick = trim($nick);
    $out = [];
    foreach (burumable_보드() as $t) {
      if ((string)($t['type'] ?? '') !== 'land') {
        continue;
      }
      $id = (int)$t['id'];
      $tile = burumable_칸시세($state, $t);
      $on = (string)($state['owner'][(string)$id] ?? $state['owner'][$id] ?? '');
      $mine = ($nick !== '' && $on === $nick);
      $out[] = [
        'id' => $id,
        'name' => (string)($tile['name'] ?? ''),
        'flag' => burumable_국기((string)($tile['name'] ?? '')),
        'owner' => $on,
        'mine' => $mine,
        'price_fmt' => burumable_만표시($tile['price'] ?? '0'),
      ];
    }
    return $out;
  }
}

if (!function_exists('burumable_세계여행고르기')) {
  function burumable_세계여행고르기(array &$state, string $nick, int $tileId, bool $auto = false): array {
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    $pend = $state['pending'] ?? null;
    if (!is_array($pend) || ($pend['kind'] ?? '') !== 'travel') {
      return ['ok' => false, 'msg' => '고를 세계여행이 없어요.'];
    }
    if (burumable_현재닉($state) !== $nick) {
      return ['ok' => false, 'msg' => '지금은 ' . burumable_현재닉($state) . ' 님 턴이에요.'];
    }
    if (!$auto && !burumable_내턴인가($state, $nick)) {
      return ['ok' => false, 'msg' => '지금은 ' . burumable_현재닉($state) . ' 님 턴이에요.'];
    }
    $pi = (int)$state['cur'];
    $board = burumable_보드();
    if ($auto && ($tileId < 0 || !isset($board[$tileId]) || ($board[$tileId]['type'] ?? '') !== 'land')) {
      $lands = [];
      foreach ($board as $t) {
        if ((string)($t['type'] ?? '') === 'land') {
          $lands[] = (int)$t['id'];
        }
      }
      if ($lands === []) {
        $state['pending'] = null;
        burumable_턴이어가기($state);
        burumable_종료확인($state);
        return ['ok' => false, 'msg' => '이동할 도시가 없어요.'];
      }
      $tileId = $lands[random_int(0, count($lands) - 1)];
    }
    if (!isset($board[$tileId]) || ($board[$tileId]['type'] ?? '') !== 'land') {
      return ['ok' => false, 'msg' => '나라를 고르세요.'];
    }
    $city = (string)($board[$tileId]['name'] ?? '');
    $state['pending'] = null;
    burumable_턴제한리셋($state);
    $stay = burumable_현재닉($state);
    $who = (string)($state['players'][$pi]['name'] ?? $nick);
    burumable_로그($state, '✈️ ' . $who . ' 세계여행 → ' . $city);
    burumable_홍보알림("🎲 부루마블\n✈️ {$who} 세계여행 → {$city}", 'burumable_worldgo');
    burumable_찬스이동($state, $pi, $tileId);
    if (empty($state['pending']) && burumable_현재닉($state) === $stay) {
      burumable_턴이어가기($state);
    }
    burumable_종료확인($state);
    return ['ok' => true];
  }
}

if (!function_exists('burumable_강제구매대상목록')) {
  /** @return list<array<string,mixed>> */
  function burumable_강제구매대상목록(array $state, string $nick): array {
    $nick = trim($nick);
    $have = $nick !== '' ? burumable_포인트양수($nick) : '0';
    $out = [];
    foreach (burumable_보드() as $t) {
      if ((string)($t['type'] ?? '') !== 'land') {
        continue;
      }
      $id = (int)$t['id'];
      $on = (string)($state['owner'][(string)$id] ?? $state['owner'][$id] ?? '');
      if ($on === '' || $on === $nick) {
        continue;
      }
      $oi = burumable_닉찾기($state, $on);
      if ($oi < 0 || !empty($state['players'][$oi]['out'])) {
        continue;
      }
      $price = burumable_인수금액($state, $id);
      if (burumable_비교($price, '1') < 0) {
        continue;
      }
      $lv = burumable_건물단계($state, $id);
      if (function_exists('burumable_매수불가') && burumable_매수불가($state, $id)) {
        continue;
      }
      $tile = burumable_칸시세($state, $t);
      $out[] = [
        'id' => $id,
        'name' => (string)($tile['name'] ?? ''),
        'flag' => burumable_국기((string)($tile['name'] ?? '')),
        'owner' => $on,
        'price_fmt' => burumable_만표시($price),
        'build_label' => burumable_건물이름($lv),
        'afford' => burumable_비교($have, $price) >= 0,
      ];
    }
    return $out;
  }
}

if (!function_exists('burumable_강제구매고르기')) {
  function burumable_강제구매고르기(array &$state, string $nick, int $tileId = -1, bool $auto = false): array {
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    $pend = $state['pending'] ?? null;
    $kind = is_array($pend) ? (string)($pend['kind'] ?? '') : '';
    if ($kind === 'forcebuy') {
      $pi = burumable_닉찾기($state, $nick);
      if ($pi >= 0) {
        $state['players'][$pi]['force_buy'] = burumable_강제구매남음($state, $pi) + 1;
        burumable_로그($state, '🎴 ' . $nick . ' 도시 강제구매권 1회 보관 · 남은 ' . (int)$state['players'][$pi]['force_buy'] . '회');
      }
      burumable_턴제한리셋($state);
      $state['pending'] = null;
      burumable_턴이어가기($state);
      burumable_종료확인($state);
      return ['ok' => true];
    }
    if ($kind !== 'takeover') {
      return ['ok' => false, 'msg' => '강제 구매할 도시가 없어요.'];
    }
    $wait = trim((string)($pend['wait'] ?? burumable_현재닉($state)));
    if (!$auto && $wait !== $nick) {
      return ['ok' => false, 'msg' => '지금은 ' . $wait . ' 님 차례예요.'];
    }
    $tid = (int)($pend['tile'] ?? -1);
    $who = (string)($pend['from'] ?? $nick);
    $owner = (string)($pend['owner'] ?? '');
    $pi = burumable_닉찾기($state, $nick);
    $forceTake = function_exists('burumable_강제인수가능') && burumable_강제인수가능($state, $tid);
    $hasTicket = $pi >= 0 && burumable_강제구매남음($state, $pi) >= 1;
    if ($pi < 0 || (!$hasTicket && !$forceTake)) {
      return ['ok' => false, 'msg' => $forceTake ? '강제인수할 수 없어요.' : '강제구매권이 없어요.'];
    }
    $board = burumable_보드();
    if (!isset($board[$tid]) || ($board[$tid]['type'] ?? '') !== 'land') {
      return ['ok' => false, 'msg' => '강제 구매할 수 없는 땅이에요.'];
    }
    $onNow = (string)($state['owner'][(string)$tid] ?? $state['owner'][$tid] ?? '');
    if ($onNow !== $owner || $owner === '' || $owner === $who) {
      return ['ok' => false, 'msg' => '강제 구매할 수 없는 땅이에요.'];
    }
    $oi = burumable_닉찾기($state, $owner);
    $vi = burumable_닉찾기($state, $who);
    if ($oi < 0 || $vi < 0 || !empty($state['players'][$oi]['out'])) {
      return ['ok' => false, 'msg' => '강제 구매할 수 없는 땅이에요.'];
    }
    if (!burumable_땅더살수있나($state, $who)) {
      return ['ok' => false, 'msg' => '강제 구매할 수 없는 땅이에요.'];
    }
    if (function_exists('burumable_매수불가') && burumable_매수불가($state, $tid)) {
      burumable_턴제한리셋($state);
      if (function_exists('burumable_인수통행료후진행')) {
        burumable_인수통행료후진행($state, $tid, $who);
      }
      return ['ok' => true, 'msg' => '랜드마크는 매수할 수 없어요. 통행료만 내요.'];
    }
    $price = burumable_냥($pend['price'] ?? burumable_인수금액($state, $tid));
    $tile = burumable_칸시세($state, $board[$tid]);
    $city = (string)($tile['name'] ?? '');
    $swap = function_exists('burumable_자동스왑') ? burumable_자동스왑($who, $price) : [];
    if (!empty($swap['swapped']) && ($swap['msg'] ?? '') !== '') {
      burumable_로그($state, '💱 ' . $who . ' ' . $swap['msg']);
    }
    if (burumable_비교(burumable_포인트양수($who), $price) < 0 || !burumable_지불($state, $vi, $price, $oi, $forceTake ? '강제인수' : '강제구매', false)) {
      return ['ok' => false, 'msg' => ($forceTake ? '강제인수' : '강제 구매') . ' 비용이 부족해요. (' . burumable_만표시($price) . ')'];
    }
    $state['owner'][(string)$tid] = $who;
    if (function_exists('burumable_인수거절리셋')) {
      burumable_인수거절리셋($state, $tid);
    }
    if ($hasTicket && !$forceTake) {
      $state['players'][$pi]['force_buy'] = max(0, burumable_강제구매남음($state, $pi) - 1);
    }
    $lv = burumable_건물단계($state, $tid);
    $bld = burumable_건물이름($lv);
    $left = (int)($state['players'][$pi]['force_buy'] ?? 0);
    burumable_턴제한리셋($state);
    $state['pending'] = null;
    if ($forceTake) {
      burumable_로그($state, '⚡ ' . $who . ' ' . $city . ' 강제인수 · ' . $owner . '에게 ' . burumable_만표시($price) . ($lv > 0 ? (' · ' . $bld) : '') . ' · 거절 한도');
      burumable_홍보알림(
        "🎲 부루마블\n⚡ {$who} {$city} 강제인수\n{$owner}에게 " . burumable_만표시($price)
          . ($lv > 0 ? (" · {$bld}") : '')
          . "\n거절 한도 · 주인 동의 없음",
        'burumable_force_takeover_ok'
      );
    } else {
      burumable_로그($state, '⚡ ' . $who . ' ' . $city . ' 강제구매 · ' . $owner . '에게 ' . burumable_만표시($price) . ($lv > 0 ? (' · ' . $bld) : '') . ' · 권 ' . $left . '회');
      burumable_홍보알림(
        "🎲 부루마블\n⚡ {$who} {$city} 강제구매\n{$owner}에게 " . burumable_만표시($price)
          . ($lv > 0 ? (" · {$bld}") : ''),
        'burumable_forcebuy_ok'
      );
    }
    burumable_턴이어가기($state);
    burumable_종료확인($state);
    return ['ok' => true];
  }
}

if (!function_exists('burumable_칸처리')) {
  function burumable_칸처리(array &$state, int $pi): void {
    if (!empty($state['over']) || !empty($state['players'][$pi]['out'])) {
      return;
    }
    $board = burumable_보드();
    $pos = (int)$state['players'][$pi]['pos'];
    $tile = $board[$pos];
    $name = (string)$state['players'][$pi]['name'];
    $type = (string)$tile['type'];
    if ($type === 'go') {
      burumable_로그($state, $name . ' 출발 칸');
      return;
    }
    if ($type === 'jail') {
      $state['players'][$pi]['jail'] = 1;
      burumable_로그($state, '🏝️ ' . $name . ' 무인도 · 다음 턴 쉽');
      return;
    }
    if ($type === 'park') {
      burumable_로그($state, '🅿️ ' . $name . ' 무료 주차');
      return;
    }
    if ($type === 'gotojail') {
      $jail = burumable_특수칸('jail');
      $state['players'][$pi]['pos'] = $jail;
      $state['players'][$pi]['jail'] = 1;
      burumable_로그($state, '🚔 ' . $name . ' 무인도로 이동 · 다음 턴 쉽');
      return;
    }
    if ($type === 'tax') {
      $taxInfo = function_exists('burumable_세금액')
        ? burumable_세금액($state, $pi)
        : ['amt' => '0', 'lands' => 0, 'pct' => 10, 'exempt' => true];
      if (!empty($taxInfo['exempt'])) {
        burumable_로그($state, '💸 ' . $name . ' 세금 면제 · 땅 없음');
        burumable_홍보알림("🎲 부루마블\n💸 {$name} 세금 면제 · 땅 없음", 'burumable_tax');
        return;
      }
      $tax = burumable_냥($taxInfo['amt'] ?? '0');
      $lands = (int)($taxInfo['lands'] ?? 0);
      $pct = (int)($taxInfo['pct'] ?? 10);
      burumable_로그($state, '💸 ' . $name . ' 세금 ' . burumable_만표시($tax) . ' (땅 ' . $lands . '칸 · 부동산 ' . $pct . '%)');
      burumable_홍보알림("🎲 부루마블\n💸 {$name} 세금 " . burumable_만표시($tax) . "\n땅 {$lands}칸 · 부동산 {$pct}%", 'burumable_tax');
      burumable_지불($state, $pi, $tax, -1, '세금', true);
      return;
    }
    if ($type === 'chance') {
      burumable_찬스($state, $pi);
      return;
    }
    if ($type !== 'land') {
      return;
    }
    $tile = burumable_칸시세($state, $tile);
    $on = (string)($state['owner'][(string)$pos] ?? $state['owner'][$pos] ?? '');
    if ($on === '') {
      $state['pending'] = ['kind' => 'buy', 'tile' => $pos, 'price' => $tile['price'], 'at' => time()];
      burumable_로그($state, $name . ' → ' . $tile['name'] . ' (' . burumable_만표시($tile['price']) . ') 살까요? 20초');
      return;
    }
    if ($on === $name) {
      $lv = burumable_건물단계($state, $pos);
      if ($lv < burumable_건물최대()) {
        $next = burumable_다음건물($lv);
        $state['pending'] = ['kind' => 'build', 'tile' => $pos, 'at' => time()];
        burumable_로그($state, $name . ' 내 땅 ' . $tile['name'] . ' · ' . burumable_건물이름($next) . ' 지을까요? ' . burumable_초표시(burumable_건설제한초()));
        return;
      }
      burumable_로그($state, $name . ' 내 땅 ' . $tile['name'] . ' · 랜드마크');
      return;
    }
    $oi = burumable_닉찾기($state, $on);
    if ($oi < 0 || !empty($state['players'][$oi]['out'])) {
      $state['pending'] = ['kind' => 'buy', 'tile' => $pos, 'price' => $tile['price'], 'at' => time()];
      return;
    }
    $rent = burumable_통행료액($state, $pi, $tile, $on);
    if (function_exists('burumable_매수불가') && burumable_매수불가($state, $pos)) {
      burumable_로그($state, '🏰 ' . $name . ' → ' . $tile['name'] . ' 랜드마크 · 매수 불가 · 통행료 ' . burumable_만표시($rent));
      burumable_통행료적용($state, $pi, $pos);
      return;
    }
    $price = burumable_인수금액($state, $pos);
    $canForceTake = function_exists('burumable_강제인수가능') && burumable_강제인수가능($state, $pos);
    $canTake = burumable_땅더살수있나($state, $name)
      && burumable_비교($price, '1') >= 0
      && burumable_비교(burumable_포인트양수($name), $price) >= 0;
    $canForce = burumable_강제구매남음($state, $pi) > 0
      && burumable_땅더살수있나($state, $name)
      && burumable_비교($price, '1') >= 0
      && burumable_비교(burumable_포인트양수($name), $price) >= 0;
    if ($canTake || $canForce) {
      $state['pending'] = [
        'kind' => 'takeover',
        'tile' => $pos,
        'owner' => $on,
        'from' => $name,
        'price' => $price,
        'rent' => $rent,
        'grace' => burumable_후발유예중인가($state, $pi),
        'wait' => $name,
        'at' => time(),
        'can_force_buy' => !$canForceTake && $canForce,
        'can_force_takeover' => $canForceTake,
      ];
      $sec = burumable_초표시(burumable_인수제한초());
      $refuseN = function_exists('burumable_인수거절수') ? burumable_인수거절수($state, $pos) : 0;
      $refuseMax = function_exists('burumable_인수거절한도') ? burumable_인수거절한도() : 1;
      if ($canForceTake) {
        $opt = '강제매수/통행료';
      } elseif (!empty($state['pending']['can_force_buy'])) {
        $opt = '강제구매권/인수/통행료';
      } else {
        $opt = '인수/통행료';
      }
      $refuseTxt = $canForceTake
        ? ' · 거절 ' . $refuseN . '/' . $refuseMax . ' 강제매수'
        : (' · 거절 ' . $refuseN . '/' . $refuseMax);
      $pct = burumable_인수퍼센트();
      burumable_로그($state, '🏠 ' . $name . ' → ' . $tile['name'] . ' ' . $opt . ' 시세 ' . $pct . '% ' . burumable_만표시($price) . $refuseTxt . ' · 통행료 ' . burumable_만표시($rent) . ' · ' . $sec);
      return;
    }
    burumable_통행료적용($state, $pi, $pos);
  }
}

if (!function_exists('burumable_도착기록')) {
  function burumable_도착기록(array &$state, int $pi): void {
    $name = (string)($state['players'][$pi]['name'] ?? '');
    $pos = (int)($state['players'][$pi]['pos'] ?? 0);
    $tile = burumable_칸시세($state, burumable_보드()[$pos] ?? ['id' => $pos, 'name' => '', 'type' => '']);
    $type = (string)($tile['type'] ?? '');
    $typeLabel = '나라';
    $hint = '';
    $event = '';
    if ($type === 'go') {
      $typeLabel = '출발';
      $hint = '지나갈 때 월급';
      $event = '출발 칸';
    } elseif ($type === 'chance') {
      $typeLabel = '찬스';
      $hint = burumable_초표시(burumable_찬스제한초()) . ' 안에 카드 고르기';
      $event = '찬스';
    } elseif ($type === 'tax') {
      $typeLabel = '세금';
      $hint = '보유 땅값·건물 ' . (function_exists('burumable_세금퍼센트') ? burumable_세금퍼센트() : 10) . '% · 땅 없으면 면제';
      $event = '세금';
    } elseif ($type === 'jail') {
      $typeLabel = '무인도';
      $hint = '다음 턴 쉼';
      $event = '무인도';
    } elseif ($type === 'park') {
      $typeLabel = '주차장';
      $hint = '효과 없음';
      $event = '무료 주차';
    } elseif ($type === 'gotojail') {
      $typeLabel = '무인도행';
      $hint = '무인도로 이동 · 다음 턴 쉼';
      $event = '무인도행';
    }
    $on = (string)($state['owner'][(string)$pos] ?? $state['owner'][$pos] ?? '');
    $pendKind = is_array($state['pending'] ?? null) ? (string)($state['pending']['kind'] ?? '') : '';
    $buy = $pendKind === 'buy';
    if ($type === 'land') {
      if ($buy) {
        $event = '구매 여부';
        $hint = '20초 안에 사거나 패스';
      } elseif ($pendKind === 'takeover') {
        $forceTake = function_exists('burumable_강제인수가능') && burumable_강제인수가능($state, $pos);
        $event = $forceTake ? '강제매수' : '인수 제안';
        $hint = burumable_초표시(burumable_인수제한초()) . ($forceTake ? ' 안에 강제매수 또는 통행료' : ' 안에 인수 또는 통행료');
      } elseif ($pendKind === 'takeover_offer') {
        $event = '인수 응답';
        $hint = burumable_초표시(burumable_인수제한초()) . ' 안에 받거나 거절';
      } elseif ($on === $name) {
        $event = '내 땅';
        if (is_array($state['pending'] ?? null) && (($state['pending']['kind'] ?? '') === 'build')) {
          $hint = burumable_초표시(burumable_건설제한초()) . ' 안에 짓거나 패스';
        }
      } elseif ($on !== '') {
        $event = '통행료';
      }
    }
    $rentFmt = '';
    if ($type === 'land') {
      $toll = $on !== '' ? burumable_렌트($state, $tile, $on) : burumable_냥($tile['rent'] ?? 0);
      $rentFmt = burumable_비교($toll, '0') > 0 ? burumable_만표시($toll) : '';
    }
    $price = burumable_냥($tile['price'] ?? 0);
    $state['arrive'] = [
      'nick' => $name,
      'id' => $pos,
      'name' => (string)($tile['name'] ?? ''),
      'type' => $type,
      'type_label' => $typeLabel,
      'hint' => $hint,
      'event' => $event,
      'owner' => $on,
      'color' => (string)($tile['color'] ?? ''),
      'price_fmt' => burumable_비교($price, '0') > 0 ? burumable_만표시($price) : '',
      'rent_fmt' => $rentFmt,
      'buy' => $buy,
      'at' => time(),
    ];
  }
}

if (!function_exists('burumable_이동')) {
  function burumable_이동(array &$state, int $pi, int $steps): void {
    if ($steps < 1 || !empty($state['players'][$pi]['out'])) {
      return;
    }
    $from = (int)$state['players'][$pi]['pos'];
    $n = burumable_칸수();
    $to = ($from + $steps) % $n;
    $state['players'][$pi]['pos'] = $to;
    if ($to < $from || ($from + $steps) >= $n) {
      $pay = burumable_월급($state);
      burumable_입금($state['players'][$pi]['name'], $pay);
      burumable_현금맞춤($state, $pi);
      burumable_로그($state, '💵 ' . $state['players'][$pi]['name'] . ' 월급 ' . burumable_만표시($pay));
      $left = (int)($state['players'][$pi]['grace_go'] ?? 0);
      if ($left > 0) {
        $state['players'][$pi]['grace_go'] = $left - 1;
        if ((int)$state['players'][$pi]['grace_go'] < 1) {
          burumable_로그($state, '🛡️ ' . $state['players'][$pi]['name'] . ' 후발 통행료 유예 종료');
        }
      }
    }
    $tile = burumable_보드()[$to];
    burumable_로그($state, $state['players'][$pi]['name'] . ' ' . $steps . '칸 → ' . $tile['name']);
    burumable_칸처리($state, $pi);
    burumable_도착기록($state, $pi);
  }
}

if (!function_exists('burumable_건설가능')) {
  /** @return list<array<string,mixed>> */
  function burumable_건설가능(array $state, string $nick): array {
    if (!empty($state['over']) || !burumable_내턴인가($state, $nick)) {
      return [];
    }
    $pi = burumable_닉찾기($state, $nick);
    if ($pi < 0 || !empty($state['players'][$pi]['out'])) {
      return [];
    }
    $pend = $state['pending'] ?? null;
    if (!is_array($pend) || ($pend['kind'] ?? '') !== 'build') {
      return [];
    }
    $tid = (int)($pend['tile'] ?? -1);
    $board = burumable_보드();
    if (!isset($board[$tid]) || ($board[$tid]['type'] ?? '') !== 'land') {
      return [];
    }
    $on = (string)($state['owner'][(string)$tid] ?? $state['owner'][$tid] ?? '');
    if ($on !== $nick) {
      return [];
    }
    $lv = burumable_건물단계($state, $tid);
    if ($lv >= burumable_건물최대()) {
      return [];
    }
    $tile = burumable_칸시세($state, $board[$tid]);
    $next = burumable_다음건물($lv);
    $cost = burumable_건물비($tile, $next);
    $cash = burumable_포인트양수($nick);
    return [[
      'id' => $tid,
      'name' => $tile['name'],
      'next' => burumable_건물이름($next),
      'cost' => $cost,
      'cost_fmt' => burumable_만표시($cost, true),
      'afford' => burumable_비교($cash, $cost) >= 0,
      'toll_fmt' => burumable_만표시(burumable_렌트(
        array_merge($state, ['build' => array_merge($state['build'] ?? [], [(string)$tid => $next])]),
        $tile,
        $nick
      ), true),
    ]];
  }
}

if (!function_exists('burumable_짓기')) {
  function burumable_짓기(array &$state, string $nick, int $tid): array {
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    $pend = $state['pending'] ?? null;
    $fromLand = is_array($pend) && (($pend['kind'] ?? '') === 'build');
    if (!empty($state['pending']) && !$fromLand) {
      return ['ok' => false, 'msg' => '먼저 땅을 사거나 찬스 카드를 골라 주세요.'];
    }
    if (!burumable_내턴인가($state, $nick)) {
      return ['ok' => false, 'msg' => '지금은 ' . burumable_현재닉($state) . ' 님 턴이에요.'];
    }
    burumable_턴제한리셋($state);
    $board = burumable_보드();
    if ($fromLand) {
      $tid = (int)($pend['tile'] ?? -1);
    }
    if (!isset($board[$tid]) || ($board[$tid]['type'] ?? '') !== 'land') {
      return ['ok' => false, 'msg' => '나라를 고르세요.'];
    }
    $tile = burumable_칸시세($state, $board[$tid]);
    $on = (string)($state['owner'][(string)$tid] ?? $state['owner'][$tid] ?? '');
    if ($on !== $nick) {
      return ['ok' => false, 'msg' => '내 땅이 아니에요.'];
    }
    if (!$fromLand && !burumable_그룹완전체($state, $nick, (string)($tile['group'] ?? ''))) {
      return ['ok' => false, 'msg' => '내 땅에 도착해야 지을 수 있어요.'];
    }
    $lv = burumable_건물단계($state, $tid);
    if ($lv >= burumable_건물최대()) {
      return ['ok' => false, 'msg' => '이미 랜드마크예요.'];
    }
    $next = burumable_다음건물($lv);
    $cost = burumable_건물비($tile, $next);
    $pi = burumable_닉찾기($state, $nick);
    if ($pi < 0) {
      return ['ok' => false, 'msg' => '참가자가 아니에요.'];
    }
    $swap = burumable_자동스왑($nick, $cost);
    if (!empty($swap['swapped']) && ($swap['msg'] ?? '') !== '') {
      burumable_로그($state, '💱 ' . $nick . ' ' . $swap['msg']);
    }
    if (burumable_비교(burumable_포인트양수($nick), $cost) < 0) {
      burumable_현금맞춤($state, $pi);
      return ['ok' => false, 'msg' => 부루마블_머니이름 . '가 없어요. (' . burumable_만표시($cost) . ')'];
    }
    if (!burumable_지불($state, $pi, $cost, -1, '건설', false)) {
      return ['ok' => false, 'msg' => 부루마블_머니이름 . '가 없어요. (' . burumable_만표시($cost) . ')'];
    }
    if (!isset($state['build']) || !is_array($state['build'])) {
      $state['build'] = [];
    }
    $state['build'][(string)$tid] = $next;
    $what = burumable_건물이름($next);
    $toll = burumable_렌트($state, $tile, $nick);
    burumable_로그($state, '🏗️ ' . $nick . ' ' . $tile['name'] . '에 ' . $what . ' 건설 · 통행료 ' . burumable_만표시($toll));
    if ($fromLand) {
      $state['pending'] = null;
      burumable_턴이어가기($state);
      burumable_종료확인($state);
    }
    return ['ok' => true];
  }
}

if (!function_exists('burumable_한턴')) {
  function burumable_한턴(array &$state, int $pi): void {
    unset($state['arrive']);
    if (!empty($state['over']) || !empty($state['players'][$pi]['out'])) {
      return;
    }
    if ((int)($state['players'][$pi]['jail'] ?? 0) > 0) {
      $state['players'][$pi]['jail'] = 0;
      burumable_로그($state, '🏝️ ' . $state['players'][$pi]['name'] . ' 무인도에서 쉼');
      return;
    }
    $d1 = random_int(1, 6);
    $d2 = random_int(1, 6);
    $state['dice'] = [$d1, $d2];
    $double = ($d1 === $d2);
    $streak = $double ? ((int)($state['doubles'] ?? 0) + 1) : 0;
    $state['doubles'] = $streak;
    burumable_로그($state, '🎲 ' . $state['players'][$pi]['name'] . ' ' . $d1 . '+' . $d2 . ($double ? ' 더블' : ''));
    if ($double && $streak >= 3) {
      $jail = burumable_특수칸('jail');
      $state['players'][$pi]['pos'] = $jail;
      $state['players'][$pi]['jail'] = 1;
      $state['again'] = false;
      $state['doubles'] = 0;
      burumable_로그($state, '🏝️ ' . $state['players'][$pi]['name'] . ' 더블 3번 · 무인도');
      burumable_홍보알림("🎲 부루마블\n🏝️ {$state['players'][$pi]['name']} 더블 3번 · 무인도", 'burumable_jail');
      return;
    }
    burumable_이동($state, $pi, $d1 + $d2);
    if (!empty($state['players'][$pi]['jail']) || !empty($state['players'][$pi]['out'])) {
      $state['again'] = false;
      return;
    }
    $state['again'] = $double;
  }
}

if (!function_exists('burumable_내턴인가')) {
  function burumable_내턴인가(array $state, string $nick): bool {
    if (!empty($state['over']) || !empty($state['paused'])) {
      return false;
    }
    if (burumable_현재닉($state) !== $nick) {
      return false;
    }
    $pi = burumable_닉찾기($state, $nick);
    if ($pi >= 0 && !empty($state['players'][$pi]['out'])) {
      return false;
    }
    return true;
  }
}

if (!function_exists('burumable_구매실행')) {
  function burumable_구매실행(array &$state, string $nick, bool $buy): array {
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    $pend = $state['pending'] ?? null;
    $pendKind = is_array($pend) ? (string)($pend['kind'] ?? '') : '';
    if ($pendKind === 'takeover' || $pendKind === 'takeover_offer') {
      return burumable_인수실행($state, $nick, $buy, false);
    }
    if (!burumable_내턴인가($state, $nick)) {
      return ['ok' => false, 'msg' => burumable_현재닉($state) . ' 님 턴이에요.'];
    }
    if (!is_array($pend) || !in_array($pendKind, ['buy', 'build'], true)) {
      if ($pendKind === 'chance') {
        return ['ok' => false, 'msg' => '먼저 찬스 카드를 골라 주세요.'];
      }
      if ($pendKind === 'travel') {
        return ['ok' => false, 'msg' => '먼저 이동할 도시를 골라 주세요.'];
      }
      if ($pendKind === 'forcebuy') {
        if (!$buy) {
          return function_exists('burumable_강제구매고르기')
            ? burumable_강제구매고르기($state, $nick, -1, false)
            : ['ok' => false, 'msg' => '강제 구매할 도시를 골라 주세요.'];
        }
        return ['ok' => false, 'msg' => '강제 구매할 도시를 골라 주세요.'];
      }
      return ['ok' => false, 'msg' => '살 땅이 없어요.'];
    }
    $pi = (int)$state['cur'];
    $tid = (int)$pend['tile'];
    $tile = burumable_칸시세($state, burumable_보드()[$tid]);
    burumable_턴제한리셋($state);
    if (($pend['kind'] ?? '') === 'build') {
      if ($buy) {
        return burumable_짓기($state, $nick, $tid);
      }
      burumable_로그($state, '⏭ ' . $nick . ' ' . $tile['name'] . ' 건설 패스');
      $state['pending'] = null;
      burumable_턴이어가기($state);
      burumable_종료확인($state);
      return ['ok' => true];
    }
    if ($buy) {
      $price = burumable_냥($tile['price'] ?? 0);
      if (!burumable_지불($state, $pi, $price, -1, '구매', false)) {
        return ['ok' => false, 'msg' => 부루마블_머니이름 . '가 없어요. (' . burumable_만표시($price) . ')'];
      }
      $state['owner'][(string)$tid] = $nick;
      if (function_exists('burumable_인수거절리셋')) {
        burumable_인수거절리셋($state, $tid);
      }
      burumable_로그($state, '✅ ' . $nick . ' ' . $tile['name'] . ' 구매');
      burumable_홍보알림("🎲 부루마블\n✅ {$nick} {$tile['name']} 구매 " . burumable_만표시($price), 'burumable_buy');
      $state['pending'] = null;
      burumable_턴이어가기($state);
      burumable_종료확인($state);
      return ['ok' => true];
    }
    burumable_로그($state, '⏭ ' . $nick . ' ' . $tile['name'] . ' 패스');
    $state['pending'] = null;
    burumable_턴이어가기($state);
    burumable_종료확인($state);
    return ['ok' => true];
  }
}

if (!function_exists('burumable_인수통행료후진행')) {
  function burumable_인수통행료후진행(array &$state, int $tid, string $visitor = ''): void {
    if ($visitor === '') {
      $visitor = (string)($state['pending']['from'] ?? burumable_현재닉($state));
    }
    $pi = burumable_닉찾기($state, $visitor);
    $stay = burumable_현재닉($state);
    $state['pending'] = null;
    if ($pi >= 0) {
      burumable_통행료적용($state, $pi, $tid);
    }
    if (empty($state['pending']) && burumable_현재닉($state) === $stay) {
      burumable_턴이어가기($state);
    }
    burumable_종료확인($state);
  }
}

if (!function_exists('burumable_인수거절한도')) {
  function burumable_인수거절한도(): int {
    return defined('부루마블_인수거절_한도') ? max(0, (int)부루마블_인수거절_한도) : 1;
  }
}

if (!function_exists('burumable_인수거절수')) {
  function burumable_인수거절수(array $state, int $tid): int {
    $max = burumable_인수거절한도();
    $v = $state['takeover_refuse'][(string)$tid] ?? $state['takeover_refuse'][$tid] ?? 0;
    return max(0, min($max, (int)$v));
  }
}

if (!function_exists('burumable_강제인수가능')) {
  /** 거절 한도를 채운 땅. 다음 도착자는 주인 동의 없이 강제매수 */
  function burumable_강제인수가능(array $state, int $tid): bool {
    $max = burumable_인수거절한도();
    return $max > 0 && burumable_인수거절수($state, $tid) >= $max;
  }
}

if (!function_exists('burumable_인수거절기록')) {
  function burumable_인수거절기록(array &$state, int $tid): int {
    if (!isset($state['takeover_refuse']) || !is_array($state['takeover_refuse'])) {
      $state['takeover_refuse'] = [];
    }
    $max = burumable_인수거절한도();
    $n = burumable_인수거절수($state, $tid) + 1;
    if ($n > $max) {
      $n = $max;
    }
    $state['takeover_refuse'][(string)$tid] = $n;
    unset($state['takeover_refuse'][$tid]);
    return $n;
  }
}

if (!function_exists('burumable_인수거절리셋')) {
  function burumable_인수거절리셋(array &$state, int $tid): void {
    if (!isset($state['takeover_refuse']) || !is_array($state['takeover_refuse'])) {
      return;
    }
    unset($state['takeover_refuse'][(string)$tid], $state['takeover_refuse'][$tid]);
  }
}

if (!function_exists('burumable_인수실행')) {
  function burumable_인수실행(array &$state, string $nick, bool $ok, bool $auto = false): array {
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    $pend = $state['pending'] ?? null;
    $kind = is_array($pend) ? (string)($pend['kind'] ?? '') : '';
    if ($kind !== 'takeover' && $kind !== 'takeover_offer') {
      return ['ok' => false, 'msg' => '인수할 땅이 없어요.'];
    }
    $wait = trim((string)($pend['wait'] ?? ''));
    if ($wait === '') {
      $wait = $kind === 'takeover_offer'
        ? (string)($pend['owner'] ?? '')
        : burumable_현재닉($state);
    }
    if (!$auto && $wait !== $nick) {
      return ['ok' => false, 'msg' => '지금은 ' . $wait . ' 님 차례예요.'];
    }
    $tid = (int)($pend['tile'] ?? -1);
    $visitor = (string)($pend['from'] ?? burumable_현재닉($state));
    $owner = (string)($pend['owner'] ?? '');
    $price = burumable_냥($pend['price'] ?? 0);
    $board = burumable_보드();
    $tile = burumable_칸시세($state, $board[$tid] ?? ['id' => $tid, 'name' => '', 'type' => 'land']);
    $onNow = (string)($state['owner'][(string)$tid] ?? $state['owner'][$tid] ?? '');
    if (function_exists('burumable_매수불가') && burumable_매수불가($state, $tid)) {
      burumable_턴제한리셋($state);
      burumable_인수통행료후진행($state, $tid, $visitor);
      return ['ok' => true, 'msg' => '랜드마크는 매수할 수 없어요. 통행료만 내요.'];
    }
    $forceTake = function_exists('burumable_강제인수가능') && burumable_강제인수가능($state, $tid);
    $pct = burumable_인수퍼센트();
    $lv = burumable_건물단계($state, $tid);
    $bld = burumable_건물이름($lv);
    if ($kind === 'takeover') {
      if (!$ok) {
        burumable_턴제한리셋($state);
        burumable_인수통행료후진행($state, $tid, $visitor);
        return ['ok' => true];
      }
      if (!$forceTake) {
        if ($onNow !== $owner || $owner === '' || $owner === $visitor) {
          burumable_턴제한리셋($state);
          $state['pending'] = null;
          burumable_턴이어가기($state);
          burumable_종료확인($state);
          return ['ok' => false, 'msg' => '인수할 수 없는 땅이에요.'];
        }
        if (!burumable_땅더살수있나($state, $visitor)) {
          return ['ok' => false, 'msg' => '인수할 수 없는 땅이에요.'];
        }
        $swap = burumable_자동스왑($visitor, $price);
        if (!empty($swap['swapped']) && ($swap['msg'] ?? '') !== '') {
          burumable_로그($state, '💱 ' . $visitor . ' ' . $swap['msg']);
        }
        if (burumable_비교(burumable_포인트양수($visitor), $price) < 0) {
          return ['ok' => false, 'msg' => '인수 비용이 부족해요. (' . burumable_만표시($price) . ') 통행료를 내 주세요.'];
        }
        $state['pending'] = [
          'kind' => 'takeover_offer',
          'tile' => $tid,
          'owner' => $owner,
          'from' => $visitor,
          'price' => $price,
          'rent' => $pend['rent'] ?? '0',
          'grace' => !empty($pend['grace']),
          'wait' => $owner,
          'at' => time(),
        ];
        $refuseN = function_exists('burumable_인수거절수') ? burumable_인수거절수($state, $tid) : 0;
        $refuseMax = function_exists('burumable_인수거절한도') ? burumable_인수거절한도() : 1;
        $refuseLeft = max(0, $refuseMax - $refuseN);
        burumable_로그($state, '🏠 ' . $visitor . ' → ' . $owner . ' ' . $tile['name'] . ' 인수 제안 시세 ' . $pct . '% ' . burumable_만표시($price) . ($lv > 0 ? (' · ' . $bld) : '') . ' · 거절 남은 ' . $refuseLeft . '회');
        burumable_홍보알림(
          "🎲 부루마블\n🏠 {$visitor} → {$owner}\n{$tile['name']} 인수 제안 시세 {$pct}% " . burumable_만표시($price)
            . ($lv > 0 ? (" · {$bld}") : '')
            . "\n받으면 땅이 넘어가고, 거절하면 통행료만 · 거절 남은 {$refuseLeft}회",
          'burumable_takeover'
        );
        return ['ok' => true];
      }
    } elseif (!$ok) {
      $n = function_exists('burumable_인수거절기록') ? burumable_인수거절기록($state, $tid) : 0;
      $max = function_exists('burumable_인수거절한도') ? burumable_인수거절한도() : 1;
      $extra = ($n >= $max) ? ' · 다음 도착 시 강제매수' : '';
      burumable_로그($state, '🙅 ' . $owner . ' ' . $tile['name'] . ' 인수 거절 (' . $n . '/' . $max . ')' . $extra);
      burumable_턴제한리셋($state);
      burumable_인수통행료후진행($state, $tid, $visitor);
      return ['ok' => true];
    }
    if ($onNow !== $owner || $owner === '' || $owner === $visitor) {
      burumable_턴제한리셋($state);
      burumable_인수통행료후진행($state, $tid, $visitor);
      return ['ok' => true, 'msg' => '강제매수할 수 없는 땅이라 통행료로 처리했어요.'];
    }
    if (!burumable_땅더살수있나($state, $visitor)) {
      burumable_로그($state, '🏠 ' . $visitor . ' 강제매수 무효 · 통행료');
      burumable_턴제한리셋($state);
      burumable_인수통행료후진행($state, $tid, $visitor);
      return ['ok' => true, 'msg' => '강제매수할 수 없어 통행료로 처리했어요.'];
    }
    $vi = burumable_닉찾기($state, $visitor);
    $oi = burumable_닉찾기($state, $owner);
    if ($vi < 0 || $oi < 0) {
      burumable_턴제한리셋($state);
      burumable_인수통행료후진행($state, $tid, $visitor);
      return ['ok' => true, 'msg' => '강제매수할 수 없어 통행료로 처리했어요.'];
    }
    $swap = burumable_자동스왑($visitor, $price);
    if (!empty($swap['swapped']) && ($swap['msg'] ?? '') !== '') {
      burumable_로그($state, '💱 ' . $visitor . ' ' . $swap['msg']);
    }
    $payLabel = $forceTake ? '강제매수' : '인수';
    if (burumable_비교(burumable_포인트양수($visitor), $price) < 0 || !burumable_지불($state, $vi, $price, $oi, $payLabel, false)) {
      if ($kind === 'takeover' && !$auto) {
        return ['ok' => false, 'msg' => $payLabel . ' 비용이 부족해요. (' . burumable_만표시($price) . ') 통행료를 내 주세요.'];
      }
      burumable_로그($state, '🏠 ' . $visitor . ' ' . $payLabel . ' 비용 부족 · 통행료');
      burumable_턴제한리셋($state);
      burumable_인수통행료후진행($state, $tid, $visitor);
      return ['ok' => true, 'msg' => $payLabel . ' 비용이 부족해 통행료로 처리했어요.'];
    }
    $state['owner'][(string)$tid] = $visitor;
    if (function_exists('burumable_인수거절리셋')) {
      burumable_인수거절리셋($state, $tid);
    }
    $state['pending'] = null;
    burumable_턴제한리셋($state);
    if ($forceTake) {
      burumable_로그($state, '⚡ ' . $visitor . ' ' . $tile['name'] . ' 강제매수 · 시세 ' . $pct . '% · ' . $owner . '에게 ' . burumable_만표시($price) . ($lv > 0 ? (' · ' . $bld) : '') . ' · 거절 한도');
      burumable_홍보알림(
        "🎲 부루마블\n⚡ {$visitor} {$tile['name']} 강제매수\n시세 {$pct}% 웃돈 · {$owner}에게 " . burumable_만표시($price)
          . ($lv > 0 ? (" · {$bld}") : '')
          . "\n거절 한도 · 주인 동의 없음",
        'burumable_takeover_ok'
      );
    } else {
      burumable_로그($state, '🏠 ' . $visitor . ' ' . $tile['name'] . ' 인수 · 시세 ' . $pct . '% · ' . $owner . '에게 ' . burumable_만표시($price) . ($lv > 0 ? (' · ' . $bld) : ''));
      burumable_홍보알림(
        "🎲 부루마블\n🏠 {$visitor} {$tile['name']} 인수\n시세 {$pct}% · {$owner}에게 " . burumable_만표시($price)
          . ($lv > 0 ? (" · {$bld}") : ''),
        'burumable_takeover_ok'
      );
    }
    burumable_턴이어가기($state);
    burumable_종료확인($state);
    return ['ok' => true];
  }
}

if (!function_exists('burumable_주사위실행')) {
  function burumable_주사위실행(array &$state, string $nick): array {
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    if (!empty($state['pending'])) {
      return ['ok' => false, 'msg' => '땅을 사거나 건물을 짓거나 찬스·인수에 답하거나 패스해 주세요.'];
    }
    if (!burumable_내턴인가($state, $nick)) {
      return ['ok' => false, 'msg' => '지금은 ' . burumable_현재닉($state) . ' 님 턴이에요.'];
    }
    $pi = (int)$state['cur'];
    if (!empty($state['players'][$pi]['out'])) {
      return ['ok' => false, 'msg' => '이미 파산했어요.'];
    }
    $state['players'][$pi]['skip_roll'] = 0;
    burumable_턴제한리셋($state);
    $state['acted'] = true;
    $stayNick = burumable_현재닉($state);
    $stayCur = (int)$state['cur'];
    burumable_한턴($state, $pi);
    if (burumable_현재닉($state) === $stayNick && (int)$state['cur'] === $stayCur) {
      burumable_턴이어가기($state);
    }
    burumable_종료확인($state);
    return ['ok' => true];
  }
}

if (!function_exists('burumable_양도수수료액')) {
  /** 양도 땅값의 10% 수수료(5%씩 소멸). @return array{fee:string,vault:string,lotto:string,pct:int,vault_pct:int,lotto_pct:int} */
  function burumable_양도수수료액($price): array {
    $vaultPct = defined('부루마블_양도금고_퍼센트') ? max(0, (int)부루마블_양도금고_퍼센트) : 5;
    $lottoPct = defined('부루마블_양도로또_퍼센트') ? max(0, (int)부루마블_양도로또_퍼센트) : 5;
    $pct = defined('부루마블_양도수수료_퍼센트') ? max(0, (int)부루마블_양도수수료_퍼센트) : ($vaultPct + $lottoPct);
    $vault = burumable_몫($price, $vaultPct, 100);
    $lotto = burumable_몫($price, $lottoPct, 100);
    return [
      'fee' => burumable_더하기($vault, $lotto),
      'vault' => $vault,
      'lotto' => $lotto,
      'pct' => $pct,
      'vault_pct' => $vaultPct,
      'lotto_pct' => $lottoPct,
    ];
  }
}

if (!function_exists('burumable_양도수수료넣기')) {
  /** 예전엔 금고·로또에 넣던 5%씩. 지금은 부루마블 머니에서 빠진 뒤 소멸. */
  function burumable_양도수수료넣기(string $vault, string $lotto, string $nick, string $tag = '부루마블양도'): void {
    unset($vault, $lotto, $nick, $tag);
  }
}

if (!function_exists('burumable_땅양도')) {
  function burumable_땅양도(array &$state, string $from, string $to, int $tid): array {
    return ['ok' => false, 'msg' => '땅 양도는 지금은 할 수 없어요.'];
    $from = trim($from);
    $to = trim($to);
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    if ($from === '' || $to === '') {
      return ['ok' => false, 'msg' => '받는 사람을 고르세요.'];
    }
    if ($from === $to) {
      return ['ok' => false, 'msg' => '자기 자신에게는 양도할 수 없어요.'];
    }
    $pendGive = $state['pending'] ?? null;
    if (is_array($pendGive) && (string)($pendGive['kind'] ?? '') === 'distress') {
      return ['ok' => false, 'msg' => '빚을 먼저 정리해 주세요. 땅을 팔거나 파산해 주세요.'];
    }
    $fi = burumable_닉찾기($state, $from);
    $ti = burumable_닉찾기($state, $to);
    if ($fi < 0) {
      return ['ok' => false, 'msg' => '참가자가 아니에요.'];
    }
    if ($ti < 0) {
      return ['ok' => false, 'msg' => '그 친구는 이번 판에 없어요.'];
    }
    if (!empty($state['players'][$fi]['out'])) {
      return ['ok' => false, 'msg' => '이번 판을 포기한 뒤에는 땅을 넘길 수 없어요.'];
    }
    if (!empty($state['players'][$ti]['out'])) {
      return ['ok' => false, 'msg' => '이번 판을 포기한 친구에게는 넘길 수 없어요.'];
    }
    $board = burumable_보드();
    if (!isset($board[$tid]) || ($board[$tid]['type'] ?? '') !== 'land') {
      return ['ok' => false, 'msg' => '나라를 고르세요.'];
    }
    $on = (string)($state['owner'][(string)$tid] ?? $state['owner'][$tid] ?? '');
    if ($on !== $from) {
      return ['ok' => false, 'msg' => '내 땅이 아니에요.'];
    }
    if (!burumable_땅더살수있나($state, $to)) {
      return ['ok' => false, 'msg' => '그 친구에게 넘길 수 없어요.'];
    }
    $pend = $state['pending'] ?? null;
    if (is_array($pend) && (int)($pend['tile'] ?? -1) === $tid) {
      return ['ok' => false, 'msg' => '이 칸은 지금 고르는 중이라 넘길 수 없어요.'];
    }
    $tile = burumable_칸시세($state, $board[$tid]);
    $feeInfo = burumable_양도수수료액($tile['price'] ?? 0);
    $fee = $feeInfo['fee'];
    if (burumable_비교($fee, '1') >= 0) {
      if (!burumable_지불($state, $fi, $fee, -1, '양도수수료', false)) {
        return ['ok' => false, 'msg' => '양도 수수료가 부족해요. 땅값 ' . (int)$feeInfo['pct'] . '% ' . burumable_만표시($fee)];
      }
      burumable_양도수수료넣기($feeInfo['vault'], $feeInfo['lotto'], $from);
    }
    $cut = defined('부루마블_양도땅값삭감_퍼센트') ? max(0, min(99, (int)부루마블_양도땅값삭감_퍼센트)) : 20;
    $id = (string)$tid;
    $keep = (int)($state['land_keep'][$id] ?? 100);
    if ($keep < 1 || $keep > 100) {
      $keep = 100;
    }
    $newKeep = (int)burumable_몫((string)$keep, 100 - $cut, 100);
    if ($newKeep < 1) {
      $newKeep = 1;
    }
    if (!isset($state['land_keep']) || !is_array($state['land_keep'])) {
      $state['land_keep'] = [];
    }
    if (!isset($state['land_keep_at']) || !is_array($state['land_keep_at'])) {
      $state['land_keep_at'] = [];
    }
    $state['land_keep'][$id] = $newKeep;
    $state['land_keep_at'][$id] = max(0, (int)($state['turn'] ?? 0));
    $after = burumable_칸시세($state, $board[$tid]);
    $state['owner'][$id] = $to;
    if (function_exists('burumable_인수거절리셋')) {
      burumable_인수거절리셋($state, $tid);
    }
    $lv = burumable_건물단계($state, $tid);
    $bld = burumable_건물이름($lv);
    $로그 = '🤝 ' . $from . ' → ' . $to . ' ' . $tile['name'] . ' 양도';
    if ($lv > 0) {
      $로그 .= ' · ' . $bld;
    }
    if (burumable_비교($fee, '1') >= 0) {
      $로그 .= ' · 수수료 ' . burumable_만표시($fee) . ' 소멸';
    }
    if ($cut > 0) {
      $heal = defined('부루마블_양도땅값복구_퍼센트') ? max(0, (int)부루마블_양도땅값복구_퍼센트) : 5;
      $로그 .= ' · 땅값 -' . $cut . '% ' . burumable_만표시($after['price'] ?? 0);
      if ($heal > 0) {
        $로그 .= ' · 한 바퀴마다 +' . $heal . '%';
      }
    }
    burumable_로그($state, $로그);
    burumable_홍보알림(
      "🎲 부루마블\n🤝 {$from} → {$to}\n{$tile['name']} 양도"
        . ($lv > 0 ? (" · {$bld}") : '')
        . (burumable_비교($fee, '1') >= 0 ? ("\n수수료 " . burumable_만표시($fee) . ' 소멸 (5%씩)') : '')
        . ($cut > 0 ? ("\n땅값 -{$cut}% → " . burumable_만표시($after['price'] ?? 0) . ' · 한 바퀴마다 +' . (defined('부루마블_양도땅값복구_퍼센트') ? (int)부루마블_양도땅값복구_퍼센트 : 5) . '% 회복') : ''),
      'burumable_give'
    );
    $msg = $tile['name'] . '을(를) ' . $to . ' 님에게 넘겼어요.';
    if (burumable_비교($fee, '1') >= 0) {
      $msg .= ' 수수료 ' . burumable_만표시($fee) . ' 소멸';
    }
    if ($cut > 0) {
      $heal = defined('부루마블_양도땅값복구_퍼센트') ? max(0, (int)부루마블_양도땅값복구_퍼센트) : 5;
      $msg .= ' · 땅값 -' . $cut . '%';
      if ($heal > 0) {
        $left = (int)ceil((100 - $newKeep) / $heal);
        $msg .= ' · 한 바퀴마다 +' . $heal . '% (' . $left . '턴 후 원상)';
      }
    }
    return ['ok' => true, 'msg' => $msg];
  }
}

if (!function_exists('burumable_매각수수료액')) {
  /** 매각 기준액의 10% 수수료(5%씩 소멸). @return array{fee:string,vault:string,lotto:string,net:string,pct:int} */
  function burumable_매각수수료액($gross): array {
    $vaultPct = defined('부루마블_매각금고_퍼센트') ? max(0, (int)부루마블_매각금고_퍼센트) : 5;
    $lottoPct = defined('부루마블_매각로또_퍼센트') ? max(0, (int)부루마블_매각로또_퍼센트) : 5;
    $pct = defined('부루마블_매각수수료_퍼센트') ? max(0, (int)부루마블_매각수수료_퍼센트) : ($vaultPct + $lottoPct);
    $vault = burumable_몫($gross, $vaultPct, 100);
    $lotto = burumable_몫($gross, $lottoPct, 100);
    $fee = burumable_더하기($vault, $lotto);
    return [
      'fee' => $fee,
      'vault' => $vault,
      'lotto' => $lotto,
      'net' => burumable_빼기($gross, $fee),
      'pct' => $pct,
      'vault_pct' => $vaultPct,
      'lotto_pct' => $lottoPct,
    ];
  }
}

if (!function_exists('burumable_땅매각')) {
  function burumable_땅매각(array &$state, string $nick, int $tid, array $opt = []): array {
    $nick = trim($nick);
    $quiet = !empty($opt['quiet']);
    $skipDebt = !empty($opt['skip_debt']);
    $force = !empty($opt['force']);
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    $pi = burumable_닉찾기($state, $nick);
    if ($pi < 0) {
      return ['ok' => false, 'msg' => '참가자가 아니에요.'];
    }
    if (!empty($state['players'][$pi]['out'])) {
      return ['ok' => false, 'msg' => '이번 판을 포기한 뒤에는 땅을 팔 수 없어요.'];
    }
    $board = burumable_보드();
    if (!isset($board[$tid]) || ($board[$tid]['type'] ?? '') !== 'land') {
      return ['ok' => false, 'msg' => '나라를 고르세요.'];
    }
    $on = (string)($state['owner'][(string)$tid] ?? $state['owner'][$tid] ?? '');
    if ($on !== $nick) {
      return ['ok' => false, 'msg' => '내 땅이 아니에요.'];
    }
    $pend = $state['pending'] ?? null;
    if (!$force && is_array($pend) && (string)($pend['kind'] ?? '') !== 'distress' && (int)($pend['tile'] ?? -1) === $tid) {
      return ['ok' => false, 'msg' => '이 칸은 지금 고르는 중이라 팔 수 없어요.'];
    }
    $tile = burumable_칸시세($state, $board[$tid]);
    $lv = burumable_건물단계($state, $tid);
    $bld = burumable_건물이름($lv);
    $gross = function_exists('burumable_칸부동산') ? burumable_칸부동산($state, $tid) : burumable_냥($tile['price'] ?? 0);
    $feeInfo = burumable_매각수수료액($gross);
    $net = $feeInfo['net'];
    $id = (string)$tid;
    unset($state['owner'][$id], $state['owner'][$tid]);
    unset($state['build'][$id], $state['build'][$tid]);
    unset($state['land_keep'][$id], $state['land_keep'][$tid]);
    unset($state['land_keep_at'][$id], $state['land_keep_at'][$tid]);
    if (function_exists('burumable_인수거절리셋')) {
      burumable_인수거절리셋($state, $tid);
    }
    if (burumable_비교($feeInfo['fee'], '1') >= 0) {
      burumable_양도수수료넣기($feeInfo['vault'], $feeInfo['lotto'], $nick, '부루마블매각');
    }
    if (burumable_비교($net, '1') >= 0) {
      burumable_입금($nick, $net);
      burumable_현금맞춤($state, $pi);
      if (function_exists('지급로그')) {
        지급로그('부루마블-땅매각', $nick, $nick, $feeInfo['fee'], $net);
      }
    }
    if (!$quiet) {
      $로그 = '🏷️ ' . $nick . ' ' . $tile['name'] . ' 매각';
      if ($lv > 0) {
        $로그 .= ' · ' . $bld;
      }
      $로그 .= ' · 수령 ' . burumable_만표시($net);
      if (burumable_비교($feeInfo['fee'], '1') >= 0) {
        $로그 .= ' · 수수료 ' . burumable_만표시($feeInfo['fee']) . ' 소멸';
      }
      burumable_로그($state, $로그);
      burumable_홍보알림(
        "🎲 부루마블\n🏷️ {$nick} {$tile['name']} 매각"
          . ($lv > 0 ? (" · {$bld}") : '')
          . "\n수령 " . burumable_만표시($net)
          . (burumable_비교($feeInfo['fee'], '1') >= 0 ? (" · 수수료 " . burumable_만표시($feeInfo['fee']) . ' 소멸 (5%씩)') : ''),
        'burumable_sell'
      );
    }
    $sellMsg = $tile['name'] . '을(를) 팔았어요. 수령 ' . burumable_만표시($net);
    if (burumable_비교($feeInfo['fee'], '1') >= 0) {
      $sellMsg .= ' · 수수료 ' . burumable_만표시($feeInfo['fee']) . ' 소멸';
    }
    if (!$skipDebt && function_exists('burumable_빚청산시도')) {
      burumable_빚청산시도($state);
    }
    return ['ok' => true, 'msg' => $sellMsg, 'net' => $net, 'fee' => $feeInfo['fee']];
  }
}

if (!function_exists('burumable_기권환급액')) {
  /** 종료 정산용. 이번 판 포기는 매각(90%)을 씀. @return array{land:string,interest:string,total:string,pct:int} */
  function burumable_기권환급액(array $state, int $i): array {
    $pct = defined('부루마블_기권환급_퍼센트') ? max(0, (int)부루마블_기권환급_퍼센트) : 50;
    $land = burumable_몫(burumable_보유부동산($state, $i), $pct, 100);
    $interest = function_exists('burumable_누적이자') ? burumable_누적이자($state, $i) : '0';
    return [
      'land' => $land,
      'interest' => $interest,
      'total' => burumable_더하기($land, $interest),
      'pct' => $pct,
    ];
  }
}

if (!function_exists('burumable_보유매각합')) {
  /** 이번 판 포기 미리보기: 땅 전부 매각 실수령 + 누적 이자 */
  function burumable_보유매각합(array $state, int $i): array {
    $name = trim((string)($state['players'][$i]['name'] ?? ''));
    $gross = '0';
    $n = 0;
    if ($name !== '' && !empty($state['owner']) && is_array($state['owner'])) {
      foreach ($state['owner'] as $tid => $on) {
        if ((string)$on !== $name) {
          continue;
        }
        $n++;
        $gross = burumable_더하기($gross, function_exists('burumable_칸부동산') ? burumable_칸부동산($state, (int)$tid) : '0');
      }
    }
    $feeInfo = function_exists('burumable_매각수수료액')
      ? burumable_매각수수료액($gross)
      : ['fee' => '0', 'net' => $gross, 'pct' => 10];
    $interest = function_exists('burumable_누적이자') ? burumable_누적이자($state, $i) : '0';
    $land = (string)($feeInfo['net'] ?? $gross);
    return [
      'lands' => $n,
      'gross' => $gross,
      'fee' => (string)($feeInfo['fee'] ?? '0'),
      'land' => $land,
      'interest' => $interest,
      'total' => burumable_더하기($land, $interest),
      'pct' => (int)($feeInfo['pct'] ?? 10),
    ];
  }
}

if (!function_exists('burumable_전땅매각')) {
  /** 보유 땅을 전부 매각. 알림은 한 번에. */
  function burumable_전땅매각(array &$state, string $nick): array {
    $nick = trim($nick);
    $ids = [];
    if (!empty($state['owner']) && is_array($state['owner'])) {
      foreach ($state['owner'] as $tid => $on) {
        if ((string)$on === $nick) {
          $ids[] = (int)$tid;
        }
      }
    }
    $ids = array_values(array_unique($ids));
    $n = 0;
    $net = '0';
    $fee = '0';
    foreach ($ids as $tid) {
      $r = burumable_땅매각($state, $nick, $tid, ['quiet' => true, 'skip_debt' => true, 'force' => true]);
      if (empty($r['ok'])) {
        continue;
      }
      $n++;
      $net = burumable_더하기($net, $r['net'] ?? '0');
      $fee = burumable_더하기($fee, $r['fee'] ?? '0');
    }
    return ['ok' => true, 'count' => $n, 'net' => $net, 'fee' => $fee];
  }
}

if (!function_exists('burumable_이번판포기')) {
  /** 보유 땅 전부 매각 · 이번 판만 빠짐 · 다음 판부터 다시 참가 */
  function burumable_이번판포기(array &$state, string $nick): array {
    $nick = trim($nick);
    if (!empty($state['over'])) {
      return ['ok' => false, 'msg' => '이미 끝난 판이에요.'];
    }
    $pi = burumable_닉찾기($state, $nick);
    if ($pi < 0) {
      return ['ok' => false, 'msg' => '참가자가 아니에요.'];
    }
    if (!empty($state['players'][$pi]['out'])) {
      return ['ok' => true, 'msg' => '이미 이번 판을 포기했어요.'];
    }
    if (function_exists('burumable_결승확정')) {
      burumable_결승확정($state);
    }
    $sold = function_exists('burumable_전땅매각') ? burumable_전땅매각($state, $nick) : ['count' => 0, 'net' => '0', 'fee' => '0'];
    $int = function_exists('burumable_누적이자') ? burumable_누적이자($state, $pi) : '0';
    if (burumable_비교($int, '1') >= 0) {
      burumable_입금($nick, $int);
      $state['players'][$pi]['interest'] = '0';
      burumable_현금맞춤($state, $pi);
      if (function_exists('지급로그')) {
        지급로그('부루마블-땅이자', $nick, $nick, 0, $int);
      }
    } else {
      $state['players'][$pi]['interest'] = '0';
    }
    $pend = is_array($state['pending'] ?? null) ? $state['pending'] : null;
    if (is_array($pend) && (string)($pend['kind'] ?? '') === 'distress'
      && trim((string)($pend['from'] ?? '')) === $nick) {
      $amt = burumable_냥($pend['amt'] ?? 0);
      $to = (int)($pend['to'] ?? -1);
      $why = (string)($pend['why'] ?? '지불');
      $have = burumable_포인트양수($nick);
      $pay = $have;
      if (burumable_비교($amt, '1') >= 0 && burumable_비교($have, $amt) > 0) {
        $pay = $amt;
      }
      if (burumable_비교($pay, '1') >= 0) {
        burumable_출금($nick, $pay, true);
        if ($to >= 0 && empty($state['players'][$to]['out'])) {
          $toName = (string)$state['players'][$to]['name'];
          burumable_입금($toName, $pay);
          burumable_현금맞춤($state, $to);
          if ($why === '임대' && function_exists('burumable_통행료수령기록')) {
            $city = '';
            $pos = (int)($state['players'][$pi]['pos'] ?? -1);
            $board = burumable_보드();
            if (isset($board[$pos])) {
              $city = (string)($board[$pos]['name'] ?? '');
            }
            burumable_통행료수령기록($state, $toName, $nick, $pay, $city);
          }
        }
        burumable_현금맞춤($state, $pi);
        burumable_로그($state, '✅ ' . $nick . ' ' . burumable_빚이름($why) . ' ' . burumable_만표시($pay) . ' 납부');
      }
      $state['pending'] = null;
    } elseif (is_array($pend)) {
      $wait = trim((string)($pend['wait'] ?? $pend['from'] ?? burumable_현재닉($state)));
      if ($wait === $nick || burumable_현재닉($state) === $nick) {
        $state['pending'] = null;
      }
    }
    $state['players'][$pi]['out'] = true;
    $state['players'][$pi]['broke'] = false;
    $state['players'][$pi]['quit'] = true;
    if (function_exists('burumable_결승탈락기록')) {
      burumable_결승탈락기록($state, $nick, '포기');
    }
    $net = burumable_냥($sold['net'] ?? '0');
    $fee = burumable_냥($sold['fee'] ?? '0');
    $cnt = (int)($sold['count'] ?? 0);
    $로그 = $nick . ' 이번 판 포기';
    if ($cnt > 0) {
      $로그 .= ' · 땅 ' . $cnt . '칸 매각 ' . burumable_만표시($net);
      if (burumable_비교($fee, '1') >= 0) {
        $로그 .= ' · 수수료 ' . burumable_만표시($fee) . ' 소멸';
      }
    }
    if (burumable_비교($int, '1') >= 0) {
      $로그 .= ' · 이자 ' . burumable_만표시($int);
    }
    burumable_로그($state, $로그);
    burumable_홍보알림(
      "🎲 부루마블\n🏳 {$nick} 이번 판 포기"
        . ($cnt > 0 ? ("\n땅 {$cnt}칸 매각 " . burumable_만표시($net)) : '')
        . (burumable_비교($int, '1') >= 0 ? ("\n이자 " . burumable_만표시($int)) : '')
        . "\n다음 판부터 다시 참여",
      'burumable_quit'
    );
    if (burumable_현재닉($state) === $nick) {
      burumable_다음($state);
    }
    burumable_종료확인($state);
    $msg = '이번 판을 포기했어요. 다음 판부터 다시 참여할 수 있어요.';
    $got = burumable_더하기($net, $int);
    if (burumable_비교($got, '1') >= 0) {
      $msg = '땅 매각 ' . burumable_만표시($got) . ' · 이번 판 포기. 다음 판부터 참여할 수 있어요.';
    }
    return ['ok' => true, 'msg' => $msg];
  }
}

if (!function_exists('burumable_기권')) {
  function burumable_기권(array &$state, string $nick): array {
    return burumable_이번판포기($state, $nick);
  }
}

if (!function_exists('burumable_플레이어색')) {
  function burumable_플레이어색(array $state, string $nick): string {
    $i = burumable_닉찾기($state, $nick);
    if ($i < 0) {
      return '';
    }
    return (string)($state['players'][$i]['color'] ?? '');
  }
}

if (!function_exists('burumable_공개')) {
  function burumable_공개(?array $state, string $nick = ''): array {
    $view = $state ?: ['quote' => burumable_시세표()];
    if ($state) {
      burumable_시세보장($view);
    }
    $board = burumable_보드();
    $taxPct = function_exists('burumable_세금퍼센트') ? burumable_세금퍼센트() : 10;
    $taxHint = '보유 땅값·건물 ' . $taxPct . '% · 땅 없으면 면제';
    $myTaxFmt = '';
    if ($state && $nick !== '' && function_exists('burumable_세금액')) {
      $meI = burumable_닉찾기($state, $nick);
      if ($meI >= 0) {
        $myTax = burumable_세금액($state, $meI);
        $myTaxFmt = !empty($myTax['exempt']) ? '면제' : (burumable_비교($myTax['amt'] ?? '0', '0') > 0 ? burumable_만표시($myTax['amt'], true) : '');
      }
    }
    $groupNames = [];
    foreach ($board as $ot) {
      $g = (string)($ot['group'] ?? '');
      if ($g !== '') {
        $groupNames[$g][] = (string)($ot['name'] ?? '');
      }
    }
    $cells = [];
    foreach ($board as $t) {
      $xy = burumable_칸좌표((int)$t['id']);
      $on = '';
      if ($state) {
        $on = (string)($state['owner'][(string)$t['id']] ?? $state['owner'][$t['id']] ?? '');
      }
      $tokens = [];
      if ($state) {
        foreach ($state['players'] as $p) {
          if (!empty($p['out'])) {
            continue;
          }
          if ((int)$p['pos'] === (int)$t['id']) {
            $tokens[] = [
              'name' => $p['name'],
              'color' => $p['color'],
              'me' => ((string)$p['name'] === $nick),
            ];
          }
        }
      }
      $t = burumable_칸시세($view, $t);
      $lv = $state ? burumable_건물단계($state, (int)$t['id']) : 0;
      $toll = '0';
      $rentFmt = '';
      if (($t['type'] ?? '') === 'land') {
        if ($state && $on !== '') {
          $toll = burumable_렌트($state, $t, $on);
        } else {
          $toll = burumable_냥($t['rent'] ?? 0);
        }
        $rentFmt = burumable_비교($toll, '0') > 0 ? burumable_만표시($toll, true) : '';
      } elseif (($t['type'] ?? '') === 'tax') {
        $rentFmt = $myTaxFmt !== '' ? $myTaxFmt : ('부동산 ' . $taxPct . '%');
      }
      $price = burumable_냥($t['price'] ?? 0);
      $type = (string)($t['type'] ?? '');
      $typeLabel = '나라';
      $hint = '';
      if ($type === 'go') {
        $typeLabel = '출발';
        $hint = '지나갈 때 월급';
      } elseif ($type === 'chance') {
        $typeLabel = '찬스';
        $hint = burumable_초표시(function_exists('burumable_찬스제한초') ? burumable_찬스제한초() : 30) . ' 안에 카드 고르기';
      } elseif ($type === 'tax') {
        $typeLabel = '세금';
        $hint = $taxHint;
      } elseif ($type === 'jail') {
        $typeLabel = '무인도';
        $hint = '다음 턴 쉼';
      } elseif ($type === 'park') {
        $typeLabel = '주차장';
        $hint = '효과 없음';
      } elseif ($type === 'gotojail') {
        $typeLabel = '무인도행';
        $hint = '무인도로 이동 · 다음 턴 쉼';
      }
      $group = (string)($t['group'] ?? '');
      $groupDone = false;
      $rentBaseFmt = '';
      $rentSetFmt = '';
      $rentPenFmt = '';
      $rentHotFmt = '';
      $rentLmFmt = '';
      $penCostFmt = '';
      $hotCostFmt = '';
      $lmCostFmt = '';
      $giveFeeFmt = '';
      $giveFeePct = defined('부루마블_양도수수료_퍼센트') ? (int)부루마블_양도수수료_퍼센트 : 10;
      $giveCutPct = defined('부루마블_양도땅값삭감_퍼센트') ? (int)부루마블_양도땅값삭감_퍼센트 : 20;
      $giveHealPct = defined('부루마블_양도땅값복구_퍼센트') ? (int)부루마블_양도땅값복구_퍼센트 : 5;
      $keepPct = 100;
      $healLeft = 0;
      $sellPct = defined('부루마블_매각수수료_퍼센트') ? (int)부루마블_매각수수료_퍼센트 : 10;
      $sellGrossFmt = '';
      $sellFeeFmt = '';
      $sellNetFmt = '';
      if ($type === 'land') {
        $baseRent = burumable_냥($t['rent'] ?? 0);
        if (burumable_비교($baseRent, '0') > 0) {
          $rentBaseFmt = burumable_만표시($baseRent);
          $rentSetFmt = burumable_만표시(burumable_곱($baseRent, 2));
          $rentPenFmt = burumable_만표시(burumable_곱($baseRent, 5));
          $rentHotFmt = burumable_만표시(burumable_곱($baseRent, 12));
          $rentLmFmt = burumable_만표시(burumable_곱($baseRent, 25));
        }
        $penCost = burumable_건물비($t, 1);
        $hotCost = burumable_건물비($t, 2);
        $lmCost = burumable_건물비($t, 3);
        $penCostFmt = burumable_비교($penCost, '0') > 0 ? burumable_만표시($penCost) : '';
        $hotCostFmt = burumable_비교($hotCost, '0') > 0 ? burumable_만표시($hotCost) : '';
        $lmCostFmt = burumable_비교($lmCost, '0') > 0 ? burumable_만표시($lmCost) : '';
        $groupDone = $state && $on !== '' && burumable_그룹완전체($state, $on, $group);
        $giveFee = function_exists('burumable_양도수수료액') ? burumable_양도수수료액($price) : ['fee' => '0', 'pct' => 10];
        $giveFeeFmt = burumable_비교($giveFee['fee'], '1') >= 0 ? burumable_만표시($giveFee['fee']) : '';
        if ($state) {
          $keepPct = function_exists('burumable_칸유지퍼센트') ? burumable_칸유지퍼센트($state, $t['id']) : 100;
          if ($keepPct < 100 && $giveHealPct > 0) {
            $healLeft = (int)ceil((100 - $keepPct) / $giveHealPct);
          }
          $gross = function_exists('burumable_칸부동산') ? burumable_칸부동산($state, (int)$t['id']) : $price;
          $sell = function_exists('burumable_매각수수료액') ? burumable_매각수수료액($gross) : ['fee' => '0', 'net' => $gross, 'pct' => 10];
          $sellPct = (int)($sell['pct'] ?? 10);
          $sellGrossFmt = burumable_비교($gross, '1') >= 0 ? burumable_만표시($gross) : '';
          $sellFeeFmt = burumable_비교($sell['fee'], '1') >= 0 ? burumable_만표시($sell['fee']) : '';
          $sellNetFmt = burumable_비교($sell['net'], '1') >= 0 ? burumable_만표시($sell['net']) : '';
        }
      }
      $cells[] = [
        'id' => $t['id'],
        'name' => $t['name'],
        'flag' => ($type === 'land') ? burumable_국기((string)$t['name']) : '',
        'type' => $t['type'],
        'type_label' => $typeLabel,
        'hint' => $hint,
        'price' => $price,
        'price_fmt' => burumable_비교($price, '0') > 0 ? burumable_만표시($price, true) : '',
        'price_full' => burumable_비교($price, '0') > 0 ? burumable_만표시($price) : '',
        'rent_fmt' => $rentFmt,
        'rent_full' => ($type === 'land' && burumable_비교($toll, '0') > 0) ? burumable_만표시($toll) : $rentFmt,
        'rent_base_fmt' => $rentBaseFmt,
        'rent_set_fmt' => $rentSetFmt,
        'rent_pen_fmt' => $rentPenFmt,
        'rent_hot_fmt' => $rentHotFmt,
        'rent_lm_fmt' => $rentLmFmt,
        'pension_fmt' => $penCostFmt,
        'hotel_fmt' => $hotCostFmt,
        'landmark_fmt' => $lmCostFmt,
        'build' => $lv,
        'build_label' => $type === 'land' ? burumable_건물이름($lv) : '',
        'no_takeover' => $type === 'land' && $lv >= 3,
        'refuse_n' => ($type === 'land' && $state) ? (function_exists('burumable_인수거절수') ? burumable_인수거절수($state, (int)$t['id']) : 0) : 0,
        'refuse_max' => ($type === 'land') ? (function_exists('burumable_인수거절한도') ? burumable_인수거절한도() : 1) : 0,
        'force_takeover' => $type === 'land' && $state && function_exists('burumable_강제인수가능') && burumable_강제인수가능($state, (int)$t['id']),
        'color' => $t['color'],
        'col' => $xy[0],
        'row' => $xy[1],
        'owner' => $on,
        'owner_color' => ($state && $on !== '') ? burumable_플레이어색($state, $on) : '',
        'group' => $group,
        'group_names' => $groupNames[$group] ?? [],
        'group_done' => $groupDone,
        'give_fee_fmt' => $giveFeeFmt,
        'give_fee_pct' => $giveFeePct,
        'give_cut_pct' => $giveCutPct,
        'give_heal_pct' => $giveHealPct,
        'keep_pct' => $keepPct,
        'heal_left' => $healLeft,
        'sell_pct' => $sellPct,
        'sell_gross_fmt' => $sellGrossFmt,
        'sell_fee_fmt' => $sellFeeFmt,
        'sell_net_fmt' => $sellNetFmt,
        'tokens' => $tokens,
      ];
    }
    $plist = [];
    $myOrder = 0;
    if ($state) {
      foreach ($state['players'] as $i => $p) {
        $pn = (string)$p['name'];
        $lands = burumable_보유땅수($state, $pn);
        if ($pn === $nick) {
          $myOrder = $i + 1;
        }
        $live = burumable_포인트원문($pn);
        $cashShow = ($live !== '' && $live[0] === '-') ? '0' : burumable_만표시($live);
        $plist[] = [
          'name' => $pn,
          'cpu' => false,
          'me' => ($pn === $nick),
          'cash' => burumable_냥($live),
          'cash_fmt' => $cashShow,
          'asset_fmt' => burumable_만표시(burumable_자산($state, $i)),
          'pos' => (int)$p['pos'],
          'out' => !empty($p['out']),
          'broke' => !empty($p['broke']),
          'force_broke' => !empty($p['force_broke']),
          'quit' => !empty($p['quit']) || (!empty($p['out']) && empty($p['broke'])),
          'sinbul' => false,
          'color' => $p['color'],
          'lands' => $lands,
          'land_cap' => 0,
          'over_cap' => false,
          'grace_go' => max(0, (int)($p['grace_go'] ?? 0)),
          'rent_cut' => max(0, (int)($p['rent_cut'] ?? 0)),
          'force_buy' => max(0, (int)($p['force_buy'] ?? 0)),
          'turn' => ((int)$state['cur'] === $i),
          'order' => $i + 1,
          'interest' => burumable_누적이자($state, $i),
          'interest_fmt' => burumable_만표시(burumable_누적이자($state, $i)),
          'found_shard' => max(0, (int)($p['found_shard'] ?? 0)),
          'found_eun' => max(0, (int)($p['found_eun'] ?? 0)),
          'skip_roll' => max(0, (int)($p['skip_roll'] ?? 0)),
        ];
      }
      usort($plist, static function ($a, $b) {
        $ab = !empty($a['out']) ? 1 : 0;
        $bb = !empty($b['out']) ? 1 : 0;
        if ($ab !== $bb) {
          return $ab - $bb;
        }
        return ((int)($a['order'] ?? 0)) - ((int)($b['order'] ?? 0));
      });
    }
    $pend = null;
    $curName = $state ? burumable_현재닉($state) : '';
    $waitFor = $curName;
    if ($state && is_array($state['pending'] ?? null)) {
      $w = trim((string)($state['pending']['wait'] ?? ''));
      if ($w !== '') {
        $waitFor = $w;
      }
      $kind = (string)($state['pending']['kind'] ?? 'buy');
      $tid = (int)($state['pending']['tile'] ?? -1);
      $tile = burumable_칸시세($state, $board[$tid] ?? ['id' => $tid, 'name' => '찬스', 'type' => 'chance']);
      if ($kind === 'distress') {
        $from = (string)($state['pending']['from'] ?? $waitFor);
        $amt = burumable_냥($state['pending']['amt'] ?? 0);
        $have = burumable_포인트양수($from);
        $short = burumable_비교($have, $amt) >= 0 ? '0' : burumable_빼기($amt, $have);
        $whyLabel = (string)($state['pending']['why_label'] ?? burumable_빚이름((string)($state['pending']['why'] ?? '')));
        $assets = burumable_빚자산목록($state, $from);
        $pend = [
          'kind' => 'distress',
          'tile' => $tid,
          'name' => (string)($tile['name'] ?? $whyLabel),
          'flag' => burumable_국기((string)($tile['name'] ?? '')),
          'why' => (string)($state['pending']['why'] ?? ''),
          'why_label' => $whyLabel,
          'amt' => $amt,
          'amt_fmt' => burumable_만표시($amt),
          'cash_fmt' => burumable_만표시($have),
          'short_fmt' => burumable_만표시($short),
          'can_pay' => burumable_비교($have, $amt) >= 0,
          'can_settle' => burumable_비교($have, $amt) < 0 && $assets === [],
          'to_name' => (string)($state['pending']['to_name'] ?? ''),
          'from' => $from,
          'price_fmt' => burumable_만표시($amt),
          'rent_fmt' => '',
          'next' => '',
          'build_label' => '',
          'mine' => ($waitFor === $nick),
          'assets' => $assets,
        ];
      } elseif ($kind === 'chance') {
        $pend = [
          'kind' => 'chance',
          'tile' => $tid,
          'name' => (string)($tile['name'] ?? '찬스'),
          'flag' => '',
          'price_fmt' => '',
          'rent_fmt' => '',
          'next' => '',
          'build_label' => '',
          'mine' => ($waitFor === $nick),
          'cards' => function_exists('burumable_찬스카드공개')
            ? burumable_찬스카드공개((array)($state['pending']['cards'] ?? []))
            : [],
        ];
      } elseif ($kind === 'travel') {
        $pend = [
          'kind' => 'travel',
          'tile' => $tid,
          'name' => '세계여행',
          'flag' => '',
          'price_fmt' => '',
          'rent_fmt' => '',
          'next' => '',
          'build_label' => '',
          'mine' => ($waitFor === $nick),
          'cities' => function_exists('burumable_도시목록공개')
            ? burumable_도시목록공개($state, $nick)
            : [],
        ];
      } elseif ($kind === 'forcebuy') {
        $pend = [
          'kind' => 'forcebuy',
          'tile' => $tid,
          'name' => '도시 강제구매',
          'flag' => '',
          'price_fmt' => '',
          'rent_fmt' => '',
          'next' => '',
          'build_label' => '',
          'mine' => ($waitFor === $nick),
          'cities' => function_exists('burumable_강제구매대상목록')
            ? burumable_강제구매대상목록($state, $waitFor !== '' ? $waitFor : $nick)
            : [],
        ];
      } elseif ($kind === 'takeover' || $kind === 'takeover_offer') {
        $lv = burumable_건물단계($state, $tid);
        $price = burumable_냥($state['pending']['price'] ?? burumable_인수금액($state, $tid));
        $rent = burumable_냥($state['pending']['rent'] ?? 0);
        $fromNick = (string)($state['pending']['from'] ?? $waitFor);
        $fromI = burumable_닉찾기($state, $fromNick);
        $forceTake = function_exists('burumable_강제인수가능') && burumable_강제인수가능($state, $tid);
        $refuseN = function_exists('burumable_인수거절수') ? burumable_인수거절수($state, $tid) : 0;
        $refuseMax = function_exists('burumable_인수거절한도') ? burumable_인수거절한도() : 1;
        $pend = [
          'kind' => $kind,
          'tile' => $tid,
          'name' => $tile['name'],
          'flag' => burumable_국기((string)$tile['name']),
          'price_fmt' => burumable_만표시($price),
          'rent_fmt' => burumable_만표시($rent),
          'next' => '',
          'build_label' => burumable_건물이름($lv),
          'owner' => (string)($state['pending']['owner'] ?? ''),
          'from' => $fromNick,
          'pct' => burumable_인수퍼센트(),
          'grace' => !empty($state['pending']['grace']),
          'mine' => ($waitFor === $nick),
          'can_takeover' => $kind === 'takeover' && $lv < 3 && !$forceTake,
          'can_force_buy' => $kind === 'takeover' && $lv < 3 && !$forceTake && $fromI >= 0 && burumable_강제구매남음($state, $fromI) > 0,
          'can_force_takeover' => $kind === 'takeover' && $lv < 3 && $forceTake,
          'no_takeover' => $lv >= 3,
          'refuse_n' => $refuseN,
          'refuse_max' => $refuseMax,
          'refuse_left' => max(0, $refuseMax - $refuseN),
        ];
      } else {
        $lv = burumable_건물단계($state, $tid);
        $next = burumable_다음건물($lv);
        $cost = $kind === 'build' ? burumable_건물비($tile, $next) : burumable_냥($tile['price'] ?? 0);
        $pend = [
          'kind' => $kind,
          'tile' => $tid,
          'name' => $tile['name'],
          'flag' => burumable_국기((string)$tile['name']),
          'price_fmt' => burumable_만표시($kind === 'build' ? $cost : ($tile['price'] ?? 0)),
          'rent_fmt' => burumable_만표시($kind === 'build'
            ? burumable_렌트(array_merge($state, ['build' => array_merge($state['build'] ?? [], [(string)$tid => $next])]), $tile, $curName !== '' ? $curName : $nick)
            : burumable_렌트($state, $tile, $curName !== '' ? $curName : $nick)),
          'next' => $kind === 'build' ? burumable_건물이름($next) : '',
          'build_label' => $kind === 'build' ? burumable_건물이름($lv) : '',
          'mine' => ($waitFor === $nick),
          'can_buy' => true,
          'land_cap' => 0,
        ];
      }
    }
    $arrive = null;
    if ($state && is_array($state['arrive'] ?? null)) {
      $arrive = $state['arrive'];
      $arrive['mine'] = (($arrive['nick'] ?? '') === $nick);
    }
    $myTurn = $state && $nick !== '' && burumable_내턴인가($state, $nick) && empty($state['pending']);
    $pendMine = $pend && !empty($pend['mine']);
    $myInterest = '0';
    $myFoundShard = 0;
    $myFoundEun = 0;
    $giveup = ['land' => '0', 'interest' => '0', 'total' => '0', 'pct' => 10, 'lands' => 0, 'fee' => '0'];
    $meOut = false;
    if ($state && $nick !== '') {
      $mi = burumable_닉찾기($state, $nick);
      if ($mi >= 0) {
        $meOut = !empty($state['players'][$mi]['out']);
        $myInterest = burumable_누적이자($state, $mi);
        $myFoundShard = max(0, (int)($state['players'][$mi]['found_shard'] ?? 0));
        $myFoundEun = max(0, (int)($state['players'][$mi]['found_eun'] ?? 0));
        if (function_exists('burumable_보유매각합') && !$meOut) {
          $giveup = burumable_보유매각합($state, $mi);
        }
      }
    }
    $foundShard = $myFoundShard;
    $foundEun = $myFoundEun;
    $recruiting = $state && !empty($state['recruiting']);
    $recruitLeft = $recruiting ? burumable_모집남은초($state) : 0;
    $recruitOpen = $recruiting && $recruitLeft > 0;
    $recruitFee = $recruiting ? burumable_참가비액($state) : '0';
    $applied = $state && $nick !== '' && burumable_신청했나($state, $nick);
    $apps = [];
    if ($state && !empty($state['applicants']) && is_array($state['applicants'])) {
      $no = 0;
      foreach ($state['applicants'] as $a) {
        if (!is_array($a)) {
          continue;
        }
        $an = trim((string)($a['name'] ?? ''));
        if ($an === '') {
          continue;
        }
        $no++;
        $apps[] = [
          'no' => $no,
          'name' => $an,
          'me' => ($an === $nick),
          'fee_fmt' => burumable_만표시($a['fee'] ?? $recruitFee),
          'at' => (int)($a['at'] ?? 0),
          'at_fmt' => date('H:i', (int)($a['at'] ?? time())),
        ];
      }
    }
    $canRecruit = burumable_민호인가($nick) && !$recruitOpen && !($recruiting && $apps !== []);
    $canStart = burumable_민호인가($nick) && $recruiting && !$recruitOpen && $apps !== [];
    $canForceStart = burumable_민호인가($nick) && $recruiting && $apps !== [];
    $canCancelRecruit = burumable_민호인가($nick) && $recruiting;
    $canExtendRecruit = burumable_민호인가($nick) && $recruiting;
    $canRemindRecruit = $recruiting && $nick !== '';
    $myPt = $nick !== '' ? burumable_게임냥양수($nick) : '0';
    $myMoney = $nick !== '' ? burumable_포인트양수($nick) : '0';
    $canJoin = $recruitOpen && $nick !== '' && !$applied;
    $joinAfford = $canJoin && burumable_비교($myPt, $recruitFee) >= 0;
    $myTurn = $state && !$recruiting && $nick !== '' && burumable_내턴인가($state, $nick) && empty($state['pending']);
    $mySkip = 0;
    if ($state && $nick !== '') {
      $meI = burumable_닉찾기($state, $nick);
      if ($meI >= 0) {
        $mySkip = max(0, (int)($state['players'][$meI]['skip_roll'] ?? 0));
      }
    }
    return array_merge([
      'has' => $state !== null,
      'over' => $state ? !empty($state['over']) : false,
      'paused' => $state ? !empty($state['paused']) : false,
      'winner' => $state['winner'] ?? '',
      'prize' => function_exists('burumable_시상공개') ? burumable_시상공개($state) : null,
      'ranks' => function_exists('burumable_등수공개') ? burumable_등수공개($state) : [],
      'turn' => $state['turn'] ?? 0,
      'max_turn' => 부루마블_최대턴,
      'dice' => $state['dice'] ?? [0, 0],
      'log' => $state['log'] ?? [],
      'pending' => $pend,
      'arrive' => $arrive,
      'players' => $plist,
      'cells' => $cells,
      'my_turn' => $myTurn,
      'my_skip_roll' => $mySkip,
      'wait_for' => $waitFor,
      'wait_sinbul' => false,
      'min_land_fmt' => burumable_만표시(burumable_최저땅값($view)),
      'can_reset' => burumable_민호인가($nick),
      'can_replay' => burumable_민호인가($nick) && $state && empty($state['recruiting']) && (string)($state['end_kind'] ?? '') !== 'lobby' && (
        (empty($state['over']) && $plist !== [] && (bool)array_filter($plist, static function ($p) {
          return !empty($p['broke']) || !empty($p['force_broke']) || !empty($p['out']);
        }))
        || (!empty($state['over']) && (string)($state['end_kind'] ?? '') !== 'settle')
      ),
      'end_kind' => $state ? (string)($state['end_kind'] ?? '') : '',
      'me' => $nick,
      'my_order' => $myOrder,
      'count' => count($plist),
      'land_cap' => 0,
      'title' => defined('부루마블_이름') ? 부루마블_이름 : '부루마블',
      'money_name' => 부루마블_머니이름,
      'grace_pct' => defined('부루마블_후발통행료_퍼센트') ? (int)부루마블_후발통행료_퍼센트 : 50,
      'takeover_pct' => function_exists('burumable_인수퍼센트') ? burumable_인수퍼센트() : 150,
      'tax_pct' => function_exists('burumable_세금퍼센트') ? burumable_세금퍼센트() : 10,
      'refuse_max' => function_exists('burumable_인수거절한도') ? burumable_인수거절한도() : 1,
      'need_action' => $myTurn || $pendMine,
      'builds' => ($state && $nick !== '') ? burumable_건설가능($state, $nick) : [],
      'start_fmt' => burumable_만표시(burumable_시작금($view)),
      'grant_fmt' => burumable_만표시(burumable_지급시작금($view)),
      'grant_pct' => burumable_지급퍼센트($view),
      'grant_pct_fmt' => burumable_퍼센트표시(burumable_지급퍼센트($view)),
      'salary_fmt' => burumable_만표시(burumable_월급($view)),
      'interest_pct' => defined('부루마블_이자_퍼센트') ? (string)부루마블_이자_퍼센트 : '0.5',
      'interest_pension_pct' => defined('부루마블_이자_펜션_추가') ? (string)부루마블_이자_펜션_추가 : '0.1',
      'interest_hotel_pct' => defined('부루마블_이자_호텔_추가') ? (string)부루마블_이자_호텔_추가 : '0.4',
      'interest_landmark_pct' => defined('부루마블_이자_랜드마크_추가') ? (string)부루마블_이자_랜드마크_추가 : '1.0',
      'interest_fmt' => function_exists('burumable_이자안내') ? burumable_이자안내() : '0.5% · 펜션 +0.1% · 호텔 +0.4% · 랜드마크 +1.0%',
      'my_interest' => $myInterest,
      'my_interest_fmt' => burumable_만표시($myInterest),
      'can_claim_interest' => burumable_비교($myInterest, '1') >= 0,
      'my_rent_from' => ($state && $nick !== '' && function_exists('burumable_통행료수령공개'))
        ? burumable_통행료수령공개($state, $nick)
        : [],
      'found_shard' => $foundShard,
      'found_eun' => $foundEun,
      'my_found_shard' => $myFoundShard,
      'my_found_eun' => $myFoundEun,
      'giveup_pct' => (int)$giveup['pct'],
      'giveup_land_fmt' => burumable_만표시($giveup['land'] ?? '0'),
      'giveup_interest_fmt' => burumable_만표시($giveup['interest'] ?? '0'),
      'giveup_fee_fmt' => burumable_만표시($giveup['fee'] ?? '0'),
      'giveup_lands' => (int)($giveup['lands'] ?? 0),
      'giveup_total' => $giveup['total'] ?? '0',
      'giveup_total_fmt' => burumable_만표시($giveup['total'] ?? '0'),
      'can_giveup' => $state && $nick !== '' && empty($state['over']) && !$meOut,
      'snap_pct' => burumable_시세퍼센트읽기($view['quote'] ?? null),
      'snap_pct_fmt' => burumable_퍼센트표시(burumable_시세퍼센트읽기($view['quote'] ?? null)),
      'turn_left' => $state ? burumable_턴남은초($view) : 0,
      'turn_limit' => $state ? burumable_턴제한초($view) : burumable_턴제한초(),
      'skip_broke_at' => function_exists('burumable_미굴림파산회') ? burumable_미굴림파산회() : 5,
      'skip_fmt' => function_exists('burumable_미굴림안내') ? burumable_미굴림안내() : '1회 머니 10% · 2회 30% · 3회·4회 50% · 5회 강제 파산',
      'buy_left' => $state ? burumable_구매남은초($view) : 0,
      'buy_limit' => $state ? burumable_대기제한초($view) : burumable_구매제한초(),
      'build_limit' => burumable_건설제한초(),
      'chance_limit' => function_exists('burumable_찬스제한초') ? burumable_찬스제한초() : 30,
      'travel_limit' => function_exists('burumable_여행제한초') ? burumable_여행제한초() : 40,
      'again' => $state ? !empty($state['again']) : false,
      'recruiting' => $recruiting,
      'recruit_open' => $recruitOpen,
      'recruit_left' => $recruitLeft,
      'recruit_limit' => $recruiting ? (int)($state['recruit_sec'] ?? burumable_모집초()) : burumable_모집초(),
      'recruit_fee' => $recruitFee,
      'recruit_fee_fmt' => burumable_만표시($recruitFee),
      'recruit_fee_pct' => 1,
      'applicants' => $apps,
      'prize_table' => function_exists('burumable_시상표공개')
        ? burumable_시상표공개(function_exists('burumable_시상인원') ? burumable_시상인원($state) : count($apps))
        : ['n' => count($apps), 'tier' => 0, 'rows' => []],
      'applied' => $applied,
      'can_join' => $canJoin,
      'join_afford' => $joinAfford,
      'can_recruit' => $canRecruit,
      'can_start' => $canStart,
      'can_force_start' => $canForceStart,
      'can_cancel_recruit' => $canCancelRecruit,
      'can_extend_recruit' => $canExtendRecruit,
      'can_remind_recruit' => $canRemindRecruit,
      'my_pt_fmt' => burumable_만표시($myPt),
      'my_money' => $myMoney,
      'my_money_fmt' => burumable_만표시($myMoney),
    ]);
  }
}
