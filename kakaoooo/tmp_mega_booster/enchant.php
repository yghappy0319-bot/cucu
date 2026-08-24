<?php
/**
 * 무기 강화 웹 (info2.php .강화 로직을 UI화)
 * 접속 시 ?code=xxx 로 tb_member.code와 매칭해 인증
 * (탭 락 없음)
 *
 * URL:
 *   /page/enchant.php?code=XXXX
 *
 * 제공 액션 (AJAX JSON):
 *   action=status         — 내 냥/무기/강화/수호 등 조회
 *   action=buy            — 무기 구매 (weapon=랜덤|단소|활|마법)
 *   action=enhance        — 현재 무기 +1 강화 시도
 *   action=enhance_batch  — 강화 연속 times=1|100|500|1000|3000|5000 (1000=본방10 · 3000=본방30 · 5000=본방50)
 *   action=use_suho       — 강화 수호 전환(buy=0) / 구매(buy=1, 냥 1회 +1~+3)
 *                           buy=1 + auto_swap_half=1 이면 겜냥 부족 시 본방냥 50% 스왑 후 구매
 *   action=use_eunchong   — 은총/메가은총 사용 (tier=1|2, +14강↑, 은총 5분 · 메가 10분)
 *   action=trial_cast     — 맛보기 (단소/활 공격 1회/일, 마법 시전·보호 각 1회/일)
 *   action=history        — 내 강화 이력 (limit, 기본 15)
 *   action=swap_np_to_pt  — 본냥 1000 남기고 나머지→게임냥 (5% 삭제)
 */

// ----- 강화 설정 (info2.php와 동기화) -----
// 비용 = ceil(유효시총 × 구간비율) 게임냥 (강화비용_기본금액 · 시총 소프트캡). 표는 config include 후 enchant_refresh_cost_tables()로 채움.
$ENCHANT_할인적용 = false;  // 50% 할인
$ENCHANT_테스트모드 = false;
$ENCHANT_첫무기구매비용 = $ENCHANT_테스트모드 ? 100 : 100000;
$ENCHANT_강화최대 = 100;
$ENCHANT_강화비용표 = [];
$ENCHANT_성공확률표 = []; // 실제 확률은 강화_성공분자/분모 사용
$ENCHANT_분모가져오기 = function ($단계) {
    if (function_exists('강화_성공분모')) {
        return 강화_성공분모((int)$단계);
    }
    return 1000;
};

/** config.php 로드 후 호출 — 전체게임냥 비율 강화비·확률 표 갱신 */
function enchant_refresh_cost_tables(): void {
    global $ENCHANT_강화비용표, $ENCHANT_성공확률표, $ENCHANT_강화최대;
    $ENCHANT_강화최대 = function_exists('강화_최대') ? 강화_최대() : 100;
    if (function_exists('강화비용_단계표')) {
        $ENCHANT_강화비용표 = 강화비용_단계표();
    }
    $표 = [];
    for ($lv = 0; $lv < (int)$ENCHANT_강화최대; $lv++) {
        $표[$lv] = function_exists('강화_성공분자') ? 강화_성공분자($lv) : 1;
    }
    $ENCHANT_성공확률표 = $표;
}
$ENCHANT_무기맵 = ['단소' => '🪈단소', '활' => '🏹활', '마법' => '🪄마법'];

// ----- 유틸 -----
function enchant_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function enchant_auth($code) {
    $code = is_string($code) ? trim($code) : '';
    if ($code === '') return null;
    $esc = addslashes($code);
    $row = db_select("SELECT idx, name, CAST(point AS CHAR) AS point, IFNULL(newpoint, 0) AS newpoint, item, enhance, style, enhance_suho, 은총, 은총개수 FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    if (empty($row['name'])) return null;
    return $row;
}
function enchant_suho_count($nick) {
    $esc = addslashes($nick);
    $r = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE nick = '{$esc}' AND itemname = '수호' AND status = 0");
    return (int)($r['cnt'] ?? 0);
}
function enchant_eunchong_effect($닉, $회원 = null): array {
    $닉 = trim((string)$닉);
    if ($닉 !== '' && function_exists('강화_은총_효과')) {
        return 강화_은총_효과($닉);
    }
    $active = false;
    $end = '';
    $cnt = 0;
    if (is_array($회원)) {
        $cnt = function_exists('bag_은총_수량')
            ? bag_은총_수량($닉 !== '' ? $닉 : (string)($회원['name'] ?? ''))
            : (int)($회원['은총개수'] ?? 0);
        $end = trim((string)($회원['은총'] ?? ''));
        $active = function_exists('강화_은총_활성') ? 강화_은총_활성($end) : ($end !== '' && strtotime($end) > time());
    } elseif ($닉 !== '' && function_exists('bag_은총_수량')) {
        $cnt = bag_은총_수량($닉);
    }
    return [
        'active' => $active,
        'zeros' => $active ? 1 : 0,
        'cost_discount' => $active,
        'tier' => 1,
        'label' => '은총',
        'cnt' => $cnt,
        'end' => $end,
        'left_sec' => ($active && $end !== '') ? max(0, strtotime($end) - time()) : 0,
    ];
}
function enchant_suho_stack($nick, $회원 = null): int {
    $nick = trim((string)$nick);
    if ($nick !== '' && function_exists('bag_강화수호_수량')) {
        return bag_강화수호_수량($nick);
    }
    if (is_array($회원)) {
        return (int)($회원['enhance_suho'] ?? 0);
    }
    return 0;
}
function enchant_cost_next($현재강화, $전역할인, $은총비용할인 = false, $현재무기 = '', $닉 = '') {
    global $ENCHANT_강화비용표, $ENCHANT_테스트모드;
    $도전모드 = 강화20_도전모드_정보($현재무기, $현재강화, $닉);
    if ($도전모드) {
        return $도전모드['비용'];
    }
    return 강화비용_산출($현재강화, $ENCHANT_강화비용표, $전역할인, $은총비용할인, $ENCHANT_테스트모드);
}
/** @param bool|int $은총_or_zeros */
function enchant_success_rate_str($현재강화, $은총_or_zeros = false, $현재무기 = '', $닉 = '') {
    global $ENCHANT_성공확률표, $ENCHANT_분모가져오기, $ENCHANT_강화최대;
    if ($현재강화 >= $ENCHANT_강화최대) return '—';
    if (is_bool($은총_or_zeros)) {
        $zeros = $은총_or_zeros ? 1 : 0;
    } else {
        $zeros = max(0, (int)$은총_or_zeros);
    }
    $도전모드 = 강화20_도전모드_정보($현재무기, $현재강화, $닉);
    if ($도전모드) {
        return 강화20_도전모드_성공확률문구($zeros, $도전모드);
    }
    if (function_exists('강화_성공확률문구')) {
        return 강화_성공확률문구((int)$현재강화, $zeros);
    }
    $분자 = (int)($ENCHANT_성공확률표[$현재강화] ?? 1);
    $분모 = (int)$ENCHANT_분모가져오기($현재강화);
    if ($zeros > 0 && function_exists('강화_분모_제로제거')) {
        $분모 = 강화_분모_제로제거($분모, $zeros);
    } elseif ($zeros > 0) {
        $분모 = (int)max(1, floor($분모 / 10));
    }
    if ($분자 < 1) $분자 = 1;
    if ($분자 > $분모) $분자 = $분모;
    return rtrim(rtrim(number_format(($분자 / $분모) * 100, 6, '.', ''), '0'), '.') . '%';
}
function enchant_fmt_nyang($n) {
    if (function_exists('강화비용_표시')) {
        return 강화비용_표시($n, '게임냥');
    }
    if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($n, '게임냥');
    }
    if (function_exists('게임냥_안전표시')) {
        return 게임냥_안전표시($n, '게임냥');
    }
    $s = function_exists('냥_정수문자열') ? 냥_정수문자열($n) : (string)$n;
    return (function_exists('냥_숫자콤마') ? 냥_숫자콤마($s) : $s) . '게임냥';
}

/**
 * +1~10 → 10회 · +11~20 → 20회 · +21~30 → 30회 · +31~40 → 40회 …
 * (미만·무기없음은 0) — 무료 band 연속용
 */
function enchant_연속회수($enhance): int {
    $e = (int)$enhance;
    if ($e < 1) {
        return 0;
    }
    $band = (int)floor(($e - 1) / 10); // 0,1,2,3…
    $n = ($band + 1) * 10;
    return min(100, max(10, $n));
}

/** 본방냥 차감 연속: 1000=10 · 3000=30 · 5000=50 (1·100·500은 0) */
function enchant_promo_batch_newpoint_cost($times): int {
    $t = (int)$times;
    if ($t === 1000) {
        return 10;
    }
    if ($t === 3000) {
        return 30;
    }
    if ($t === 5000) {
        return 50;
    }
    return 0;
}

/** 허용 연속 횟수 */
function enchant_batch_times_allowed(): array {
    return [1, 100, 500, 1000, 3000, 5000];
}

/** 5000회 연속 강화 — 무기 강화(+레벨) 최소 */
function enchant_batch_5000_min_enhance(): int {
    return 50;
}

/**
 * 종류별 독점 좌석 1회 선조회 (배치 중 회차별 DB 왕복 제거)
 * @return array<int,string> enhance => nick
 */
function enchant_batch_seat_map($무기아이템, $제외닉 = ''): array {
    $키 = function_exists('강화_무기종류키') ? 강화_무기종류키($무기아이템) : trim((string)$무기아이템);
    if ($키 === '') {
        return [];
    }
    if ($키 === '🪄마법') {
        $무기조건 = "(TRIM(COALESCE(item,'')) = '🪄마법' OR TRIM(COALESCE(item,'')) = '🪄 마법')";
    } else {
        $키_esc = addslashes($키);
        $무기조건 = "TRIM(COALESCE(item,'')) = '{$키_esc}'";
    }
    $제외조건 = '';
    $제외닉 = trim((string)$제외닉);
    if ($제외닉 !== '') {
        $제외조건 = " AND name != '" . addslashes($제외닉) . "'";
    }
    $map = [];
    $rs = @db_query("
        SELECT name, enhance
        FROM tb_member
        WHERE {$무기조건} AND enhance >= 1{$제외조건}
    ");
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $e = (int)($row['enhance'] ?? 0);
            $n = trim((string)($row['name'] ?? ''));
            if ($e >= 1 && $n !== '' && !isset($map[$e])) {
                $map[$e] = $n;
            }
        }
    }
    return $map;
}

/** 선조회 좌석으로 도전모드 정보 구성 (DB 추가 조회 없음) */
function enchant_batch_challenge_from_seat($현재무기, $현재강화, array $seatMap, $도전자닉 = '') {
    $현재강화 = (int)$현재강화;
    $목표 = $현재강화 + 1;
    $독점시작 = defined('강화_독점시작') ? (int)강화_독점시작 : 1;
    $max = function_exists('강화_최대') ? 강화_최대() : 100;
    if ($목표 < $독점시작 || $목표 > $max) {
        return null;
    }
    $보유자닉 = trim((string)($seatMap[$목표] ?? ''));
    $도전자닉 = trim((string)$도전자닉);
    if ($보유자닉 === '' || ($도전자닉 !== '' && $보유자닉 === $도전자닉)) {
        return null;
    }
    if (function_exists('강화비용_기본금액')) {
        $비용 = (string)강화비용_기본금액($현재강화);
    } else {
        $비용 = '1';
    }
    if ($비용 === '' || $비용 === '0') {
        $비용 = '1';
    }
    $절반 = function_exists('강화비용_반액') ? 강화비용_반액($비용) : (string)max(0, (int)floor(((float)$비용) / 2));
    $분자 = function_exists('강화_성공분자') ? 강화_성공분자($현재강화) : 1;
    $분모 = function_exists('강화_성공분모') ? max(1, 강화_성공분모($현재강화)) : 100;
    return [
        '보유자닉'   => $보유자닉,
        '무기'       => trim((string)$현재무기),
        '목표강화'   => $목표,
        '현재강화'   => $현재강화,
        '비용'       => $비용,
        '보유자보상' => $절반,
        '소멸'       => $절반,
        '분자'       => $분자,
        '분모'       => $분모,
        '분모_은총'  => (int)max(1, (int)floor($분모 / 10)),
    ];
}

function enchant_point_str($v): string {
    if (function_exists('냥_정수문자열')) {
        return 냥_정수문자열($v);
    }
    $s = preg_replace('/[^\d]/', '', (string)$v);
    return ltrim((string)$s, '0') ?: '0';
}

function enchant_point_sub($point, $cost): string {
    $p = enchant_point_str($point);
    $c = enchant_point_str($cost);
    if (function_exists('냥_금액_문자열차감')) {
        return 냥_금액_문자열차감($p, $c);
    }
    if (function_exists('bcsub') && function_exists('bccomp')) {
        if (bccomp($p, $c, 0) < 0) {
            return '0';
        }
        $r = bcsub($p, $c, 0);
        return ($r === '' || (isset($r[0]) && $r[0] === '-')) ? '0' : $r;
    }
    // (int) 금지 — PHP_INT_MAX(~922경)에서 잔액이 잘림
    if (strlen($p) < strlen($c) || (strlen($p) === strlen($c) && $p < $c)) {
        return '0';
    }
    return $p; // 최소한 차감 전 잔액 유지 (bcmath/문자열차감 없을 때)
}

function enchant_point_add($a, $b): string {
    $a = enchant_point_str($a);
    $b = enchant_point_str($b);
    if (function_exists('냥_금액_문자열합')) {
        return 냥_금액_문자열합($a, $b);
    }
    if (function_exists('bcadd')) {
        return bcadd($a, $b, 0);
    }
    return $a;
}

function enchant_point_enough($point, $cost): bool {
    if (function_exists('강화비용_포인트충분')) {
        return 강화비용_포인트충분($point, $cost);
    }
    $p = enchant_point_str($point);
    $c = enchant_point_str($cost);
    if (function_exists('bccomp')) {
        return bccomp($p, $c, 0) >= 0;
    }
    if (strlen($p) !== strlen($c)) {
        return strlen($p) > strlen($c);
    }
    return $p >= $c;
}

/**
 * 강화 수호 N회 구매에 필요한 본방냥 (가방 수호 아이템 우선 차감 반영)
 */
function enchant_강화수호_구매필요비용(string $닉, int $count): string {
    $count = max(1, (int)$count);
    $보유아이템 = function_exists('item_bag_qty_nick') ? (int)item_bag_qty_nick($닉, '수호') : 0;
    $냥사용회수 = max(0, $count - min($count, $보유아이템));
    $회당 = function_exists('강화수호_회당비용') ? (float)강화수호_회당비용() : 0.1;
    return number_format(round($회당 * $냥사용회수, 1), 1, '.', '');
}

/** 강화 수호 회당 비용 표시 (본방냥) */
function enchant_수호시세_fmt($시세 = null): string {
    $n = $시세 !== null ? (float)$시세 : (function_exists('강화수호_회당비용') ? (float)강화수호_회당비용() : 0.1);
    return '본방 ' . number_format(max(0, $n), 1) . '냥';
}

/** 본방냥(newpoint) 표시 — 소수 1자리 */
function enchant_본방냥_fmt($n): string {
    return number_format(round((float)$n, 1), 1) . '냥';
}

/**
 * 본방냥(newpoint) 50% → 게임냥 스왑 (강화 페이지용 · 삭제 5%)
 * @return array{ok:bool,data?:string,point?:string,newpoint?:float,차감_np?:float,지급_pt?:string}
 */
function enchant_본방냥절반_스왑(string $닉): array {
    $닉 = trim($닉);
    if ($닉 === '') {
        return ['ok' => false, 'data' => '❌ 닉네임을 확인해주세요.'];
    }
    if (!function_exists('스왑_견적계산')) {
        $path = $_SERVER['DOCUMENT_ROOT'] . '/api/game/swap.inc.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
    if (!function_exists('스왑_견적계산') || !function_exists('스왑_정수문자열')) {
        return ['ok' => false, 'data' => '❌ 스왑 기능을 불러올 수 없어요.'];
    }
    $닉_esc = addslashes($닉);
    $회원 = db_select("SELECT newpoint, CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원)) {
        return ['ok' => false, 'data' => '❌ 회원 정보를 찾을 수 없어요.'];
    }
    $보유_np = round((float)($회원['newpoint'] ?? 0), 1);
    $스왑금액 = round($보유_np * 0.5, 1);
    if ($스왑금액 < 0.1) {
        return ['ok' => false, 'data' => '❌ 스왑할 본방냥이 없어요.'];
    }
    $견적 = 스왑_견적계산('np2pt', $스왑금액, false, 5.0, false);
    if (empty($견적['ok'])) {
        return ['ok' => false, 'data' => $견적['msg'] ?? '❌ 스왑을 처리할 수 없어요.'];
    }
    $차감_np = (float)$견적['차감_np'];
    $삭제_np = (float)($견적['삭제_np'] ?? 0);
    $교환_np = (float)($견적['교환_np'] ?? 0);
    $지급_pt = 스왑_정수문자열($견적['지급_pt'] ?? 0);
    if ($보유_np + 1e-9 < $차감_np) {
        return ['ok' => false, 'data' => '❌ 본방냥이 부족해요.'];
    }
    if (function_exists('스왑_point_컬럼_보장')) {
        스왑_point_컬럼_보장();
    }
    db_query("
      UPDATE tb_member
      SET newpoint = newpoint - {$차감_np},
          point = point + {$지급_pt}
      WHERE name = '{$닉_esc}' AND status = 0
      LIMIT 1
    ");
    $갱신 = db_select("SELECT newpoint, CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $np표시 = function_exists('newpoint표시') ? newpoint표시($차감_np) : number_format($차감_np, 1);
    $삭제표시 = function_exists('newpoint표시') ? newpoint표시($삭제_np) : number_format($삭제_np, 1);
    $교환표시 = function_exists('newpoint표시') ? newpoint표시($교환_np) : number_format($교환_np, 1);
    $msg = "💱 본방냥 50% 스왑 (5% 삭제)\n"
        . "차감 {$np표시}냥 · 삭제 {$삭제표시} · 교환 {$교환표시}\n"
        . '게임냥 +' . enchant_fmt_nyang($지급_pt);
    return [
        'ok' => true,
        'data' => $msg,
        'point' => enchant_point_str($갱신['point'] ?? 0),
        'newpoint' => round((float)($갱신['newpoint'] ?? 0), 1),
        '차감_np' => $차감_np,
        '지급_pt' => $지급_pt,
    ];
}

/**
 * 게임냥 부족 시 본냥→게임냥 스왑 유도 응답
 * @return array{ok:false,need_swap?:bool,data:string,swap_confirm?:string,newpoint?:float}
 */
function enchant_게임냥부족_스왑응답($회원, $필요비용): array {
    $보유 = enchant_point_str($회원['point'] ?? 0);
    $본냥 = round((float)($회원['newpoint'] ?? 0), 1);
    $msg = '❌ 게임냥이 부족해요. (필요: ' . enchant_fmt_nyang($필요비용)
        . ' · 보유: ' . enchant_fmt_nyang($보유) . ')';
    if ($본냥 < 0.1) {
        return [
            'ok' => false,
            'data' => $msg . "\n본냥도 없어 스왑할 수 없어요.",
        ];
    }
    $msg .= "\n본냥을 게임냥으로 스왑해야 해요.\n(본냥 1,000냥 남김 · 5% 제외 삭제 · 나머지 스왑)";
    return [
        'ok' => false,
        'need_swap' => true,
        'data' => $msg,
        'swap_confirm' => "게임냥이 부족합니다.\n본냥을 게임냥으로 스왑하시겠습니까?\n\n※ 본냥 1,000냥 남김\n※ 5% 제외(삭제) · 95% 환율 교환",
        'newpoint' => $본냥,
        'newpoint_fmt' => enchant_본방냥_fmt($본냥),
        'need_fmt' => enchant_fmt_nyang($필요비용),
        'have_fmt' => enchant_fmt_nyang($보유),
    ];
}

/** 표시용: 게임냥 — 억 미만(만·원)도 0으로 깎지 않음 */
function enchant_잔액_표시($point): string {
    return enchant_fmt_nyang($point);
}

/**
 * 강화 UI 갱신용 상태 (status / enhance 응답 공용)
 * @param bool $light true면 맛보기·도전 DB 조회 생략 (배치/단건 직후 체감용)
 * @param bool|null $include_suho_offer null이면 light가 아닐 때만 맥스구매 미리뽑기 포함
 */
function enchant_status_payload($닉, $회원 = null, $light = false, $include_suho_offer = null) {
    global $ENCHANT_할인적용, $ENCHANT_성공확률표, $ENCHANT_분모가져오기;
    $닉_esc = addslashes($닉);
    if (!is_array($회원) || empty($회원['name'])) {
        $회원 = db_select("SELECT name, CAST(point AS CHAR) AS point, IFNULL(newpoint, 0) AS newpoint, item, enhance, style, enhance_suho, 은총, 은총개수 FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    }
    if (empty($회원['name'])) {
        return [];
    }
    $현재강화 = (int)($회원['enhance'] ?? 0);
    $현재무기 = trim((string)($회원['item'] ?? ''));
    $은총효과 = enchant_eunchong_effect($닉, $회원);
    $은총활성 = !empty($은총효과['active']);
    $은총제로 = (int)($은총효과['zeros'] ?? 0);
    $은총비할인 = !empty($은총효과['cost_discount']);
    $내구도 = ($현재무기 !== '' && $현재강화 >= 10) ? (int)무기_최대내구도($현재무기, $현재강화) : 0;
    $수호시세 = 강화수호_회당비용();
    $은총_end = (string)($은총효과['end'] ?? '');
    $은총_left_sec = (int)($은총효과['left_sec'] ?? 0);

    // light: 탈취좌석 DB 재조회 없이 일반 비용·확률만 (UI 갱신에 충분)
    if ($light) {
        global $ENCHANT_강화비용표, $ENCHANT_테스트모드;
        $다음비용 = 강화비용_산출(
            $현재강화,
            $ENCHANT_강화비용표 ?? null,
            $ENCHANT_할인적용,
            $은총비할인,
            $ENCHANT_테스트모드 ?? false
        );
        if (function_exists('강화_성공분자')) {
            $분자 = 강화_성공분자($현재강화);
            $분모 = 강화_성공분모($현재강화);
        } else {
            $분자 = (int)($ENCHANT_성공확률표[$현재강화] ?? 1);
            $분모Fn = $ENCHANT_분모가져오기;
            $분모 = (int)$분모Fn($현재강화);
        }
        if ($은총제로 > 0 && function_exists('강화_분모_제로제거')) {
            $분모 = 강화_분모_제로제거((int)$분모, $은총제로);
        } elseif ($은총활성) {
            $분모 = (int)max(1, floor($분모 / 10));
        }
        if ($분자 < 1) {
            $분자 = 1;
        }
        if ($분자 > $분모) {
            $분자 = $분모;
        }
        $성공률 = ($분모 > 0)
            ? (rtrim(rtrim(number_format(($분자 / $분모) * 100, 6, '.', ''), '0'), '.') . '%')
            : '—';
        $맛보기상태 = ['cast_available' => 0, 'protect_available' => 0];
    } else {
        $다음비용 = enchant_cost_next($현재강화, $ENCHANT_할인적용, $은총비할인, $현재무기, $닉);
        $성공률 = enchant_success_rate_str($현재강화, $은총제로, $현재무기, $닉);
        $맛보기상태 = function_exists('web_trial_cast_status')
            ? web_trial_cast_status($닉)
            : ['cast_available' => 0, 'protect_available' => 0];
    }

    $point = enchant_point_str($회원['point'] ?? 0);
    $newpoint = round((float)($회원['newpoint'] ?? 0), 1);
    $mega_cost = defined('MINING_EUNCHONG_MEGA_COST') ? (int)MINING_EUNCHONG_MEGA_COST : 10;
    $suho_item = $light
        ? (function_exists('item_bag_qty_nick') ? (int)item_bag_qty_nick($닉, '수호') : enchant_suho_count($닉))
        : enchant_suho_count($닉);
    $out = [
        'point'        => $point,
        'point_fmt'    => enchant_잔액_표시($point),
        'newpoint'     => $newpoint,
        'newpoint_fmt' => enchant_본방냥_fmt($newpoint),
        'item'         => $현재무기,
        'enhance'      => $현재강화,
        'style'        => trim((string)($회원['style'] ?? '')),
        'enhance_suho' => enchant_suho_stack($닉, $회원),
        '은총개수'     => (int)($은총효과['cnt'] ?? (function_exists('bag_은총_수량') ? bag_은총_수량($닉) : ($회원['은총개수'] ?? 0))),
        '은총활성'     => $은총활성 ? 1 : 0,
        '은총_end'     => $은총_end,
        '은총_left_sec'=> $은총_left_sec,
        '은총_tier'    => (int)($은총효과['tier'] ?? 1),
        '은총_label'   => (string)($은총효과['label'] ?? '은총'),
        '은총_zeros'   => $은총제로,
        '은총_cost_discount' => $은총비할인 ? 1 : 0,
        '은총_mega_cost' => $mega_cost,
        '은총_show_mega' => (((int)($은총효과['cnt'] ?? 0)) >= $mega_cost) ? 1 : 0,
        'suho_item'    => $suho_item,
        'suho_price'   => number_format((float)$수호시세, 1, '.', ''),
        'suho_price_fmt' => enchant_수호시세_fmt($수호시세),
        'cost_next'    => enchant_point_str($다음비용),
        'cost_next_fmt'=> enchant_fmt_nyang($다음비용),
        'success_rate' => $성공률,
        'durability'   => $내구도,
        'trial_cast_available' => (int)($맛보기상태['cast_available'] ?? 0),
        'trial_protect_available' => (int)($맛보기상태['protect_available'] ?? 0),
    ];
    $withOffer = ($include_suho_offer === null) ? (!$light) : (bool)$include_suho_offer;
    if ($withOffer && function_exists('강화수호_맥스구매_견적')) {
        $offer = 강화수호_맥스구매_견적($newpoint, $닉);
        $out['suho_max_buy'] = (int)($offer['회수'] ?? 0);
        $out['suho_max_gain'] = (int)($offer['증가'] ?? 0);
        $out['suho_max_cost'] = number_format((float)($offer['비용'] ?? 0), 1, '.', '');
        $out['suho_max_exp'] = (int)($offer['exp'] ?? 0);
        $out['suho_max_token'] = (string)($offer['token'] ?? '');
        $out['suho_max_c1'] = (int)($offer['c1'] ?? 0);
        $out['suho_max_c2'] = (int)($offer['c2'] ?? 0);
        $out['suho_max_c3'] = (int)($offer['c3'] ?? 0);
    }
    return $out;
}

/**
 * DB 최신 기준 강화 1회. 단일/연속(배치) 공용.
 * @return array{ok: bool, data?: string, body?: array}
 */
function enchant_perform_enhance_attempt($닉, $닉_esc, $booster = false) {
    global $ENCHANT_강화최대, $ENCHANT_할인적용, $ENCHANT_성공확률표, $ENCHANT_분모가져오기;
    // 종류×강화 중복 즉시 정리 (최신 1명 유지·기존 하향)
    if (function_exists('강화_독점중복_정리')) {
        @강화_독점중복_정리();
    }
    $회원 = db_select("SELECT name, CAST(point AS CHAR) AS point, item, enhance, enhance_suho, style, 은총 FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($회원['name'])) {
        return ['ok' => false, 'data' => '❌ 회원을 찾을 수 없어요.'];
    }
    // config.php include 후 $닉 이 덮어써질 수 있어 DB 조회 결과를 항상 사용
    $강화주체닉 = trim((string)($회원['name'] ?? $닉));
    $은총효과 = enchant_eunchong_effect($강화주체닉, $회원);
    $은총활성 = !empty($은총효과['active']);
    $은총제로 = (int)($은총효과['zeros'] ?? 0);
    $은총비할인 = !empty($은총효과['cost_discount']);

    $현재무기 = trim((string)($회원['item'] ?? ''));
    $현재강화 = (int)($회원['enhance'] ?? 0);

    $boost = function_exists('mega_booster_apply_effect')
        ? mega_booster_apply_effect($은총효과, !empty($booster), $현재강화)
        : ['ok' => empty($booster), 'zeros' => $은총제로, 'extra_cost' => '0', 'applied' => false, 'error' => '부스터를 사용할 수 없어요.'];
    if (empty($boost['ok'])) {
        return ['ok' => false, 'data' => '❌ ' . (string)($boost['error'] ?? '부스터를 사용할 수 없어요.')];
    }
    $은총제로 = (int)($boost['zeros'] ?? $은총제로);
    $booster_applied = !empty($boost['applied']);
    $booster_extra = (string)($boost['extra_cost'] ?? '0');

    if ($현재무기 === '') {
        return ['ok' => false, 'data' => '❌ 보유 무기가 없어요. 먼저 무기를 구매하세요.'];
    }
    if ($현재강화 >= $ENCHANT_강화최대) {
        return ['ok' => false, 'data' => "⚔️ {$현재무기} 이미 최대 강화 +{$ENCHANT_강화최대} 입니다."];
    }

    $도전모드 = 강화20_도전모드_정보($현재무기, $현재강화, $강화주체닉);
    $보유자_esc = $도전모드 ? addslashes($도전모드['보유자닉']) : '';

    $강화비용 = $도전모드
        ? $도전모드['비용']
        : enchant_cost_next($현재강화, $ENCHANT_할인적용, $은총비할인, $현재무기, $강화주체닉);
    if ($booster_applied && $booster_extra !== '0') {
        $강화비용 = function_exists('mega_booster_add_cost')
            ? mega_booster_add_cost($강화비용, $booster_extra)
            : enchant_point_add($강화비용, $booster_extra);
    }
    $강화비용_sql = function_exists('강화비용_sql') ? 강화비용_sql($강화비용) : enchant_point_str($강화비용);
    $보유냥 = enchant_point_str($회원['point'] ?? 0);
    if (!enchant_point_enough($보유냥, $강화비용)) {
        return enchant_게임냥부족_스왑응답($회원, $강화비용);
    }

    $자숙위반 = function_exists('자숙_강화위반_적용') ? 자숙_강화위반_적용($강화주체닉, '냥') : ['notice' => ''];
    $자숙위반안내 = (string)($자숙위반['notice'] ?? '');
    $자숙차감 = (($자숙위반['deduct_from'] ?? 'point') === 'newpoint') ? 0 : (int)($자숙위반['deduct'] ?? 0);

    if (function_exists('강화_스키마_보장')) { 강화_스키마_보장(); }
    if (function_exists('강화_성공분자')) {
        $분자 = 강화_성공분자((int)$현재강화);
        $분모 = 강화_성공분모((int)$현재강화);
    } else {
        $분자 = (int)($ENCHANT_성공확률표[$현재강화] ?? 1);
        $분모 = (int)$ENCHANT_분모가져오기($현재강화);
    }
    if ($도전모드) {
        $분자 = max(1, (int)($도전모드['분자'] ?? $분자));
        $분모 = 강화20_도전모드_성공분모($은총제로, $도전모드);
    } elseif ($은총제로 > 0 && function_exists('강화_분모_제로제거')) {
        $분모 = 강화_분모_제로제거((int)$분모, $은총제로);
    } elseif ($은총활성) {
        $분모 = (int)max(1, floor($분모 / 10));
    }
    if ($분자 < 1) {
        $분자 = 1;
    }
    if ($분자 > $분모) {
        $분자 = $분모;
    }
    $주사위 = rand(1, $분모);
    $성공 = ($주사위 <= $분자);
    $성공확률_문구 = $도전모드
        ? 강화20_도전모드_성공확률문구($은총제로, $도전모드)
        : rtrim(rtrim(number_format(($분자 / $분모) * 100, 6, '.', ''), '0'), '.') . '%';
    $도전안내 = $도전모드 ? ("\n👑 +" . (int)($도전모드['목표강화'] ?? ($현재강화 + 1)) . " [{$도전모드['보유자닉']}] 탈취 도전") : '';
    $보상안내 = '';

    $rs = db_query("UPDATE tb_member SET point = point - {$강화비용_sql} WHERE name = '{$닉_esc}' AND point >= {$강화비용_sql} LIMIT 1");
    if (!$rs) {
        return ['ok' => false, 'data' => '❌ 강화 처리 실패.'];
    }
    global $conn;
    if (($conn instanceof mysqli) && (int)mysqli_affected_rows($conn) <= 0) {
        return enchant_게임냥부족_스왑응답($회원, $강화비용);
    }
    // 소멸분 재분배(50% 소멸 · 25% 금고 · 25% 로또) — 도전 시 보유자 보상 제외
    if (function_exists('강화비용_소멸분배')) {
        $소멸분배대상 = $강화비용;
        if ($도전모드) {
            $보유자보상분 = $도전모드['보유자보상'] ?? 0;
            $소멸분배대상 = enchant_point_sub($강화비용, $보유자보상분);
        }
        강화비용_소멸분배($소멸분배대상, $닉);
    }
    if ($도전모드) {
        $보상금 = 강화20_도전_보유자보상지급($보유자_esc, $도전모드['보유자보상']);
        $소멸금 = $도전모드['소멸'];
        $보상안내 = "\n💰 [{$도전모드['보유자닉']}] +" . enchant_fmt_nyang($보상금) . " · 소멸 " . enchant_fmt_nyang($소멸금);
    }

    $point_after = enchant_point_sub(enchant_point_sub($보유냥, $자숙차감), $강화비용);

    if ($성공) {
        $탈취안내 = '';
        $역풍발생 = false;
        $대성공안내 = '';
        $상승 = null;
        if ($도전모드) {
            $목표강화 = (int)($도전모드['목표강화'] ?? ($현재강화 + 1));
            $도전자결과 = 강화20_도전_성공시_도전자강화($목표강화, $현재강화);
            $다음강화 = (int)$도전자결과['enhance'];
            if (!empty($도전자결과['crit'])) {
                $상승 = $도전자결과;
                $대성공안내 = (string)($도전자결과['안내'] ?? '');
            }
        } else {
            $상승 = function_exists('강화_성공다음강화')
                ? 강화_성공다음강화($현재강화, $ENCHANT_강화최대)
                : ['enhance' => $현재강화 + 1, '안내' => ''];
            $다음강화 = (int)$상승['enhance'];
            $대성공안내 = (string)($상승['안내'] ?? '');
        }
        // 종류별 독점 좌석: 목표~도달 강화 기존 보유자 하향 (대성공 점프 포함)
        if ($도전모드) {
            $하향들 = [];
            if (function_exists('강화_독점좌석_상승선점')) {
                $하향들 = 강화_독점좌석_상승선점($현재무기, $현재강화, $다음강화, $닉, $강화주체닉);
            } elseif ($다음강화 >= (defined('강화_독점시작') ? (int)강화_독점시작 : 1) && function_exists('강화_독점좌석_선점')) {
                $하향들 = 강화_독점좌석_선점($현재무기, $다음강화, $닉, $강화주체닉);
            }
            if (!empty($하향들)) {
                $탈취조각 = [];
                foreach ($하향들 as $h) {
                    $탈취조각[] = "[{$h['nick']}] +{$h['from']} → +{$h['to']}";
                }
                $탈취안내 = "\n👑 +{$다음강화} 탈취! " . implode(' · ', $탈취조각);
            } else {
                $하향후 = 강화20_도전_성공시_보유자하향($도전모드['보유자닉'], $보유자_esc, $현재무기, $목표강화, $강화주체닉);
                $탈취안내 = "\n👑 +{$다음강화} 탈취! [{$도전모드['보유자닉']}] +{$목표강화} → +{$하향후}";
            }
        } elseif (function_exists('강화_독점좌석_상승선점')) {
            강화_독점좌석_상승선점($현재무기, $현재강화, $다음강화, $닉, $강화주체닉);
        } elseif ($다음강화 >= (defined('강화_독점시작') ? (int)강화_독점시작 : 1) && function_exists('강화_독점좌석_선점')) {
            강화_독점좌석_선점($현재무기, $다음강화, $닉, $강화주체닉);
        }
        db_query("UPDATE tb_member SET `enhance` = {$다음강화}, 강화성공시간 = now() WHERE name = '{$닉_esc}'");
        if (function_exists('무기_강화후_표시동기화')) {
            무기_강화후_표시동기화($닉, $회원 ?? $현재무기, $다음강화);
            if (function_exists('무기_표시아이템') && function_exists('무기_타입값')) {
                $동기화타입 = 무기_타입값($회원 ?? $현재무기);
                if ($동기화타입 > 0) {
                    $현재무기 = 무기_표시아이템($동기화타입, $다음강화);
                    $회원['item'] = $현재무기;
                    $회원['무기타입'] = $동기화타입;
                    $회원['enhance'] = $다음강화;
                }
            }
        }

        if ($다음강화 >= 10) {
            db_query("UPDATE tb_member SET magic_used = 0, magic_window = NOW() WHERE name = '{$닉_esc}'");
            $현재아이템_trim = trim($현재무기);
            if (function_exists('무기_마법인가') ? 무기_마법인가($회원 ?? $현재아이템_trim) : ($현재아이템_trim === '🪄마법' || $현재아이템_trim === '🪄 마법')) {
                db_query("UPDATE tb_member SET protect_used = 0, protect_reset_date = NOW() WHERE name = '{$닉_esc}'");
            }
            $내구도 = 무기_최대내구도($현재아이템_trim, $다음강화);
            if ($내구도 > 0) {
                $기존행 = db_select("SELECT durability FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
                $기존내구도 = isset($기존행['durability']) && $기존행['durability'] !== null ? (int)$기존행['durability'] : 0;
                if ($기존내구도 < $내구도) {
                    db_query("UPDATE tb_member SET durability = {$내구도} WHERE name = '{$닉_esc}'");
                }
            }
        }
        // 구간진입·대성공 알림 (탈취 도전 포함 · 대성공 점프 시)
        if (empty($역풍발생) && function_exists('강화_구간진입_알림등록')) {
            $강화주체닉 = trim((string)($회원['name'] ?? $강화주체닉));
            강화_구간진입_알림등록($강화주체닉, $현재무기, $현재강화, $다음강화);
        }
        if (empty($역풍발생) && is_array($상승) && !empty($상승['crit']) && function_exists('강화_대성공_알림등록')) {
            $강화주체닉 = trim((string)($회원['name'] ?? $강화주체닉));
            강화_대성공_알림등록($강화주체닉, $현재무기, $현재강화, $다음강화, (int)($상승['gain'] ?? 0));
        }

        if (function_exists('강화_이력_기록')) {
            강화_이력_기록([
                'nick'             => $강화주체닉,
                'channel'          => 'web',
                'item'             => $현재무기,
                'style'            => trim((string)($회원['style'] ?? '')),
                'enhance_before'   => $현재강화,
                'enhance_after'    => $다음강화,
                'result'           => 'success',
                'cost'             => $강화비용,
                'dice'             => $주사위,
                'dice_num'         => $분자,
                'dice_den'         => $분모,
                'rate'             => $성공확률_문구,
                'eunchong'         => $은총활성,
                'challenge_mode'   => (bool)$도전모드,
                'challenge_target' => $도전모드 ? ($도전모드['보유자닉'] ?? '') : '',
                'challenge_steal'  => $도전모드 ? !empty($도전자결과['탈취성공']) : null,
            ]);
        }

        return ['ok' => true, 'body' => [
            'ok'       => true,
            'type'     => 'enhance',
            'result'   => 'success',
            'data'     => $자숙위반안내 . "⚔️ 강화 성공! (주사위 {$주사위})\n{$현재무기} +{$현재강화} → +{$다음강화}{$대성공안내}{$도전안내}{$탈취안내}{$보상안내}\n(확률 {$성공확률_문구} · " . enchant_fmt_nyang($강화비용) . " 차감)",
            'item'     => $현재무기,
            'enhance'  => $다음강화,
            'point'    => $point_after,
            'point_fmt'=> enchant_fmt_nyang($point_after),
            'cost'     => $강화비용,
            'cost_fmt' => enchant_fmt_nyang($강화비용),
            'rate'     => $성공확률_문구,
            'dice'     => $주사위,
        ]];
    }
    $수호방지 = function_exists('강화실패_수호방지_적용')
        ? 강화실패_수호방지_적용($강화주체닉)
        : null;
    if ($수호방지 !== null) {
        $남은수호 = (int)$수호방지['enhance_suho'];
        $수호문구 = (string)($수호방지['msg_suffix'] ?? '');
        if (function_exists('강화_이력_기록')) {
            강화_이력_기록([
                'nick'           => $강화주체닉,
                'channel'        => 'web',
                'item'           => $현재무기,
                'style'          => trim((string)($회원['style'] ?? '')),
                'enhance_before' => $현재강화,
                'enhance_after'  => $현재강화,
                'result'         => 'fail_protect',
                'cost'           => $강화비용,
                'dice'           => $주사위,
                'dice_num'       => $분자,
                'dice_den'       => $분모,
                'rate'           => $성공확률_문구,
                'eunchong'       => $은총활성,
                'challenge_mode' => (bool)$도전모드,
                'challenge_target' => $도전모드 ? ($도전모드['보유자닉'] ?? '') : '',
                'suho_used'      => true,
                'suho_left'      => $남은수호,
            ]);
        }
        $아이템표시 = (int)($수호방지['suho_item_left'] ?? 0);
        return ['ok' => true, 'body' => [
            'ok'       => true,
            'type'     => 'enhance',
            'result'   => 'fail_protect',
            'data'     => $자숙위반안내 . "👼 강화 실패! 파손 방지{$수호문구}\n{$현재무기} +{$현재강화} 유지 · 남은 수호 {$남은수호}회{$도전안내}{$보상안내}\n(확률 {$성공확률_문구} · " . enchant_fmt_nyang($강화비용) . " 차감)",
            'item'         => $현재무기,
            'enhance'      => $현재강화,
            'enhance_suho' => $남은수호,
            'suho_item'    => $아이템표시,
            'suho_item_used' => !empty($수호방지['suho_item_used']) ? 1 : 0,
            'point'        => $point_after,
            'point_fmt'    => enchant_fmt_nyang($point_after),
            'cost'         => $강화비용,
            'cost_fmt'     => enchant_fmt_nyang($강화비용),
            'rate'         => $성공확률_문구,
            'dice'         => $주사위,
        ]];
    }
    if (function_exists('무기_해제')) {
        무기_해제($닉, ["`style` = ''"]);
    } else {
        db_query("UPDATE tb_member SET `item` = NULL, `enhance` = 0, `style` = '' WHERE name = '{$닉_esc}'");
    }
    if (function_exists('강화_이력_기록')) {
        강화_이력_기록([
            'nick'           => $강화주체닉,
            'channel'        => 'web',
            'item'           => $현재무기,
            'style'          => trim((string)($회원['style'] ?? '')),
            'enhance_before' => $현재강화,
            'enhance_after'  => 0,
            'result'         => 'fail_break',
            'cost'           => $강화비용,
            'dice'           => $주사위,
            'dice_num'       => $분자,
            'dice_den'       => $분모,
            'rate'           => $성공확률_문구,
            'eunchong'       => $은총활성,
            'challenge_mode' => (bool)$도전모드,
            'challenge_target' => $도전모드 ? ($도전모드['보유자닉'] ?? '') : '',
        ]);
    }
    return ['ok' => true, 'body' => [
        'ok'       => true,
        'type'     => 'enhance',
        'result'   => 'fail_break',
        'need_suho' => 1,
        'data'     => $자숙위반안내 . "💥 강화 실패! 무기 파손… (주사위 {$주사위})\n{$현재무기} +{$현재강화} 소멸{$도전안내}{$보상안내}\n(확률 {$성공확률_문구} · " . enchant_fmt_nyang($강화비용) . " 차감)\n\n👼 파손 방지가 없어요. 아래에서 수호를 구매하거나 강화 수호로 전환하세요.",
        'item'     => '',
        'enhance'  => 0,
        'broken_item' => $현재무기,
        'broken_enhance' => $현재강화,
        'suho_item' => enchant_suho_count($닉),
        'point'    => $point_after,
        'point_fmt'=> enchant_fmt_nyang($point_after),
        'cost'     => $강화비용,
        'cost_fmt' => enchant_fmt_nyang($강화비용),
        'rate'     => $성공확률_문구,
        'dice'     => $주사위,
    ]];
}

/**
 * 배치 강화 — 비용·확률 선계산 + 메모리 루프 후 DB 커밋 (채굴 연속강화와 동일 패턴)
 * 탈취 도전(좌석 점유)도 회차마다 단건 시도하지 않고 배치 루프에서 처리
 * @return array{ok: bool, data?: string, body?: array}
 */
function enchant_batch_suho_consume(&$enhance_suho, array &$suho_item_ids, array &$used_suho_idxs) {
    if ($enhance_suho >= 1) {
        $enhance_suho--;
        return [
            'enhance_suho' => $enhance_suho,
            'suho_item_used' => false,
            'suho_item_left' => count($suho_item_ids),
            'msg_suffix' => $enhance_suho > 0 ? " (수호 {$enhance_suho}회 남음)" : '',
        ];
    }
    if (!empty($suho_item_ids)) {
        $idx = (int)array_shift($suho_item_ids);
        if ($idx > 0) {
            $used_suho_idxs[] = $idx;
        }
        $left = count($suho_item_ids);
        return [
            'enhance_suho' => $enhance_suho,
            'suho_item_used' => true,
            'suho_item_left' => $left,
            'msg_suffix' => ' (수호 아이템 1개 소모' . ($left > 0 ? " · {$left}개 남음" : '') . ')',
        ];
    }
    return null;
}

function enchant_batch_commit_member($닉_esc, array $st, $start_point, $total_spent, array $used_suho_idxs, $newpoint_spent = 0, $burn_amount = null) {
    global $conn;

    $spent_sql = function_exists('강화비용_sql') ? 강화비용_sql($total_spent) : enchant_point_str($total_spent);
    $spent_positive = function_exists('bccomp')
        ? (bccomp($spent_sql, '0', 0) > 0)
        : ((int)$spent_sql > 0);
    $newpoint_spent = max(0, (int)$newpoint_spent);

    $sets = [];
    if ($spent_positive) {
        $sets[] = "point = point - {$spent_sql}";
    }
    if ($newpoint_spent > 0) {
        $sets[] = 'newpoint = newpoint - ' . $newpoint_spent;
    }
    $sets[] = '`enhance` = ' . (int)$st['enhance'];
    if ($st['item'] === '') {
        $sets[] = '`item` = NULL';
        $sets[] = '`무기타입` = 0';
        $sets[] = "`style` = ''";
    } else {
        $배치타입 = function_exists('무기_타입값') ? 무기_타입값($st['무기타입'] ?? $st['item']) : 0;
        $배치아이템 = $st['item'];
        if ($배치타입 >= 1 && $배치타입 <= 3 && function_exists('무기_표시아이템')) {
            $배치아이템 = 무기_표시아이템($배치타입, (int)$st['enhance']);
        }
        $sets[] = "`item` = '" . addslashes($배치아이템) . "'";
        if ($배치타입 >= 1 && $배치타입 <= 3) {
            $sets[] = '`무기타입` = ' . $배치타입;
        }
        $sets[] = "`style` = '" . addslashes($st['style']) . "'";
    }
    $sets[] = 'enhance_suho = ' . (int)$st['enhance_suho'];
    if ((int)$st['durability'] > 0) {
        $sets[] = 'durability = ' . (int)$st['durability'];
    }
    if (!empty($st['had_success'])) {
        $sets[] = '강화성공시간 = NOW()';
    }
    if (!empty($st['magic_reset'])) {
        $sets[] = 'magic_used = 0';
        $sets[] = 'magic_window = NOW()';
    }
    if (!empty($st['protect_reset'])) {
        $sets[] = 'protect_used = 0';
        $sets[] = 'protect_reset_date = NOW()';
    }

    $where = ["name = '{$닉_esc}'"];
    if ($spent_positive) {
        $where[] = "point >= {$spent_sql}";
    }
    if ($newpoint_spent > 0) {
        $where[] = 'newpoint >= ' . $newpoint_spent;
    }

    db_query('UPDATE tb_member SET ' . implode(', ', $sets) . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
    $applied = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
    if (!$applied) {
        return false;
    }

    $배치닉 = stripslashes($닉_esc);

    // 소멸분 재분배(50% 소멸 · 25% 금고 · 25% 로또) — burn_amount가 있으면 그걸, 없으면 total_spent
    if ($spent_positive && function_exists('강화비용_소멸분배')) {
        $분배금액 = ($burn_amount !== null && $burn_amount !== '') ? $burn_amount : $total_spent;
        강화비용_소멸분배($분배금액, $배치닉);
    }

    if (function_exists('bag_강화수호_설정')) {
        bag_강화수호_설정(stripslashes($닉_esc), (int)$st['enhance_suho']);
    }

    if (!empty($used_suho_idxs)) {
        $ids = implode(',', array_map('intval', $used_suho_idxs));
        db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx IN ({$ids}) AND nick = '{$닉_esc}'");
        if (function_exists('아이템사용_시세하락')) {
            아이템사용_시세하락('수호', count($used_suho_idxs));
        }
    }

    return true;
}

function enchant_perform_enhance_batch($닉, $닉_esc, $times, $newpoint_cost = 0, $booster = false) {
    global $ENCHANT_강화최대, $ENCHANT_할인적용, $ENCHANT_성공확률표, $ENCHANT_분모가져오기, $ENCHANT_강화비용표, $ENCHANT_테스트모드;

    if (function_exists('tb_member_point_컬럼_보장')) {
        tb_member_point_컬럼_보장();
    }
    $times = max(1, min(5000, (int)$times));
    $newpoint_cost = max(0, (int)$newpoint_cost);
    if ($times >= 1000) {
        @set_time_limit(max(180, (int)ceil($times * 0.12)));
    } elseif ($times >= 100) {
        @set_time_limit(180);
    }
    $회원 = db_select("SELECT name, CAST(IFNULL(point, 0) AS CHAR) AS point, IFNULL(newpoint, 0) AS newpoint, item, enhance, enhance_suho, style, 은총, durability FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($회원['name'])) {
        return ['ok' => false, 'data' => '❌ 회원을 찾을 수 없어요.'];
    }
    $start_newpoint = (int)floor((float)($회원['newpoint'] ?? 0));
    if ($newpoint_cost > 0 && $start_newpoint < $newpoint_cost) {
        $필요표시 = number_format($newpoint_cost);
        $보유표시 = number_format($start_newpoint);
        return ['ok' => false, 'data' => "❌ 본방냥이 부족해요. ({$times}회 연속 {$필요표시}냥 · 보유 {$보유표시}냥)"];
    }

    $강화주체닉 = trim((string)($회원['name'] ?? $닉));
    $은총효과 = enchant_eunchong_effect($강화주체닉, $회원);
    $은총활성 = !empty($은총효과['active']);
    $은총제로 = (int)($은총효과['zeros'] ?? 0);
    $은총비할인 = !empty($은총효과['cost_discount']);

    $boost = function_exists('mega_booster_apply_effect')
        ? mega_booster_apply_effect($은총효과, !empty($booster), (int)($회원['enhance'] ?? 0))
        : ['ok' => empty($booster), 'zeros' => $은총제로, 'extra_cost' => '0', 'applied' => false, 'error' => '부스터를 사용할 수 없어요.'];
    if (empty($boost['ok'])) {
        return ['ok' => false, 'data' => '❌ ' . (string)($boost['error'] ?? '부스터를 사용할 수 없어요.')];
    }
    $은총제로 = (int)($boost['zeros'] ?? $은총제로);
    $booster_applied = !empty($boost['applied']);
    $스타일문구 = trim((string)($회원['style'] ?? ''));

    $st = [
        'point'         => enchant_point_str($회원['point'] ?? 0),
        'item'          => trim((string)($회원['item'] ?? '')),
        'enhance'       => (int)($회원['enhance'] ?? 0),
        'style'         => $스타일문구,
        'enhance_suho'  => enchant_suho_stack($강화주체닉, $회원),
        'durability'    => isset($회원['durability']) && $회원['durability'] !== null ? (int)$회원['durability'] : 0,
        'magic_reset'   => false,
        'protect_reset' => false,
        'had_success'   => false,
    ];
    $start_point = $st['point'];

    // 수호 아이템: 이번 배치에 쓸 만큼만 로드 (전체 순회 방지)
    $suho_item_ids = [];
    $suho_fetch = max(20, min(5000, $times));
    $rs = db_query("SELECT idx FROM tb_member_item WHERE nick = '{$닉_esc}' AND itemname = '수호' AND status = 0 ORDER BY idx ASC LIMIT {$suho_fetch}");
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $suho_item_ids[] = (int)$row['idx'];
        }
    }
    $used_suho_idxs = [];

    if ($st['item'] === '') {
        return ['ok' => false, 'data' => '❌ 보유 무기가 없어요. 먼저 무기를 구매하세요.'];
    }
    if ($st['enhance'] >= $ENCHANT_강화최대) {
        return ['ok' => false, 'data' => "⚔️ {$st['item']} 이미 최대 강화 +{$ENCHANT_강화최대} 입니다."];
    }

    $자숙위반안내 = '';
    if (function_exists('자숙_강화위반_적용')) {
        $자숙위반 = 자숙_강화위반_적용($강화주체닉, '냥');
        $자숙위반안내 = (string)($자숙위반['notice'] ?? '');
    }

    // 좌석 1회 선조회 + 이력은 연속에서 생략(채굴처럼 요약만)
    $seatMap = enchant_batch_seat_map($st['item'], $강화주체닉);
    $write_history = ($times < 10);

    // 채굴 배치와 동일: 레벨별 비용·확률 선계산 후 메모리 루프 → DB 1회 커밋
    $비용캐시 = [];
    $분모캐시 = [];
    for ($lv = 0; $lv < (int)$ENCHANT_강화최대; $lv++) {
        $비용캐시[$lv . '|0'] = 강화비용_산출($lv, $ENCHANT_강화비용표, $ENCHANT_할인적용, false, $ENCHANT_테스트모드);
        $비용캐시[$lv . '|1'] = 강화비용_산출($lv, $ENCHANT_강화비용표, $ENCHANT_할인적용, true, $ENCHANT_테스트모드);
        if ($booster_applied && function_exists('mega_booster_cost_for_level') && function_exists('mega_booster_add_cost')) {
            $부스터비 = mega_booster_cost_for_level($lv);
            $비용캐시[$lv . '|0'] = mega_booster_add_cost($비용캐시[$lv . '|0'], $부스터비);
            $비용캐시[$lv . '|1'] = mega_booster_add_cost($비용캐시[$lv . '|1'], $부스터비);
        } elseif ($booster_applied && function_exists('mega_booster_cost_for_level')) {
            $부스터비 = mega_booster_cost_for_level($lv);
            $비용캐시[$lv . '|0'] = enchant_point_add($비용캐시[$lv . '|0'], $부스터비);
            $비용캐시[$lv . '|1'] = enchant_point_add($비용캐시[$lv . '|1'], $부스터비);
        }
        if (function_exists('강화_성공분자')) {
            $분자 = 강화_성공분자((int)$lv);
            $분모 = 강화_성공분모((int)$lv);
        } else {
            $분자 = (int)($ENCHANT_성공확률표[$lv] ?? 1);
            $분모 = (int)$ENCHANT_분모가져오기($lv);
        }
        if ($은총제로 > 0 && function_exists('강화_분모_제로제거')) {
            $분모_e = 강화_분모_제로제거((int)$분모, $은총제로);
        } elseif ($은총활성) {
            $분모_e = (int)max(1, floor($분모 / 10));
        } else {
            $분모_e = (int)$분모;
        }
        if ($분자 < 1) {
            $분자 = 1;
        }
        $분모캐시[$lv . '|0'] = [$분자, max(1, (int)$분모)];
        $분모캐시[$lv . '|1'] = [$분자, max(1, (int)$분모_e)];
    }

    $totalCost = '0';
    $n_ok = 0;
    $n_prot = 0;
    $n_fail = 0;
    $n_steal = 0;
    $tries = 0;
    $lastBody = null;
    $history_pending = [];
    $lotto_pending = [];
    $holder_pay = []; // nick => amount(string)
    $steal_pending = []; // 커밋 성공 후 보유자 하향
    $도전캐시 = [];
    $start_enhance = (int)$st['enhance'];
    $start_item = $st['item'];

    for ($i = 0; $i < $times; $i++) {
        $현재무기 = $st['item'];
        $현재강화 = $st['enhance'];

        if ($현재무기 === '' || $현재강화 >= $ENCHANT_강화최대) {
            break;
        }

        $도전키 = $현재무기 . '|' . ($현재강화 + 1);
        if (!array_key_exists($도전키, $도전캐시)) {
            $도전캐시[$도전키] = enchant_batch_challenge_from_seat($현재무기, $현재강화, $seatMap, $강화주체닉);
        }
        $도전모드 = $도전캐시[$도전키];

        if ($도전모드) {
            $강화비용 = $도전모드['비용'];
            if ($booster_applied && function_exists('mega_booster_cost_for_level')) {
                $부스터비 = mega_booster_cost_for_level($현재강화);
                $강화비용 = function_exists('mega_booster_add_cost')
                    ? mega_booster_add_cost($강화비용, $부스터비)
                    : enchant_point_add($강화비용, $부스터비);
            }
            $분자 = max(1, (int)($도전모드['분자'] ?? 1));
            $분모 = 강화20_도전모드_성공분모($은총제로, $도전모드);
            if ($분자 > $분모) {
                $분자 = $분모;
            }
            $성공확률_문구 = 강화20_도전모드_성공확률문구($은총제로, $도전모드);
            $주사위 = rand(1, $분모);
        } else {
            $비용키 = $현재강화 . '|' . ($은총비할인 ? '1' : '0');
            $강화비용 = $비용캐시[$비용키];
            $분모키 = $현재강화 . '|' . ($은총활성 || $은총제로 > 0 ? '1' : '0');
            [$분자, $분모] = $분모캐시[$분모키];
            if ($분자 > $분모) {
                $분자 = $분모;
            }
            $성공확률_문구 = rtrim(rtrim(number_format(($분자 / $분모) * 100, 6, '.', ''), '0'), '.') . '%';
            $주사위 = rand(1, $분모);
        }

        if (!enchant_point_enough($st['point'], $강화비용)) {
            if ($tries === 0) {
                return enchant_게임냥부족_스왑응답($회원, $강화비용);
            }
            break;
        }

        $st['point'] = enchant_point_sub($st['point'], $강화비용);
        $totalCost = enchant_point_add($totalCost, $강화비용);
        $tries++;
        $성공 = ($주사위 <= $분자);

        // 탈취 도전: 시도마다 보유자 보상 누적 (커밋 시 1회 지급)
        if ($도전모드) {
            $hNick = trim((string)($도전모드['보유자닉'] ?? ''));
            $보상 = $도전모드['보유자보상'] ?? '0';
            if ($hNick !== '') {
                $holder_pay[$hNick] = isset($holder_pay[$hNick])
                    ? enchant_point_add($holder_pay[$hNick], $보상)
                    : enchant_point_str($보상);
            }
        }

        if ($성공) {
            $탈취성공 = false;
            $상승 = null;
            if ($도전모드) {
                $목표강화 = (int)($도전모드['목표강화'] ?? ($현재강화 + 1));
                $도전자결과 = 강화20_도전_성공시_도전자강화($목표강화, $현재강화);
                $다음강화 = (int)$도전자결과['enhance'];
                $탈취성공 = !empty($도전자결과['탈취성공']);
                if (!empty($도전자결과['crit'])) {
                    $상승 = $도전자결과;
                }
                $보유자닉 = trim((string)($도전모드['보유자닉'] ?? ''));
                if ($보유자닉 !== '') {
                    // DB는 내 커밋 성공 뒤에 반영 (실패 시 보유자만 깎이는 일 방지)
                    $steal_pending[] = [
                        '보유자닉' => $보유자닉,
                        '무기'     => $현재무기,
                        '목표강화' => $목표강화,
                        '탈취자닉' => $강화주체닉,
                    ];
                }
                $n_steal++;
                // 로컬 좌석맵 갱신 (커밋 전 DB는 그대로) · 대성공 점프 구간 포함
                for ($lv = $현재강화 + 1; $lv <= $다음강화; $lv++) {
                    unset($seatMap[$lv]);
                }
                $seatMap[$다음강화] = $강화주체닉;
                if ($보유자닉 !== '') {
                    $to = max(0, $목표강화 - 1);
                    while ($to >= 1 && isset($seatMap[$to]) && $seatMap[$to] !== $보유자닉) {
                        $to--;
                    }
                    if ($to >= 1) {
                        $seatMap[$to] = $보유자닉;
                    }
                }
                $도전캐시[$도전키] = null;
                unset($도전캐시[$현재무기 . '|' . ($다음강화 + 1)]);
            } else {
                $상승 = function_exists('강화_성공다음강화')
                    ? 강화_성공다음강화($현재강화, $ENCHANT_강화최대)
                    : ['enhance' => $현재강화 + 1];
                $다음강화 = (int)$상승['enhance'];
                $seatMap[$다음강화] = $강화주체닉;
                if (function_exists('강화_독점좌석_상승선점')) {
                    // 배치 커밋 전 좌석맵만 갱신 · 실제 DB 하향은 커밋 후 중복정리로 보완 가능
                    for ($lv = $현재강화 + 1; $lv < $다음강화; $lv++) {
                        unset($seatMap[$lv]);
                    }
                }
            }

            $st['enhance'] = $다음강화;
            $st['had_success'] = true;
            if (function_exists('무기_타입값') && function_exists('무기_표시아이템')) {
                $배치타입 = 무기_타입값($st['무기타입'] ?? $회원 ?? $현재무기);
                if ($배치타입 >= 1 && $배치타입 <= 3) {
                    $st['무기타입'] = $배치타입;
                    $st['item'] = 무기_표시아이템($배치타입, $다음강화);
                    $현재무기 = $st['item'];
                }
            }
            $n_ok++;

            if ($다음강화 >= 10) {
                $st['magic_reset'] = true;
                $현재아이템_trim = trim($현재무기);
                if (function_exists('무기_마법인가') ? 무기_마법인가($st['무기타입'] ?? $현재아이템_trim) : ($현재아이템_trim === '🪄마법' || $현재아이템_trim === '🪄 마법')) {
                    $st['protect_reset'] = true;
                }
                $내구도 = (int)무기_최대내구도($현재아이템_trim, $다음강화);
                if ($내구도 > 0 && $st['durability'] < $내구도) {
                    $st['durability'] = $내구도;
                }
            }
            // 구간진입·대성공 알림 (탈취 도전 대성공 포함)
            if (function_exists('강화_구간진입_알림문구')) {
                $구간알림 = 강화_구간진입_알림문구($강화주체닉, $현재무기, $현재강화, $다음강화);
                if ($구간알림 !== null) {
                    $lotto_pending[] = ['msg' => $구간알림, 'item' => $강화주체닉];
                }
            }
            if (is_array($상승) && !empty($상승['crit']) && function_exists('강화_대성공_알림문구')) {
                $대성공알림 = 강화_대성공_알림문구($강화주체닉, $현재무기, $현재강화, $다음강화, (int)($상승['gain'] ?? 0));
                if ($대성공알림 !== null) {
                    $lotto_pending[] = ['msg' => $대성공알림, 'item' => $강화주체닉];
                }
            }

            $lastBody = [
                'ok' => true, 'type' => 'enhance', 'result' => 'success',
                'data' => '', 'item' => $현재무기, 'enhance' => $다음강화,
                'point' => $st['point'], 'cost' => $강화비용,
                'rate' => $성공확률_문구, 'dice' => $주사위,
            ];
            if ($write_history) {
                $history_pending[] = [
                    'nick' => $강화주체닉, 'channel' => 'web', 'item' => $현재무기, 'style' => $스타일문구,
                    'enhance_before' => $현재강화, 'enhance_after' => $다음강화, 'result' => 'success',
                    'cost' => $강화비용, 'dice' => $주사위, 'dice_num' => $분자, 'dice_den' => $분모,
                    'rate' => $성공확률_문구, 'eunchong' => $은총활성,
                    'challenge_mode' => (bool)$도전모드,
                    'challenge_target' => $도전모드 ? ($도전모드['보유자닉'] ?? '') : '',
                    'challenge_steal' => $도전모드 ? $탈취성공 : null,
                ];
            }

            if ($다음강화 >= $ENCHANT_강화최대) {
                break;
            }
            continue;
        }

        $수호방지 = enchant_batch_suho_consume($st['enhance_suho'], $suho_item_ids, $used_suho_idxs);
        if ($수호방지 !== null) {
            $n_prot++;
            $남은수호 = (int)$수호방지['enhance_suho'];
            $lastBody = [
                'ok' => true, 'type' => 'enhance', 'result' => 'fail_protect',
                'data' => '', 'item' => $현재무기, 'enhance' => $현재강화,
                'enhance_suho' => $남은수호,
                'suho_item' => (int)($수호방지['suho_item_left'] ?? count($suho_item_ids)),
                'point' => $st['point'], 'cost' => $강화비용,
                'rate' => $성공확률_문구, 'dice' => $주사위,
            ];
            if ($write_history) {
                $history_pending[] = [
                    'nick' => $강화주체닉, 'channel' => 'web', 'item' => $현재무기, 'style' => $스타일문구,
                    'enhance_before' => $현재강화, 'enhance_after' => $현재강화, 'result' => 'fail_protect',
                    'cost' => $강화비용, 'dice' => $주사위, 'dice_num' => $분자, 'dice_den' => $분모,
                    'rate' => $성공확률_문구, 'eunchong' => $은총활성,
                    'challenge_mode' => (bool)$도전모드,
                    'challenge_target' => $도전모드 ? ($도전모드['보유자닉'] ?? '') : '',
                    'suho_used' => true, 'suho_left' => $남은수호,
                ];
            }
            continue;
        }

        $n_fail++;
        $brokenItem = $현재무기;
        $brokenEnhance = $현재강화;
        $st['item'] = '';
        $st['style'] = '';
        $st['enhance'] = 0;
        $lastBody = [
            'ok' => true, 'type' => 'enhance', 'result' => 'fail_break', 'need_suho' => 1,
            'data' => '', 'item' => '', 'enhance' => 0,
            'broken_item' => $brokenItem,
            'broken_enhance' => $brokenEnhance,
            'suho_item' => count($suho_item_ids),
            'point' => $st['point'], 'cost' => $강화비용,
            'rate' => $성공확률_문구, 'dice' => $주사위,
        ];
        if ($write_history) {
            $history_pending[] = [
                'nick' => $강화주체닉, 'channel' => 'web', 'item' => $현재무기, 'style' => $스타일문구,
                'enhance_before' => $현재강화, 'enhance_after' => 0, 'result' => 'fail_break',
                'cost' => $강화비용, 'dice' => $주사위, 'dice_num' => $분자, 'dice_den' => $분모,
                'rate' => $성공확률_문구, 'eunchong' => $은총활성,
                'challenge_mode' => (bool)$도전모드,
                'challenge_target' => $도전모드 ? ($도전모드['보유자닉'] ?? '') : '',
            ];
        }
        break;
    }

    if ($tries < 1 || $lastBody === null) {
        return ['ok' => false, 'data' => '❌ 강화를 진행하지 못했어요.'];
    }

    $np_deduct = ($newpoint_cost > 0 && $tries >= 1) ? $newpoint_cost : 0;
    $changed = ($st['enhance'] !== $start_enhance)
        || ($st['item'] !== $start_item)
        || !empty($used_suho_idxs)
        || $np_deduct > 0
        || (function_exists('bccomp') ? bccomp($totalCost, '0', 0) > 0 : ((float)$totalCost > 0));

    $riseCleared = false;
    if ($changed) {
        $holderTotal = '0';
        foreach ($holder_pay as $hAmt) {
            $holderTotal = enchant_point_add($holderTotal, $hAmt);
        }
        $burnTotal = enchant_point_sub($totalCost, $holderTotal);
        if (!enchant_batch_commit_member($닉_esc, $st, $start_point, $totalCost, $used_suho_idxs, $np_deduct, $burnTotal)) {
            return ['ok' => false, 'data' => '❌ 강화 저장에 실패했어요. 냥·본방냥·무기 상태를 확인해주세요.'];
        }
        if ($np_deduct > 0 && function_exists('지급로그')) {
            지급로그('무기강화배치', $강화주체닉, $tries . '회(본방)', 0, $np_deduct);
        }
        // 대성공 점프·연속 상승으로 지나간 독점 좌석 정리 (탈취 좌석 포함 → 알림 1회)
        if ((int)$st['enhance'] > (int)$start_enhance
            && $st['item'] !== ''
            && function_exists('강화_독점좌석_상승선점')
        ) {
            강화_독점좌석_상승선점($st['item'], (int)$start_enhance, (int)$st['enhance'], $닉, $강화주체닉);
            $riseCleared = true;
        }
    }

    // 탈취: 보유자 하향 → 보상 (내 커밋 이후, 보유자별 보상은 1회 UPDATE)
    // 상승선점으로 이미 비웠으면 하향·홍보방 알림 중복 호출 금지 (파손 등으로 상승선점 스킵된 경우만 보정)
    if (empty($riseCleared)) {
        foreach ($steal_pending as $sp) {
            $hNick = trim((string)($sp['보유자닉'] ?? ''));
            if ($hNick === '') {
                continue;
            }
            강화20_도전_성공시_보유자하향(
                $hNick,
                addslashes($hNick),
                (string)($sp['무기'] ?? ''),
                (int)($sp['목표강화'] ?? 0),
                (string)($sp['탈취자닉'] ?? '')
            );
        }
    }
    foreach ($holder_pay as $hNick => $amt) {
        $hNick = trim((string)$hNick);
        if ($hNick === '') {
            continue;
        }
        강화20_도전_보유자보상지급(addslashes($hNick), $amt);
    }

    if (!empty($history_pending)) {
        if (function_exists('강화_이력_기록_일괄')) {
            강화_이력_기록_일괄($history_pending);
        } else {
            foreach ($history_pending as $h) {
                if (function_exists('강화_이력_기록')) {
                    강화_이력_기록($h);
                }
            }
        }
    }
    foreach ($lotto_pending as $lotto) {
        if (function_exists('강화_알림_등록')) {
            강화_알림_등록($lotto['msg'], $lotto['item']);
        } elseif (function_exists('info2알림_등록')) {
            info2알림_등록($lotto['msg'], $lotto['item']);
        } else {
            $msg_esc = addslashes((string)$lotto['msg']);
            $item_esc = addslashes((string)$lotto['item']);
            @db_query("
              CREATE TABLE IF NOT EXISTS tb_info2_alarm (
                idx INT UNSIGNED NOT NULL AUTO_INCREMENT,
                status TINYINT NOT NULL DEFAULT 0,
                msg TEXT NOT NULL,
                item VARCHAR(64) NOT NULL DEFAULT 'system',
                regdate DATETIME NOT NULL,
                PRIMARY KEY (idx),
                KEY ix_status_reg (status, regdate, idx)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            @db_query("INSERT INTO tb_info2_alarm SET status=0, msg='{$msg_esc}', item='{$item_esc}', regdate=NOW()");
        }
    }

    $누적표시 = enchant_잔액_표시($totalCost);
    $잔액표시 = enchant_잔액_표시($st['point']);
    // 채굴 배치처럼 짧은 요약만 (회차별 전문 X → 응답/렌더 체감 개선)
    $msg = "📋 {$tries}회 강화 · 성공 {$n_ok} · 수호 {$n_prot} · 실패 {$n_fail}";
    if ($n_steal > 0) {
        $msg .= " · 탈취 {$n_steal}";
    }
    $msg .= " · 누적 {$누적표시}\n잔액 {$잔액표시}";
    if ($np_deduct > 0) {
        $msg .= "\n본방냥 -" . number_format($np_deduct) . '냥';
    }
    if (($lastBody['result'] ?? '') === 'fail_break') {
        $msg .= "\n💥 무기 파손 · 수호를 준비해 주세요";
    } elseif ($st['item'] !== '') {
        $msg .= "\n현재 {$st['item']} +{$st['enhance']}";
    }
    if ($자숙위반안내 !== '') {
        $msg = $자숙위반안내 . $msg;
    }

    $out = $lastBody;
    $out['ok'] = true;
    $out['type'] = 'enhance_batch';
    $out['data'] = $msg;
    $out['result'] = $lastBody['result'];
    if (($lastBody['result'] ?? '') === 'fail_break') {
        $out['need_suho'] = 1;
    }
    $out['enhance_suho'] = $st['enhance_suho'];
    $out['suho_item'] = count($suho_item_ids);
    $out['point'] = $st['point'];
    $out['point_fmt'] = $잔액표시;
    $out['newpoint'] = max(0, round($start_newpoint - $np_deduct, 1));
    $out['newpoint_fmt'] = enchant_본방냥_fmt($out['newpoint']);
    $out['item'] = $st['item'];
    $out['enhance'] = $st['enhance'];
    $out['total_batch_cost'] = $totalCost;
    $out['cost'] = $totalCost;
    $out['cost_fmt'] = $누적표시;
    $out['newpoint_cost'] = $np_deduct;
    $out['batch_tries'] = $tries;
    $out['batch_success'] = $n_ok;
    $out['batch_fail'] = $n_fail;
    $out['batch_protect'] = $n_prot;

    return ['ok' => true, 'body' => $out];
}

// ----- 공통 요청값 -----
$req_action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
$req_code   = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : '';

// ----- 코드 인증이 필요한 액션들 (탭 락 없음) -----
$ACTIONS_NEED_AUTH = ['status', 'buy', 'enhance', 'enhance_batch', 'use_suho', 'use_eunchong', 'trial_cast', 'history', 'swap_np_to_pt'];
if (in_array($req_action, $ACTIONS_NEED_AUTH, true)) {
    if ($req_code === '') enchant_json(['ok' => false, 'data' => '초대 코드가 필요합니다.']);

    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    $회원 = enchant_auth($req_code);
    if (!$회원) enchant_json(['ok' => false, 'data' => '유효하지 않은 초대 코드입니다.']);

    $닉 = trim($회원['name']);
    $닉_esc = addslashes($닉);

    $은총활성 = !empty($회원['은총']) && (strtotime($회원['은총']) > time());

    // config.php는 $두자리닉넴 + 계급() 필요 → 반드시 api/function.php 먼저 include
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/item/web_trial_cast.php';
    $두자리닉넴 = getTwoCharNick($닉);
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';
    enchant_refresh_cost_tables();
    // config.php 관리자 목록 루프가 $닉 을 마지막 관리자로 덮어씀 → code 인증 회원으로 복원
    $닉 = trim($회원['name']);
    $닉_esc = addslashes($닉);
    $두자리닉넴 = getTwoCharNick($닉);
    if (function_exists('무기_내구도20_일괄3000_적용')) {
        무기_내구도20_일괄3000_적용();
    }

    // ===== 본냥 → 게임냥 스왑 (본냥 1000 남김 · 5% 삭제) =====
    if ($req_action === 'swap_np_to_pt') {
        if (!function_exists('스왑_본냥잔여_실행_데이터')) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/api/game/swap.inc.php';
        }
        $결과 = 스왑_본냥잔여_실행_데이터($닉, 1000.0, 5.0);
        if (empty($결과['ok'])) {
            enchant_json(['ok' => false, 'data' => $결과['data'] ?? '❌ 스왑에 실패했어요.']);
        }
        enchant_json([
            'ok' => true,
            'type' => 'swap_np_to_pt',
            'data' => $결과['data'] ?? '💱 스왑 완료',
            'point' => $결과['point'] ?? null,
            'point_fmt' => isset($결과['point']) ? enchant_fmt_nyang($결과['point']) : null,
            'newpoint' => $결과['newpoint'] ?? null,
            'newpoint_fmt' => isset($결과['newpoint'])
                ? enchant_본방냥_fmt($결과['newpoint'])
                : null,
        ]);
    }

    // ===== 상태 조회 =====
    if ($req_action === 'status') {
        // auth 시점 $회원 은 enhance가 stale일 수 있음 → 항상 DB 재조회
        enchant_json(array_merge(['ok' => true, 'name' => $닉], enchant_status_payload($닉, null)));
    }

    // ===== 맛보기 시전 (하루 1회) =====
    if ($req_action === 'trial_cast') {
        $cast_kind = isset($_REQUEST['cast_kind']) ? trim((string)$_REQUEST['cast_kind']) : '';
        $결과 = web_trial_cast_execute($닉, $회원, $cast_kind);
        if (empty($결과['ok'])) {
            enchant_json(['ok' => false, 'data' => $결과['data'] ?? '❌ 맛보기 실패']);
        }
        enchant_json($결과);
    }

    // ===== 무기 구매 =====
    if ($req_action === 'buy') {
        $weapon = isset($_REQUEST['weapon']) ? trim($_REQUEST['weapon']) : '';
        $현재무기 = trim((string)($회원['item'] ?? ''));
        if ($현재무기 !== '') {
            enchant_json(['ok' => false, 'data' => '이미 보유한 무기가 있어요. 강화를 진행하거나 파손 시 재구매해주세요.']);
        }

        $지정구매 = ($weapon !== '' && $weapon !== '랜덤' && isset($ENCHANT_무기맵[$weapon]));
        if ($weapon !== '' && $weapon !== '랜덤' && !$지정구매) {
            enchant_json(['ok' => false, 'data' => '무기 종류를 확인해주세요. (랜덤/단소/활/마법)']);
        }
        $구매비용 = $ENCHANT_첫무기구매비용;
        $구매비용_str = enchant_point_str($구매비용);

        $보유냥 = enchant_point_str($회원['point'] ?? 0);
        if (!enchant_point_enough($보유냥, $구매비용_str)) {
            enchant_json(enchant_게임냥부족_스왑응답($회원, $구매비용_str));
        }

        $자숙위반 = function_exists('자숙_강화위반_적용') ? 자숙_강화위반_적용($닉, '냥') : ['notice' => '', 'deduct' => 0, 'deduct_from' => ''];
        $자숙위반안내 = (string)($자숙위반['notice'] ?? '');
        $자숙차감 = (($자숙위반['deduct_from'] ?? 'point') === 'newpoint') ? '0' : enchant_point_str($자숙위반['deduct'] ?? 0);

        if (function_exists('무기_타입_스키마보장')) { 무기_타입_스키마보장(); }
        if ($지정구매) {
            $구매타입 = function_exists('무기_타입_키에서') ? 무기_타입_키에서($weapon) : 0;
            $선택무기 = (function_exists('무기_표시아이템') && $구매타입 > 0) ? 무기_표시아이템($구매타입, 0) : $ENCHANT_무기맵[$weapon];
        } else {
            $키목록 = array_keys($ENCHANT_무기맵);
            $weapon = $키목록[array_rand($키목록)];
            $구매타입 = function_exists('무기_타입_키에서') ? 무기_타입_키에서($weapon) : 0;
            $선택무기 = (function_exists('무기_표시아이템') && $구매타입 > 0) ? 무기_표시아이템($구매타입, 0) : $ENCHANT_무기맵[$weapon];
        }
        $구매비용_sql = function_exists('강화비용_sql') ? 강화비용_sql($구매비용_str) : $구매비용_str;

        if (function_exists('무기_장착_갱신') && !empty($구매타입)) {
            $rs = 무기_장착_갱신($닉, (int)$구매타입, 0, ["point = point - {$구매비용_sql}"]);
        } else {
            $무기_esc = addslashes($선택무기);
            $rs = db_query("UPDATE tb_member SET point = point - {$구매비용_sql}, `item` = '{$무기_esc}', `enhance` = 0 WHERE name = '{$닉_esc}'");
        }
        if (!$rs) {
            enchant_json(['ok' => false, 'data' => '❌ 구매 처리 실패']);
        }
        $잔액 = enchant_point_sub(enchant_point_sub($보유냥, $자숙차감), $구매비용_str);
        미션완료_기록_if_new($두자리닉넴, '일방', '무기구매');
        enchant_json([
            'ok'       => true,
            'type'     => 'buy',
            'data'     => $자숙위반안내 . "⚔️ {$선택무기} 구매완료! (" . enchant_fmt_nyang($구매비용_str) . " 차감)",
            'item'     => $선택무기,
            'enhance'  => 0,
            'point'    => $잔액,
            'point_fmt'=> enchant_fmt_nyang($잔액),
            'cost'     => $구매비용_str,
            'cost_fmt' => enchant_fmt_nyang($구매비용_str),
        ]);
    }

    // ===== 강화 이력 =====
    if ($req_action === 'history') {
        $limit = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : 15;
        $rows = function_exists('강화_이력_조회') ? 강화_이력_조회($닉, $limit) : [];
        foreach ($rows as $i => $row) {
            $rows[$i]['cost_fmt'] = enchant_fmt_nyang((int)($row['cost'] ?? 0));
        }
        enchant_json(['ok' => true, 'type' => 'history', 'items' => $rows]);
    }

    // ===== 강화 시도 (1회) =====
    if ($req_action === 'enhance') {
        $booster = function_exists('mega_booster_request_wanted')
            ? mega_booster_request_wanted()
            : (!empty($_REQUEST['booster']) && (string)$_REQUEST['booster'] !== '0');
        $r = enchant_perform_enhance_attempt($닉, $닉_esc, $booster);
        if (!$r['ok']) {
            enchant_json($r);
        }
        // light: 맛보기·탈취 재조회 생략 · body를 나중에 합쳐 강화 결과가 덮이지 않게
        enchant_json(array_merge(enchant_status_payload($닉, null, true), $r['body']));
    }

    // ===== 강화 연속 (1·100·500 무료 / 1000=본방10 / 3000=본방30 / 5000=본방50) =====
    if ($req_action === 'enhance_batch') {
        $현재강화_배치 = (int)($회원['enhance'] ?? 0);
        $내무기_배치 = trim((string)($회원['item'] ?? ''));
        if ($내무기_배치 === '') {
            enchant_json(['ok' => false, 'data' => '❌ 보유 무기가 없어요.']);
        }
        if ($현재강화_배치 >= $ENCHANT_강화최대) {
            enchant_json(['ok' => false, 'data' => "⚔️ 이미 최대 강화 +{$ENCHANT_강화최대} 입니다."]);
        }

        $times = isset($_REQUEST['times']) ? (int)$_REQUEST['times'] : 0;
        if (!in_array($times, enchant_batch_times_allowed(), true)) {
            enchant_json(['ok' => false, 'data' => '❌ 연속 강화는 1·100·500·1000·3000·5000회만 가능해요.']);
        }
        if ($times === 5000 && $현재강화_배치 < enchant_batch_5000_min_enhance()) {
            $필요 = enchant_batch_5000_min_enhance();
            enchant_json(['ok' => false, 'data' => "❌ 5000회 연속 강화는 무기 +{$필요} 이상부터 가능해요. (현재 +{$현재강화_배치})"]);
        }
        $newpoint_cost = enchant_promo_batch_newpoint_cost($times);
        $booster = function_exists('mega_booster_request_wanted')
            ? mega_booster_request_wanted()
            : (!empty($_REQUEST['booster']) && (string)$_REQUEST['booster'] !== '0');

        $r = enchant_perform_enhance_batch($닉, $닉_esc, $times, $newpoint_cost, $booster);
        if (!$r['ok']) {
            enchant_json($r);
        }
        // body에 이미 point/enhance 있음 + light 상태로 합침 (재조회 최소화)
        $body = $r['body'];
        $lightMember = [
            'name' => $닉,
            'point' => $body['point'] ?? 0,
            'newpoint' => $body['newpoint'] ?? 0,
            'item' => $body['item'] ?? '',
            'enhance' => $body['enhance'] ?? 0,
            'style' => $회원['style'] ?? '',
            'enhance_suho' => $body['enhance_suho'] ?? ($회원['enhance_suho'] ?? 0),
            '은총' => $회원['은총'] ?? '',
            '은총개수' => $회원['은총개수'] ?? 0,
        ];
        enchant_json(array_merge(enchant_status_payload($닉, $lightMember, true), $body));
    }

    // ===== 강화 수호 구매/전환 =====
    if ($req_action === 'use_suho') {
        $count = isset($_REQUEST['count']) ? (int)$_REQUEST['count'] : 0;
        if ($count < 1) {
            $count = 1;
        }
        if ($count > 10000000) {
            enchant_json(['ok' => false, 'data' => '❌ 한 번에 처리할 수 있는 횟수를 초과했어요.']);
        }

        $buyMax = isset($_REQUEST['buy_max']) && (string)$_REQUEST['buy_max'] !== '0' && (string)$_REQUEST['buy_max'] !== '';
        if ($buyMax) {
            $gain = isset($_REQUEST['gain']) ? (int)$_REQUEST['gain'] : 0;
            $cost = isset($_REQUEST['cost']) ? (float)$_REQUEST['cost'] : 0.0;
            $exp = isset($_REQUEST['exp']) ? (int)$_REQUEST['exp'] : 0;
            $token = isset($_REQUEST['token']) ? (string)$_REQUEST['token'] : '';
            if (!강화수호_맥스구매_검증($닉, $count, $gain, $cost, $exp, $token)) {
                enchant_json(['ok' => false, 'data' => '❌ 미리뽑기 정보가 만료됐어요. 새로고침 후 다시 구매해주세요.']);
            }
            $결과 = 강화수호_본방고정구매($닉, $count, $gain);
        } else {
            // 수호 구매·전환 모두 회당 +1~+3 (채팅 `.강화 수호`와 동일)
            $결과 = 강화수호_냥적용($닉, $count, '본방냥', null);
        }
        if (empty($결과['ok'])) {
            enchant_json(['ok' => false, 'data' => $결과['msg'] ?? '❌ 강화 수호 처리에 실패했어요.']);
        }

        $수호후잔액 = enchant_point_str($결과['point'] ?? 0);
        $수호시세 = 강화수호_회당비용();
        $newpoint = isset($결과['newpoint'])
            ? round((float)$결과['newpoint'], 1)
            : round((float)((db_select("SELECT IFNULL(newpoint, 0) AS newpoint FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1")['newpoint'] ?? 0)), 1);
        $offer = function_exists('강화수호_맥스구매_견적')
            ? 강화수호_맥스구매_견적($newpoint, $닉)
            : ['회수' => 0, '증가' => 0, '비용' => 0, 'exp' => 0, 'token' => '', 'c1' => 0, 'c2' => 0, 'c3' => 0];
        enchant_json([
            'ok'           => true,
            'type'         => 'use_suho',
            'data'         => $결과['msg'],
            'enhance_suho' => (int)($결과['enhance_suho'] ?? 0),
            'point'        => $수호후잔액,
            'point_fmt'    => enchant_fmt_nyang($수호후잔액),
            'newpoint'     => $newpoint,
            'newpoint_fmt' => enchant_본방냥_fmt($newpoint),
            'suho_item'    => enchant_suho_count($닉),
            'suho_price'   => number_format((float)$수호시세, 1, '.', ''),
            'suho_price_fmt' => enchant_수호시세_fmt($수호시세),
            'suho_max_buy' => (int)($offer['회수'] ?? 0),
            'suho_max_gain' => (int)($offer['증가'] ?? 0),
            'suho_max_cost' => number_format((float)($offer['비용'] ?? 0), 1, '.', ''),
            'suho_max_exp' => (int)($offer['exp'] ?? 0),
            'suho_max_token' => (string)($offer['token'] ?? ''),
            'suho_max_c1' => (int)($offer['c1'] ?? 0),
            'suho_max_c2' => (int)($offer['c2'] ?? 0),
            'suho_max_c3' => (int)($offer['c3'] ?? 0),
            'swapped_half' => 0,
        ]);
    }

    // ===== 은총/메가은총 사용 (무기 전용 · 채굴과 분리) =====
    if ($req_action === 'use_eunchong') {
        $tier = isset($_POST['tier']) ? (int)$_POST['tier'] : (isset($_GET['tier']) ? (int)$_GET['tier'] : 1);
        $tier = ($tier >= 2) ? 2 : 1;
        $현재무기 = trim((string)($회원['item'] ?? ''));
        $현재강화 = (int)($회원['enhance'] ?? 0);
        if (!function_exists('강화_은총_사용')) {
            enchant_json(['ok' => false, 'data' => '❌ 은총 기능을 불러올 수 없어요.']);
        }
        $결과 = 강화_은총_사용($닉, $tier, $현재강화, $현재무기);
        if (empty($결과['ok'])) {
            enchant_json(['ok' => false, 'data' => $결과['data'] ?? '❌ 은총 적용에 실패했습니다.']);
        }
        $은총효과 = enchant_eunchong_effect($닉);
        $zeros = (int)($은총효과['zeros'] ?? ($결과['zeros'] ?? 0));
        $discount = !empty($은총효과['cost_discount']);
        enchant_json(array_merge([
            'ok'            => true,
            'type'          => 'use_eunchong',
            'data'          => $결과['data'] ?? '적용 완료',
            'success_rate'  => enchant_success_rate_str($현재강화, $zeros, $현재무기, $닉),
            'cost_next'     => enchant_cost_next($현재강화, $ENCHANT_할인적용, $discount, $현재무기, $닉),
            'cost_next_fmt' => enchant_fmt_nyang(enchant_cost_next($현재강화, $ENCHANT_할인적용, $discount, $현재무기, $닉)),
            'eunchong_discount' => $discount ? 1 : 0,
            '은총_tier'     => (int)($은총효과['tier'] ?? $tier),
            '은총_label'    => (string)($은총효과['label'] ?? ($결과['label'] ?? '은총')),
            '은총_zeros'    => $zeros,
            '은총_cost_discount' => $discount ? 1 : 0,
        ], enchant_status_payload($닉)));
    }
}

// ===================== 페이지 출력 =====================
$enchant_code = isset($_GET['code']) ? trim($_GET['code']) : '';
$enchant_name = '';
$enchant_point = '0';
$enchant_newpoint = 0;
$enchant_item = '';
$enchant_type = 0;
$enchant_enhance = 0;
$enchant_style = '';
$enchant_enhance_suho = 0;
$enchant_suho_item = 0;
$enchant_suho_price = '0';
$enchant_suho_max_buy = 0;
$enchant_suho_max_gain = 0;
$enchant_suho_max_cost = '0.0';
$enchant_suho_max_exp = 0;
$enchant_suho_max_token = '';
$enchant_cost_next = '0';
$enchant_rate_str = '—';
$enchant_need_code = false;
$enchant_은총활성 = false;
$enchant_은총개수 = 0;
$enchant_은총_end = '';
$enchant_은총_left_sec = 0;
$enchant_은총_tier = 1;
$enchant_은총_label = '은총';
$enchant_은총_zeros = 0;
$enchant_은총_cost_discount = 0;
$enchant_은총_mega_cost = 10;
$enchant_은총_show_mega = 0;
$enchant_durability = 0;
$enchant_point_fmt = '';
$enchant_cost_next_fmt = '';
$enchant_suho_price_fmt = '';
$enchant_buy_cost_fmt = '';
$enchant_trial_cast_available = 0;
$enchant_trial_protect_available = 0;

if ($enchant_code === '') {
    $enchant_need_code = true;
} else {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/item/web_trial_cast.php';
    $m = enchant_auth($enchant_code);
    if (!$m) {
        $enchant_need_code = true;
    } else {
        $enchant_name = trim($m['name']);
        // (int) 캐스팅 금지 — PHP_INT_MAX(~922경)에서 잘려 천경·해가 깨짐
        $enchant_point = enchant_point_str($m['point'] ?? 0);
        if (!isset($m['newpoint'])) {
            $npRow = db_select("SELECT IFNULL(newpoint, 0) AS newpoint FROM tb_member WHERE name = '" . addslashes($enchant_name) . "' LIMIT 1");
            $enchant_newpoint = round((float)($npRow['newpoint'] ?? 0), 1);
        } else {
            $enchant_newpoint = round((float)$m['newpoint'], 1);
        }
        $enchant_item = trim((string)($m['item'] ?? ''));
        $enchant_enhance = (int)($m['enhance'] ?? 0);
        $enchant_style = trim((string)($m['style'] ?? ''));
        $enchant_enhance_suho = enchant_suho_stack($enchant_name, $m);
        $enchant_suho_item = enchant_suho_count($enchant_name);
        // config.php는 $두자리닉넴 필요 → function.php 먼저 include
        $두자리닉넴 = getTwoCharNick($enchant_name);
        include_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';
        enchant_refresh_cost_tables();
        if (function_exists('무기_타입_스키마보장')) {
            무기_타입_스키마보장();
        }
        if (function_exists('강화_스키마_보장')) {
            강화_스키마_보장();
        }
        $enchant_type = function_exists('무기_타입값') ? 무기_타입값($m) : 0;
        if ($enchant_type > 0 && function_exists('무기_표시아이템')) {
            $enchant_item = 무기_표시아이템($enchant_type, $enchant_enhance);
        }
        if (function_exists('tb_member_point_컬럼_보장')) {
            tb_member_point_컬럼_보장();
        }
        if (function_exists('무기_내구도20_일괄3000_적용')) {
            무기_내구도20_일괄3000_적용();
        }
        $enchant_suho_price = number_format((float)강화수호_회당비용(), 1, '.', '');
        $eunEff = enchant_eunchong_effect($enchant_name, $m);
        $enchant_은총활성 = !empty($eunEff['active']);
        $enchant_은총개수 = (int)($eunEff['cnt'] ?? ($m['은총개수'] ?? 0));
        $enchant_은총_end = (string)($eunEff['end'] ?? '');
        $enchant_은총_left_sec = (int)($eunEff['left_sec'] ?? 0);
        $enchant_은총_tier = (int)($eunEff['tier'] ?? 1);
        $enchant_은총_label = (string)($eunEff['label'] ?? '은총');
        $enchant_은총_zeros = (int)($eunEff['zeros'] ?? 0);
        $enchant_은총_cost_discount = !empty($eunEff['cost_discount']) ? 1 : 0;
        $enchant_은총_mega_cost = defined('MINING_EUNCHONG_MEGA_COST') ? (int)MINING_EUNCHONG_MEGA_COST : 10;
        $enchant_은총_show_mega = ($enchant_은총개수 >= $enchant_은총_mega_cost) ? 1 : 0;
        $enchant_cost_next = enchant_point_str(enchant_cost_next($enchant_enhance, $ENCHANT_할인적용, !empty($eunEff['cost_discount']), $enchant_item, $enchant_name));
        $enchant_rate_str = enchant_success_rate_str($enchant_enhance, $enchant_은총_zeros, $enchant_item, $enchant_name);
        if ($enchant_item !== '' && $enchant_enhance >= 10) {
            $enchant_durability = (int)무기_최대내구도($enchant_item, $enchant_enhance);
        }
        $enchant_point_fmt = enchant_fmt_nyang($enchant_point);
        $enchant_cost_next_fmt = enchant_fmt_nyang($enchant_cost_next);
        $enchant_suho_price_fmt = enchant_수호시세_fmt($enchant_suho_price);
        $enchant_buy_cost_fmt = enchant_fmt_nyang($ENCHANT_첫무기구매비용);
        $suhoOffer = function_exists('강화수호_맥스구매_견적')
            ? 강화수호_맥스구매_견적((float)$enchant_newpoint, $enchant_name)
            : ['회수' => 0, '증가' => 0, '비용' => 0, 'exp' => 0, 'token' => ''];
        $enchant_suho_max_buy = (int)($suhoOffer['회수'] ?? 0);
        $enchant_suho_max_gain = (int)($suhoOffer['증가'] ?? 0);
        $enchant_suho_max_cost = number_format((float)($suhoOffer['비용'] ?? 0), 1, '.', '');
        $enchant_suho_max_exp = (int)($suhoOffer['exp'] ?? 0);
        $enchant_suho_max_token = (string)($suhoOffer['token'] ?? '');
        if ($enchant_item !== '' && $enchant_enhance >= 10) {
            $trialSt = web_trial_cast_status($enchant_name);
            $enchant_trial_cast_available = (int)$trialSt['cast_available'];
            $enchant_trial_protect_available = (int)$trialSt['protect_available'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1a0a2e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>무기 강화 · 웹</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Black+Han+Sans&family=Noto+Sans+KR:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #1a0a2e;
            --card: #16213e;
            --accent: #e94560;
            --gold: #ffd700;
            --blue: #4dabf7;
            --green: #51cf66;
            --text: #eaeaea;
            --muted: #a0a0a0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            min-height: 100vh;
            min-height: -webkit-fill-available;
            background: var(--bg);
            background-image:
                radial-gradient(ellipse at 50% 0%, rgba(233,69,96,0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(255,215,0,0.08) 0%, transparent 40%);
            font-family: 'Noto Sans KR', sans-serif;
            color: var(--text);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 24px;
            padding-left: max(24px, env(safe-area-inset-left));
            padding-right: max(24px, env(safe-area-inset-right));
            padding-bottom: max(24px, env(safe-area-inset-bottom));
        }
        .wrap {
            width: 100%;
            max-width: 460px;
            background: var(--card);
            border-radius: 24px;
            padding: 28px 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.06);
        }
        h1 {
            font-family: 'Black Han Sans', sans-serif;
            font-size: 2.1rem;
            text-align: center;
            margin-bottom: 4px;
            background: linear-gradient(135deg, var(--gold), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .sub {
            text-align: center;
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 22px;
        }
        .card {
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 14px;
        }
        .card h3 {
            font-size: 0.95rem;
            margin-bottom: 10px;
            color: var(--gold);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .stat {
            background: rgba(255,255,255,0.04);
            border-radius: 10px;
            padding: 10px 12px;
        }
        .stat .label {
            font-size: 0.75rem;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .stat .value {
            font-size: 1rem;
            font-weight: 700;
            word-break: break-all;
        }
        .stat .value.gold { color: var(--gold); }
        .stat .value.accent { color: var(--accent); }
        .stat .value.blue { color: var(--blue); }
        .stat .value.green { color: var(--green); }

        .info-card { padding: 12px 14px; }
        .info-card h3 { margin-bottom: 6px; font-size: 0.9rem; }
        .info-compact { display: flex; flex-direction: column; gap: 5px; }
        .info-line {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
            font-size: 0.82rem;
            line-height: 1.3;
        }
        .info-line .il { color: var(--muted); flex-shrink: 0; font-size: 0.74rem; }
        .info-line .iv {
            font-weight: 700;
            text-align: right;
            word-break: break-all;
            font-size: 0.88rem;
        }
        .info-line .iv.gold { color: var(--gold); font-size: 0.84rem; }
        .info-line .iv.accent { color: var(--accent); }
        .info-line .iv.blue { color: var(--blue); }
        .info-line .iv.green { color: var(--green); }
        .info-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 10px;
            padding: 6px 8px;
            background: rgba(255,255,255,0.04);
            border-radius: 8px;
        }
        .info-chip {
            display: inline-flex;
            align-items: baseline;
            gap: 3px;
            font-size: 0.78rem;
            white-space: nowrap;
        }
        .info-chip .il { color: var(--muted); font-size: 0.7rem; }
        .info-chip .iv { font-weight: 700; font-size: 0.82rem; }
        .info-chip .iv.accent { color: var(--accent); }
        .info-chip .iv.blue { color: var(--blue); }
        .info-chip .iv.green { color: var(--green); }

        .weapon-display {
            text-align: center;
            padding: 10px 12px;
            background: linear-gradient(135deg, rgba(255,215,0,0.08), rgba(233,69,96,0.08));
            border: 1px solid rgba(255,215,0,0.2);
            border-radius: 12px;
            margin-bottom: 10px;
        }
        .weapon-display .emoji {
            font-size: 1.85rem;
            line-height: 1;
            margin-bottom: 2px;
        }
        .weapon-display .weapon-line {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: center;
            gap: 0 8px;
            max-width: 100%;
        }
        .weapon-display .name {
            font-family: 'Black Han Sans', sans-serif;
            font-size: 1.05rem;
            color: var(--gold);
        }
        .weapon-display .level {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--accent);
        }
        .weapon-display .style {
            display: inline-block;
            font-size: 0.78rem;
            color: var(--muted);
            margin: 0;
        }
        .weapon-display.empty {
            opacity: 0.75;
        }
        .weapon-display.empty .name {
            color: var(--muted);
        }

        .btn-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        .btn-row.c2 { grid-template-columns: repeat(2, 1fr); }
        .btn {
            padding: 12px 10px;
            min-height: 46px;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 0.88rem;
            font-weight: 700;
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: transform 0.08s, box-shadow 0.2s, opacity 0.15s;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        .btn:active { transform: translateY(1px); }
        .btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }
        .btn-buy-random { background: linear-gradient(145deg, #4dabf7, #3b8fd1); box-shadow: 0 4px 12px rgba(77,171,247,0.3); }
        .btn-buy { background: linear-gradient(145deg, #2d3561, #20264a); border: 1px solid rgba(255,255,255,0.12); }
        .btn-enhance {
            background: linear-gradient(145deg, var(--accent), #c73e54);
            box-shadow: 0 1px 5px rgba(233,69,96,0.22);
        }
        .mega-booster-wrap {
            display: none;
            align-items: center;
            gap: 8px;
            margin: 10px 0 0;
            padding: 9px 11px;
            border-radius: 10px;
            border: 1px solid rgba(56, 189, 248, 0.35);
            background: rgba(14, 165, 233, 0.08);
            color: #7dd3fc;
            font-size: 0.84rem;
            line-height: 1.35;
            cursor: pointer;
            user-select: none;
        }
        .mega-booster-wrap.show { display: flex; }
        .mega-booster-wrap input {
            width: 16px;
            height: 16px;
            accent-color: #0ea5e9;
            flex-shrink: 0;
        }
        .mega-booster-wrap small {
            opacity: 0.75;
            font-size: 0.78rem;
        }
        .enhance-btns {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: 4px;
            margin-top: 6px;
        }
        .enhance-btns .btn.btn-enhance {
            flex: 1 1 0;
            min-width: 0;
            min-height: 42px;
            height: 42px;
            padding: 0 2px;
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 1;
            border-radius: 8px;
            letter-spacing: -0.04em;
            white-space: nowrap;
            box-shadow: 0 1px 4px rgba(233,69,96,0.2);
        }
        .enhance-btns .btn.btn-enhance-main {
            flex: 0.85 1 0;
            min-height: 42px;
            height: 42px;
            font-size: 0.7rem;
        }
        .btn-swap-np {
            width: 100%;
            margin-top: 10px;
            background: linear-gradient(145deg, #2dd4bf, #0d9488);
            color: #042f2e;
            box-shadow: 0 4px 12px rgba(45,212,191,0.28);
            font-size: 0.9rem;
            font-weight: 800;
            min-height: 46px;
        }
        .swap-np-hint {
            margin-top: 6px;
            font-size: 0.72rem;
            color: rgba(236,253,245,0.55);
            line-height: 1.4;
            text-align: center;
        }
        .btn-cast {
            background: linear-gradient(135deg, #dc2626, #ea580c);
            color: #fff;
        }
        .btn-cast.magic {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }
        .btn-protect {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #fff;
        }
        .cast-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 8px;
        }
        .cast-btns.single {
            grid-template-columns: 1fr;
        }
        .btn-cast:disabled,
        .btn-protect:disabled { opacity: 0.45; }
        .btn-trial {
            background: linear-gradient(135deg, #a855f7, #ec4899);
            color: #fff;
            font-size: 0.88rem;
        }
        .btn-trial:disabled { opacity: 0.45; }
        .trial-section {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed rgba(255,255,255,0.12);
        }
        .trial-section h4 {
            margin: 0 0 8px;
            font-size: 0.88rem;
            color: var(--gold);
            font-weight: 700;
        }
        .btn-suho {
            background: linear-gradient(145deg, #ffd700, #c9a400);
            color: #1a0a2e;
            box-shadow: 0 4px 12px rgba(255,215,0,0.3);
        }
        .btn-buy-suho {
            background: linear-gradient(145deg, #74c0fc, #339af0);
            color: #fff;
            box-shadow: 0 4px 12px rgba(51,154,240,0.28);
            font-size: 0.88rem;
            min-height: 44px;
        }
        .btn-eunchong {
            background: linear-gradient(145deg, #51cf66, #2f9e44);
            color: #fff;
            box-shadow: 0 4px 12px rgba(81,207,102,0.35);
            min-height: 48px;
            font-size: 0.95rem;
        }

        /* 아이템 탭 */
        .item-tabs { margin-bottom: 14px; }
        .tab-bar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .tab-btn {
            position: relative;
            padding: 12px 10px;
            min-height: 46px;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text);
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, transform 0.08s;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .tab-btn:hover { background: rgba(255,255,255,0.04); }
        .tab-btn:active { transform: translateY(1px); }
        .tab-btn.active {
            background: linear-gradient(145deg, rgba(255,215,0,0.12), rgba(233,69,96,0.12));
            border-color: rgba(255,215,0,0.3);
            color: var(--gold);
        }
        .tab-count {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            font-size: 0.78rem;
            font-weight: 700;
            min-width: 24px;
        }
        .tab-count.blue { color: var(--blue); }
        .tab-count.green { color: var(--green); }
        .tab-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 8px var(--green);
            margin-left: 2px;
            animation: tab-pulse 1.2s ease-in-out infinite;
        }
        @keyframes tab-pulse {
            0%,100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        .tab-panel {
            display: none;
            margin-top: 10px;
            padding: 16px;
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            animation: tab-slide 0.18s ease-out;
        }
        .tab-panel.active { display: block; }
        @keyframes tab-slide {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .suho-row {
            display: flex;
            gap: 8px;
        }
        .suho-row input {
            flex: 1;
            padding: 12px 14px;
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            background: rgba(0,0,0,0.25);
            color: var(--text);
            font-size: 16px;
            text-align: center;
            -webkit-appearance: none;
            appearance: none;
        }
        .suho-row input:focus { outline: none; border-color: var(--gold); }
        .suho-row .btn-suho { flex: 0 0 auto; padding-left: 18px; padding-right: 18px; }

        .result {
            margin-top: 14px;
            padding: 14px 16px;
            border-radius: 12px;
            background: rgba(0,0,0,0.35);
            border: 1px solid rgba(255,255,255,0.08);
            font-size: 0.9rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
            box-sizing: border-box;
            height: 7.5rem;
            max-height: 7.5rem;
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            display: none;
        }
        .result.show { display: block; }
        .result.success { border-color: rgba(255,215,0,0.4); color: #ffd700; animation: sparkle 0.6s ease-out; }
        .result.fail { border-color: rgba(233,69,96,0.4); color: #ff8c9e; }
        .result.info { border-color: rgba(77,171,247,0.3); color: var(--blue); }
        @keyframes sparkle {
            0%,100% { box-shadow: 0 0 0 0 rgba(255,215,0,0); }
            50% { box-shadow: 0 0 24px 4px rgba(255,215,0,0.45); }
        }

        .hint { font-size: 0.78rem; color: var(--muted); margin-top: 6px; line-height: 1.5; }

        .break-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(0,0,0,0.72);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .break-modal.show { display: flex; }
        .break-modal-card {
            width: 100%;
            max-width: 360px;
            background: linear-gradient(165deg, #1e1535 0%, #16213e 100%);
            border: 1px solid rgba(233,69,96,0.45);
            border-radius: 18px;
            padding: 26px 22px 20px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.55);
            text-align: center;
        }
        .break-modal-icon { font-size: 2.4rem; margin-bottom: 8px; line-height: 1; }
        .break-modal-title {
            font-family: 'Black Han Sans', sans-serif;
            font-size: 1.55rem;
            color: #fca5a5;
            margin-bottom: 10px;
        }
        .break-modal-weapon {
            font-size: 0.92rem;
            color: var(--gold);
            margin-bottom: 12px;
            font-weight: 700;
        }
        .break-modal-desc {
            font-size: 0.9rem;
            color: var(--text);
            line-height: 1.55;
            margin-bottom: 8px;
        }
        .break-modal-hint {
            font-size: 0.78rem;
            color: var(--muted);
            line-height: 1.45;
            margin-bottom: 18px;
        }
        .break-modal-actions { display: flex; flex-direction: column; gap: 8px; }
        .break-modal-actions .btn { width: 100%; }
        .btn-break-buy {
            background: linear-gradient(135deg, #4dabf7, #228be6) !important;
            color: #fff !important;
            border: none !important;
        }
        .btn-break-close {
            background: transparent !important;
            border: 1px solid rgba(255,255,255,0.18) !important;
            color: var(--muted) !important;
            font-weight: 400 !important;
            min-height: 40px !important;
        }

        .history-list { display: flex; flex-direction: column; gap: 8px; max-height: 280px; overflow-y: auto; }
        .history-item {
            background: rgba(255,255,255,0.04);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.82rem;
            line-height: 1.45;
        }
        .history-item .hi-top { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 4px; }
        .history-item .hi-result { font-weight: 700; }
        .history-item .hi-result.success { color: #86efac; }
        .history-item .hi-result.fail_protect { color: #93c5fd; }
        .history-item .hi-result.fail_break { color: #fca5a5; }
        .history-item .hi-time { color: var(--muted); font-size: 0.75rem; white-space: nowrap; }
        .history-item .hi-sub { color: var(--muted); font-size: 0.76rem; }
        .history-empty { color: var(--muted); font-size: 0.82rem; text-align: center; padding: 12px 0; }
        .history-card { padding-bottom: 16px; }
        .history-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 0;
            margin: 0;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            font: inherit;
            text-align: left;
        }
        .history-toggle:hover .history-toggle-title { color: #ffe566; }
        .history-toggle-title {
            font-size: 0.95rem;
            color: var(--gold);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            transition: color 0.15s;
        }
        .history-chevron {
            flex-shrink: 0;
            color: var(--muted);
            font-size: 0.85rem;
            line-height: 1;
            transition: transform 0.2s ease;
        }
        .history-card.open .history-chevron { transform: rotate(180deg); }
        .history-body { margin-top: 10px; }
        .history-body[hidden] { display: none !important; }
        .bigmsg { text-align: center; padding: 24px 12px; color: var(--muted); font-size: 0.95rem; line-height: 1.6; }
        .bigmsg strong { color: var(--accent); }

        .cost-line { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px; font-size: 0.85rem; color: var(--muted); }
        .cost-line b { color: var(--text); font-weight: 700; }
        .cost-line b.gold { color: var(--gold); }
        .cost-line b.accent { color: var(--accent); }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.72rem;
            background: rgba(255,215,0,0.15);
            color: var(--gold);
            margin-left: 6px;
        }
        .badge.warn { background: rgba(233,69,96,0.15); color: var(--accent); }
        .badge.info { background: rgba(77,171,247,0.15); color: var(--blue); }
        .footer-nav {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 16px;
        }
        a.btn-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            text-decoration: none;
            padding: 12px 10px;
            min-height: 48px;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 0.88rem;
            font-weight: 700;
            color: #fff;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.12);
            background: linear-gradient(145deg, #2d3561, #20264a);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        a.btn-nav.gold {
            background: linear-gradient(145deg, #c9a227, #9a7b1a);
            border-color: rgba(255,215,0,0.25);
            color: #1a1208;
        }
        a.btn-nav:active { transform: translateY(1px); opacity: 0.92; }
        .footer-refresh {
            text-align: center;
            margin-top: 10px;
        }
        @media (max-width: 480px) {
            body { padding: 16px; }
            .wrap { padding: 22px 18px; border-radius: 20px; }
            h1 { font-size: 1.8rem; }
            .btn-row { grid-template-columns: repeat(2, 1fr); }
            .btn-row.specific { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>무기 강화</h1>
        <p class="sub">무기를 구매하고 강화해보세요</p>

        <?php if ($enchant_need_code) { ?>
            <div class="bigmsg">
                <p style="font-size:2rem;margin-bottom:10px;">🔒</p>
                <strong>코드를 부여받으세요.</strong><br>
                올바른 초대 코드로 접속해야 이용할 수 있어요.<br>
                <span style="opacity:0.7;">예) /page/enchant.php?code=XXXX</span>
                <div style="margin-top:14px;">
                    <a href="/page/enchant_roadmap.php" style="color:var(--gold);text-decoration:none;font-size:0.9rem;">📋 단계별 확률·강화비 표 보기</a>
                </div>
            </div>
        <?php } else { ?>

        <!-- 회원 정보 카드 -->
        <div class="card info-card">
            <h3>🎒 내 정보
                <?php if ($enchant_은총활성) { ?><span class="badge info" id="infoEunchongBadge"><?php echo htmlspecialchars($enchant_은총_label, ENT_QUOTES, 'UTF-8'); ?> 활성</span><?php } else { ?><span class="badge info" id="infoEunchongBadge" style="display:none;">은총 활성</span><?php } ?>
            </h3>
            <div class="info-compact">
                <div class="info-line">
                    <span class="il">닉네임</span>
                    <span class="iv"><?php echo htmlspecialchars($enchant_name, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-line">
                    <span class="il">본방냥</span>
                    <span class="iv blue" id="stNewpoint"><?php echo htmlspecialchars(enchant_본방냥_fmt($enchant_newpoint), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-line">
                    <span class="il">게임냥</span>
                    <span class="iv gold" id="stPoint"><?php echo htmlspecialchars($enchant_point_fmt, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-chips">
                    <span class="info-chip">
                        <span class="il">파손방지</span>
                        <span class="iv accent" id="stSuho"><?php echo number_format($enchant_enhance_suho); ?>회</span>
                    </span>
                    <span class="info-chip">
                        <span class="il">수호</span>
                        <span class="iv blue" id="stSuhoItem"><?php echo number_format($enchant_suho_item); ?>개</span>
                    </span>
                    <span class="info-chip">
                        <span class="il">은총</span>
                        <span class="iv green" id="stEunchongItem"><?php echo number_format($enchant_은총개수); ?>개</span>
                    </span>
                </div>
                <div class="info-line" id="stEunchongBuffBox" <?php if (!$enchant_은총활성) echo 'style="display:none;"'; ?>>
                    <span class="il" id="stEunchongBuffLabel"><?php echo htmlspecialchars($enchant_은총_label ?: '은총', ENT_QUOTES, 'UTF-8'); ?> 버프</span>
                    <span class="iv green" id="stEunchongBuff"
                        data-end="<?php echo htmlspecialchars($enchant_은총_end, ENT_QUOTES, 'UTF-8'); ?>"
                        data-left="<?php echo (int)$enchant_은총_left_sec; ?>">—</span>
                </div>
            </div>
        </div>

        <!-- 현재 무기 -->
        <?php if ($enchant_item !== '') { ?>
        <div class="weapon-display" id="weaponBox">
            <div class="emoji" id="weaponEmoji"><?php echo htmlspecialchars(mb_substr($enchant_item, 0, 1), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="weapon-line">
                <?php if ($enchant_style !== '') { ?>
                <div class="style" id="weaponStyle"><?php echo htmlspecialchars($enchant_style, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php } ?>
                <div class="name"><span id="weaponName"><?php echo htmlspecialchars(mb_substr($enchant_item, 1), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="level" id="weaponLevel">+<?php echo (int)$enchant_enhance; ?></span>
                </div>
            </div>
        </div>
        <?php } else { ?>
        <div class="weapon-display empty" id="weaponBox">
            <div class="emoji" id="weaponEmoji">⚔️</div>
            <div class="weapon-line">
                <div class="name" id="weaponName">무기 없음 · 구매해주세요</div>
            </div>
        </div>
        <?php } ?>

        <!-- 무기 구매 -->
        <div class="card" id="buyCard" <?php if ($enchant_item !== '') echo 'style="display:none;"'; ?>>
            <h3>🛒 무기 구매</h3>
            <div class="cost-line">
                <span>무기 구매 (단소/활/마법·랜덤)</span><b class="gold"><?php echo htmlspecialchars($enchant_buy_cost_fmt, ENT_QUOTES, 'UTF-8'); ?></b>
            </div>
            <div class="btn-row specific" style="margin-top:10px;">
                <button type="button" class="btn btn-buy-random" data-weapon="랜덤" style="grid-column:1/-1;">🎲 랜덤 <?php echo htmlspecialchars($enchant_buy_cost_fmt, ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
            <div class="btn-row specific" style="margin-top:8px;">
                <button type="button" class="btn btn-buy" data-weapon="단소">🪈 단소</button>
                <button type="button" class="btn btn-buy" data-weapon="활">🏹 활</button>
                <button type="button" class="btn btn-buy" data-weapon="마법">🪄 마법</button>
            </div>
            <div class="hint">무기 구매는 보유 무기가 없을 때만 가능합니다.</div>
            <?php
                $enchant_guide_q = $enchant_code !== '' ? ('?code=' . rawurlencode($enchant_code)) : '';
            ?>
            <a class="btn btn-guide" href="/page/enchant_weapon_guide.php<?php echo htmlspecialchars($enchant_guide_q, ENT_QUOTES, 'UTF-8'); ?>" style="display:flex;align-items:center;justify-content:center;margin-top:10px;text-decoration:none;background:linear-gradient(145deg,#3d4a7a,#2a3358);border:1px solid rgba(126,184,255,0.28);">📖 구매한 무기 사용방법</a>
        </div>

        <!-- 결과 (강화/수호/은총·구매 메시지) -->
        <div class="result" id="result" role="status"></div>

        <!-- 강화 -->
        <div class="card" id="enhanceCard" <?php if ($enchant_item === '') echo 'style="display:none;"'; ?>>
            <h3>⚒️ 무기 강화
                <?php if ($ENCHANT_할인적용) { ?><span class="badge">50% 할인중</span><?php } ?>
                <span class="badge info" id="buffEunchongBadge" <?php if (!$enchant_은총활성) echo 'style="display:none;"'; ?>><?php
                    if ((int)$enchant_은총_zeros >= 2 || $enchant_은총_label === '메가은총') {
                        echo '✨ 메가은총 (확률↑↑ · 비용할인 없음)';
                    } else {
                        echo '✨ 은총 (확률↑ · 비용 50%)';
                    }
                ?></span>
            </h3>
            <div class="cost-line">
                <span>다음 단계</span>
                <b class="accent" id="levNext">+<?php echo (int)$enchant_enhance; ?> → +<?php echo min($ENCHANT_강화최대, (int)$enchant_enhance + 1); ?></b>
            </div>
            <div class="cost-line">
                <span>소요 비용</span>
                <b class="gold" id="costNext"><?php echo htmlspecialchars($enchant_cost_next_fmt, ENT_QUOTES, 'UTF-8'); ?></b>
            </div>
            <div class="cost-line">
                <span>성공 확률</span>
                <b class="gold" id="rateNext"><?php echo htmlspecialchars($enchant_rate_str, ENT_QUOTES, 'UTF-8'); ?></b>
            </div>
            <?php if ($enchant_durability > 0) { ?>
            <div class="cost-line">
                <span>예상 최대 내구도</span>
                <b id="durability"><?php echo (int)$enchant_durability; ?></b>
            </div>
            <?php } ?>
            <label class="mega-booster-wrap<?php echo ($enchant_은총활성 && (int)$enchant_은총_tier === 2) ? ' show' : ''; ?>" id="megaBoosterWrap">
                <input type="checkbox" id="megaBoosterChk">
                <span>부스터 <small>강화비 ×3 추가 · 0 하나 더 (총 3개)</small></span>
            </label>
            <div class="enhance-btns">
                <?php
                $enchant_has_weapon = ($enchant_item !== '' && (int)$enchant_enhance < (int)$ENCHANT_강화최대);
                $enchant_promo_style = $enchant_has_weapon ? '' : ' style="display:none"';
                $enchant_batch5000_ok = $enchant_has_weapon && (int)$enchant_enhance >= enchant_batch_5000_min_enhance();
                $enchant_batch5000_style = $enchant_batch5000_ok ? '' : ' style="display:none"';
                ?>
                <button type="button" class="btn btn-enhance btn-enhance-main" id="btnEnhance">⚔️ 1회</button>
                <button type="button" class="btn btn-enhance btn-enhance-batch" data-times="100" id="btnEnhanceBatch100"<?= $enchant_promo_style ?>>⚔️ 100</button>
                <button type="button" class="btn btn-enhance btn-enhance-batch" data-times="500" id="btnEnhanceBatch500"<?= $enchant_promo_style ?>>⚔️ 500</button>
                <button type="button" class="btn btn-enhance btn-enhance-batch" data-times="1000" id="btnEnhanceBatch1000"<?= $enchant_promo_style ?>>⚔️ 1000(10냥)</button>
                <button type="button" class="btn btn-enhance btn-enhance-batch" data-times="3000" id="btnEnhanceBatch3000"<?= $enchant_promo_style ?>>⚔️ 3000(30냥)</button>
                <button type="button" class="btn btn-enhance btn-enhance-batch" data-times="5000" id="btnEnhanceBatch5000"<?= $enchant_batch5000_style ?>>⚔️ 5000(50냥)</button>
            </div>
        </div>

        <!-- 아이템 사용 (탭) -->
        <div class="item-tabs">
            <div class="tab-bar">
                <button type="button" class="tab-btn" data-tab="suho">
                    👼 수호 <span class="tab-count blue" id="tabSuhoCount"><?php echo number_format($enchant_suho_item); ?></span>
                </button>
                <button type="button" class="tab-btn" data-tab="eunchong">
                    ✨ 은총 <span class="tab-count green" id="tabEunchongCount"><?php echo number_format($enchant_은총개수); ?></span>
                    <?php if ($enchant_은총활성) { ?><span class="tab-dot" id="tabEunchongDot" title="버프 중"></span><?php } else { ?><span class="tab-dot" id="tabEunchongDot" style="display:none;" title="버프 중"></span><?php } ?>
                </button>
            </div>

            <!-- 수호 패널 -->
            <div class="tab-panel" data-panel="suho">
                <div class="cost-line" id="suhoProtectLine">
                    <span>파손방지 누적</span>
                    <b class="accent" id="suhoStackMini"><?php echo number_format($enchant_enhance_suho); ?>회</b>
                </div>
                <div id="suhoUseSection" <?php if ($enchant_suho_item < 1) echo 'style="display:none;"'; ?>>
                    <div class="cost-line">
                        <span>수호 아이템 보유</span>
                        <b class="blue" id="suhoItemMini"><?php echo number_format($enchant_suho_item); ?>개 보유</b>
                    </div>
                    <div class="cost-line">
                        <span>1개당 +1~+3 누적 (랜덤) · 실패 시 아이템 1개 자동 소모</span>
                    </div>
                    <div class="suho-row" style="margin-top:10px;">
                        <input type="number" id="suhoCount" min="1" max="999" placeholder="사용 개수" value="1" inputmode="numeric">
                        <button type="button" class="btn btn-suho" id="btnUseSuho">👼 강화 수호로 전환</button>
                    </div>
                </div>
                <div id="suhoBuySection">
                    <div class="cost-line" id="suhoBuyAlert" <?php if ($enchant_suho_item > 0 || $enchant_enhance_suho > 0) echo 'style="display:none;"'; ?>>
                        <span style="color:var(--accent);">⚠️ 파손 방지 없음 — 강화 수호 구매 필요</span>
                    </div>
                    <div class="cost-line">
                        <span>강화 수호 1회 비용 (본방냥 · 1회 +1~+3 누적)</span>
                        <b class="blue" id="suhoPriceMini"><?php echo htmlspecialchars($enchant_suho_price_fmt, ENT_QUOTES, 'UTF-8'); ?></b>
                    </div>
                    <div class="btn-row c2" style="margin-top:10px;">
                        <button type="button" class="btn btn-buy-suho" data-buy-enhance-suho="1">👼 강화 수호 1개 구매</button>
                        <button type="button" class="btn btn-buy-suho" id="btnBuyEnhanceSuhoMax"
                            data-buy-enhance-suho="<?php echo (int)$enchant_suho_max_buy; ?>"
                            data-suho-gain="<?php echo (int)$enchant_suho_max_gain; ?>"
                            data-suho-cost="<?php echo htmlspecialchars($enchant_suho_max_cost, ENT_QUOTES, 'UTF-8'); ?>"
                            data-suho-exp="<?php echo (int)$enchant_suho_max_exp; ?>"
                            data-suho-token="<?php echo htmlspecialchars($enchant_suho_max_token, ENT_QUOTES, 'UTF-8'); ?>"
                            <?php if ((int)$enchant_suho_max_gain < 1) echo 'style="display:none;"'; ?>>
                            👼 <?php echo number_format((int)$enchant_suho_max_gain); ?>개 구매하기
                        </button>
                    </div>
                    <div class="cost-line" style="margin-top:8px;">
                        <span>본방 10냥 남김 · 회당 +1~+3 미리뽑기(새로고침 시 다시 뽑음) · 버튼 수치 그대로 지급</span>
                    </div>
                </div>
                <div class="hint">실패 시: 파손방지 누적 → 수호 아이템 순으로 자동 사용 · 둘 다 없으면 무기 파손</div>
            </div>

            <!-- 은총 패널 -->
            <div class="tab-panel" data-panel="eunchong">
                <div class="cost-line">
                    <span>은총: 0 1개 제거 + 강화비 50% (5분) · 메가은총(10개): 0 2개 제거 · 강화비 할인 없음 (10분, 연장 가능)</span>
                </div>
                <div class="cost-line">
                    <span>보유 은총</span>
                    <b class="green" id="eunchongItemMini"><?php echo number_format($enchant_은총개수); ?>개</b>
                </div>
                <div class="cost-line" id="eunchongBuffLine" <?php if (!$enchant_은총활성) echo 'style="display:none;"'; ?>>
                    <span id="eunchongBuffKind"><?php echo htmlspecialchars($enchant_은총_label ?: '은총', ENT_QUOTES, 'UTF-8'); ?> 남은 시간</span>
                    <b class="green" id="eunchongBuffText">—</b>
                </div>
                <div class="btn-row" style="margin-top:10px;">
                    <button type="button" class="btn btn-eunchong" id="btnUseEunchong" data-tier="1">✨ 은총 사용</button>
                    <button type="button" class="btn btn-eunchong" id="btnUseMegaEunchong" data-tier="2"<?php if (!(int)$enchant_은총_show_mega) echo ' style="display:none;"'; ?>>✨ 메가은총 (10개)</button>
                </div>
                <div class="hint">+14강 이상 무기만 사용 가능 · 이미 버프 중이면 현재 종료시각에서 연장(은총 +5분 · 메가 +10분) · 채굴 은총과 버프를 공유합니다</div>
            </div>
        </div>

        <!-- +10 맛보기 -->
        <?php
        $enchant_is_attack = function_exists('무기_단소활인가')
            ? 무기_단소활인가(['item' => $enchant_item, '무기타입' => $enchant_type ?? 0])
            : false;
        $enchant_is_magic = function_exists('무기_마법인가')
            ? 무기_마법인가(['item' => $enchant_item, '무기타입' => $enchant_type ?? 0])
            : ($enchant_item === '🪄마법' || $enchant_item === '🪄 마법');
        
        $enchant_show_cast = ($enchant_item !== '' && (int)$enchant_enhance >= 10 && ($enchant_is_attack || $enchant_is_magic));
        ?>
        <div class="card history-card" id="castCard" <?php if (!$enchant_show_cast) echo 'style="display:none;"'; ?>>
            <button type="button" class="history-toggle" id="castToggle" aria-expanded="false" aria-controls="castBody">
                <span class="history-toggle-title" id="castCardTitle"><?php echo $enchant_is_magic ? '✨ 마법 맛보기' : '⚔️ 무기 공격 맛보기'; ?></span>
                <span class="history-chevron" aria-hidden="true">▼</span>
            </button>
            <div class="history-body" id="castBody" hidden>
            <?php if ($enchant_is_magic) { ?>
            <div class="cost-line">
                <span>맛보기 시전 (하루 1회)</span>
                <b class="blue" id="trialCastStatus"><?php echo $enchant_trial_cast_available ? '가능' : '사용 완료'; ?></b>
            </div>
            <div class="cost-line" id="trialProtectStatusRow">
                <span>맛보기 보호 (하루 1회)</span>
                <b class="blue" id="trialProtectStatus"><?php echo $enchant_trial_protect_available ? '가능' : '사용 완료'; ?></b>
            </div>
            <?php } else { ?>
            <div class="cost-line">
                <span>오늘 맛보기 (하루 1회)</span>
                <b class="blue" id="trialCastStatus"><?php echo $enchant_trial_cast_available ? '가능' : '사용 완료'; ?></b>
            </div>
            <div class="cost-line" id="trialProtectStatusRow" style="display:none;">
                <span>맛보기 보호 (하루 1회)</span>
                <b class="blue" id="trialProtectStatus">—</b>
            </div>
            <?php } ?>
            <div class="cast-btns<?php echo $enchant_is_magic ? '' : ' single'; ?>" id="trialBtnRow">
                <button type="button" class="btn btn-trial" id="btnTrialCast">
                    <?php echo $enchant_is_magic ? '👅 맛보기 시전' : '👅 맛보기 공격'; ?>
                </button>
                <button type="button" class="btn btn-trial" id="btnTrialProtect"<?php if (!$enchant_is_magic) echo ' style="display:none;"'; ?>>👅 맛보기 보호</button>
            </div>
            <div class="hint" id="trialHint"><?php
                echo $enchant_is_magic
                    ? '시전·보호 각 하루 1회 · 한도·내구도·본인 비용 없음'
                    : '한도·내구도·본인 비용 없음 · 대상 효과는 실제 적용';
            ?></div>
            </div>
        </div>

        <div class="card history-card" id="historyCard">
            <button type="button" class="history-toggle" id="historyToggle" aria-expanded="false" aria-controls="historyBody">
                <span class="history-toggle-title">📜 강화 이력 <span class="badge info">최근 15건</span></span>
                <span class="history-chevron" aria-hidden="true">▼</span>
            </button>
            <div class="history-body" id="historyBody" hidden>
                <div class="history-list" id="historyList">
                    <div class="history-empty">펼치면 최근 기록을 불러옵니다.</div>
                </div>
            </div>
        </div>

        <?php
            $enchant_nav_q = $enchant_code !== '' ? ('?code=' . rawurlencode($enchant_code)) : '';
        ?>
        <div class="footer-nav">
            <a class="btn-nav" href="/page/enchant_weapon_guide.php<?php echo htmlspecialchars($enchant_nav_q, ENT_QUOTES, 'UTF-8'); ?>" style="grid-column:1/-1;border-color:rgba(126,184,255,0.35);color:#bfdbfe;">📖 구매한 무기 사용방법</a>
            <a class="btn-nav gold" href="/page/enchant_roadmap.php<?php echo htmlspecialchars($enchant_nav_q, ENT_QUOTES, 'UTF-8'); ?>">📋 단계별 확률·강화비</a>
            <a class="btn-nav" href="/page/enchant_break_restore.php<?php echo htmlspecialchars($enchant_nav_q, ENT_QUOTES, 'UTF-8'); ?>" style="border-color:rgba(248,113,113,0.45);color:#fca5a5;">💥 파손 복구 (+20↑)</a>
        </div>
        <div class="footer-refresh">
            <button type="button" class="btn" id="btnRefresh" style="background:transparent;border:1px solid rgba(255,255,255,0.15);color:var(--muted);font-weight:400;min-height:36px;padding:6px 14px;">새로고침</button>
        </div>

        <div class="break-modal" id="breakSuhoModal" role="dialog" aria-modal="true" aria-labelledby="breakSuhoTitle" hidden>
            <div class="break-modal-card">
                <div class="break-modal-icon">💥</div>
                <div class="break-modal-title" id="breakSuhoTitle">무기가 깨졌어요!</div>
                <div class="break-modal-weapon" id="breakSuhoWeapon"></div>
                <div class="break-modal-desc">강화 수호를 구매하면<br>무기 파손이 방지됩니다.</div>
                <div class="break-modal-hint">게임냥이 부족하면 본방냥 50%를<br>자동 스왑한 뒤 구매합니다.</div>
                <div class="break-modal-actions">
                    <button type="button" class="btn btn-break-buy" id="btnBreakBuySuho100">👼 강화 수호 100개 구매하기</button>
                    <button type="button" class="btn btn-break-close" id="btnBreakSuhoClose">닫기</button>
                </div>
            </div>
        </div>

        <?php } // end has code ?>
    </div>

    <?php if (!$enchant_need_code) { ?>
    <script>
    (function() {
        var CODE = <?php echo json_encode($enchant_code, JSON_UNESCAPED_UNICODE); ?>;
        var BASE = location.pathname;

        var $ = function(id) { return document.getElementById(id); };
        var state = {
            point: <?php echo json_encode((string)$enchant_point, JSON_UNESCAPED_UNICODE); ?>,
            point_fmt: <?php echo json_encode($enchant_point_fmt, JSON_UNESCAPED_UNICODE); ?>,
            newpoint: <?php echo json_encode(round((float)$enchant_newpoint, 1)); ?>,
            newpoint_fmt: <?php echo json_encode(enchant_본방냥_fmt($enchant_newpoint), JSON_UNESCAPED_UNICODE); ?>,
            item: <?php echo json_encode($enchant_item, JSON_UNESCAPED_UNICODE); ?>,
            weaponType: <?php echo (int)($enchant_type ?? 0); ?>,
            enhance: <?php echo (int)$enchant_enhance; ?>,
            enhance_suho: <?php echo (int)$enchant_enhance_suho; ?>,
            suho_item: <?php echo (int)$enchant_suho_item; ?>,
            suho_price: <?php echo json_encode((string)$enchant_suho_price, JSON_UNESCAPED_UNICODE); ?>,
            suho_price_fmt: <?php echo json_encode($enchant_suho_price_fmt, JSON_UNESCAPED_UNICODE); ?>,
            suho_max_buy: <?php echo (int)$enchant_suho_max_buy; ?>,
            suho_max_gain: <?php echo (int)$enchant_suho_max_gain; ?>,
            suho_max_cost: <?php echo json_encode((string)$enchant_suho_max_cost, JSON_UNESCAPED_UNICODE); ?>,
            suho_max_exp: <?php echo (int)$enchant_suho_max_exp; ?>,
            suho_max_token: <?php echo json_encode((string)$enchant_suho_max_token, JSON_UNESCAPED_UNICODE); ?>,
            cost_next: <?php echo json_encode((string)$enchant_cost_next, JSON_UNESCAPED_UNICODE); ?>,
            cost_next_fmt: <?php echo json_encode($enchant_cost_next_fmt, JSON_UNESCAPED_UNICODE); ?>,
            success_rate: <?php echo json_encode($enchant_rate_str, JSON_UNESCAPED_UNICODE); ?>,
            max_level: <?php echo (int)$ENCHANT_강화최대; ?>,
            batch_5000_min_enhance: <?php echo (int)enchant_batch_5000_min_enhance(); ?>,
            style: <?php echo json_encode($enchant_style, JSON_UNESCAPED_UNICODE); ?>,
            durability: <?php echo (int)$enchant_durability; ?>,
            은총활성: <?php echo $enchant_은총활성 ? 1 : 0; ?>,
            은총개수: <?php echo (int)$enchant_은총개수; ?>,
            은총_end: <?php echo json_encode($enchant_은총_end, JSON_UNESCAPED_UNICODE); ?>,
            은총_left_sec: <?php echo (int)$enchant_은총_left_sec; ?>,
            은총_tier: <?php echo (int)$enchant_은총_tier; ?>,
            은총_label: <?php echo json_encode($enchant_은총_label, JSON_UNESCAPED_UNICODE); ?>,
            은총_zeros: <?php echo (int)$enchant_은총_zeros; ?>,
            은총_cost_discount: <?php echo (int)$enchant_은총_cost_discount; ?>,
            은총_mega_cost: <?php echo (int)$enchant_은총_mega_cost; ?>,
            은총_show_mega: <?php echo (int)$enchant_은총_show_mega; ?>,
            trial_cast_available: <?php echo (int)$enchant_trial_cast_available; ?>,
            trial_protect_available: <?php echo (int)$enchant_trial_protect_available; ?>
        };

        function fmt(n) { return (n || 0).toLocaleString('ko-KR'); }
        function fmtNyang(fmt, n) {
            if (fmt) return fmt;
            return fmt(n || 0) + '냥';
        }

        function showResult(text, kind) {
            var r = $('result');
            r.className = 'result show ' + (kind || 'info');
            r.textContent = text;
        }

        function ajax(params, onDone) {
            params.code = CODE;
            var body = Object.keys(params).map(function(k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            }).join('&');
            fetch(BASE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
                credentials: 'same-origin'
            }).then(function(r) { return r.json(); })
            .then(function(j) { onDone(j); })
            .catch(function(e) { onDone({ ok: false, data: '통신 오류' }); });
        }

        var statusReqId = 0;
        var enhancing = false;
        /** 강화 성공/반영마다 증가 — 은총·status 응답이 예전 enhance로 UI를 되돌리지 않게 */
        var enhanceGen = 0;
        function refreshStatus() {
            var reqId = ++statusReqId;
            var genAtStart = enhanceGen;
            ajax({ action: 'status' }, function(j) {
                // 더 최신 status/강화 응답이 있으면 무시 (은총 직후 → 강화 레이스 방지)
                if (reqId !== statusReqId) return;
                if (enhancing) return;
                if (genAtStart !== enhanceGen) return;
                if (!j.ok) { showResult(j.data || '상태 조회 실패', 'fail'); return; }
                applyStatusFromResponse(j);
                renderAll();
            });
        }

        function fmtSec(s) {
            s = Math.max(0, parseInt(s || 0, 10));
            var m = Math.floor(s / 60);
            var r = s % 60;
            return m + '분 ' + (r < 10 ? '0' : '') + r + '초';
        }
        function eunchongBadgeText() {
            var label = state.은총_label || '은총';
            var zeros = parseInt(state.은총_zeros || 0, 10);
            if (zeros >= 2 || label === '메가은총') {
                return '✨ 메가은총 (확률↑↑ · 비용할인 없음)';
            }
            return '✨ 은총 (확률↑ · 비용 50%)';
        }
        function isMegaBoosterActivePeriod() {
            return !!(state.은총활성 && parseInt(state.은총_left_sec || 0, 10) > 0 && parseInt(state.은총_tier || 0, 10) === 2);
        }
        function isMegaBoosterChecked() {
            var chk = $('megaBoosterChk');
            return !!(chk && chk.checked && isMegaBoosterActivePeriod());
        }
        function updateMegaBoosterUi() {
            var wrap = $('megaBoosterWrap');
            var chk = $('megaBoosterChk');
            if (!wrap) return;
            var show = isMegaBoosterActivePeriod();
            if (show) wrap.classList.add('show');
            else {
                wrap.classList.remove('show');
                if (chk) chk.checked = false;
            }
        }
        function renderEunchong() {
            var active = state.은총활성 && state.은총_left_sec > 0;
            var cnt = state.은총개수 || 0;
            var megaCost = parseInt(state.은총_mega_cost || 10, 10);
            var label = state.은총_label || '은총';
            var itemEl = $('stEunchongItem');
            if (itemEl) itemEl.textContent = fmt(cnt) + '개';
            var mini = $('eunchongItemMini');
            if (mini) mini.textContent = fmt(cnt) + '개';
            var tabCnt = $('tabEunchongCount');
            if (tabCnt) tabCnt.textContent = fmt(cnt);
            var dot = $('tabEunchongDot');
            if (dot) dot.style.display = active ? '' : 'none';

            var buffBox = $('stEunchongBuffBox');
            var buffEl = $('stEunchongBuff');
            var badge = $('buffEunchongBadge');
            var infoBadge = $('infoEunchongBadge');
            var buffLine = $('eunchongBuffLine');
            var buffText = $('eunchongBuffText');
            var buffLabel = $('stEunchongBuffLabel');
            var buffKind = $('eunchongBuffKind');
            if (active) {
                if (buffBox) buffBox.style.display = '';
                if (buffEl) buffEl.textContent = fmtSec(state.은총_left_sec);
                if (badge) { badge.style.display = ''; badge.textContent = eunchongBadgeText(); }
                if (infoBadge) { infoBadge.style.display = ''; infoBadge.textContent = label + ' 활성'; }
                if (buffLine) buffLine.style.display = '';
                if (buffText) buffText.textContent = fmtSec(state.은총_left_sec);
                if (buffLabel) buffLabel.textContent = label + ' 버프';
                if (buffKind) buffKind.textContent = label + ' 남은 시간';
            } else {
                if (buffBox) buffBox.style.display = 'none';
                if (badge) badge.style.display = 'none';
                if (infoBadge) infoBadge.style.display = 'none';
                if (buffLine) buffLine.style.display = 'none';
            }
            var canWeapon = !!(state.item && state.enhance >= 14 && state.enhance < state.max_level);
            var btnE = $('btnUseEunchong');
            if (btnE) {
                var canUse = cnt >= 1 && canWeapon;
                btnE.disabled = !canUse;
                if (cnt < 1) btnE.textContent = '✨ 은총 없음';
                else if (!state.item || state.enhance < 14) btnE.textContent = '✨ +14강 이상 무기만 사용 가능';
                else if (state.enhance >= state.max_level) btnE.textContent = '✨ 최대 강화 달성';
                else btnE.textContent = '✨ 은총 사용 (1개)';
            }
            var btnM = $('btnUseMegaEunchong');
            if (btnM) {
                var showMega = cnt >= megaCost;
                btnM.style.display = showMega ? '' : 'none';
                btnM.disabled = !(showMega && canWeapon);
                if (!canWeapon && showMega) btnM.textContent = '✨ +14강 이상 무기만 사용 가능';
                else btnM.textContent = '✨ 메가은총 (' + megaCost + '개)';
            }
            updateMegaBoosterUi();
        }

        function setActiveTab(name) {
            var btns = document.querySelectorAll('.tab-btn');
            var panels = document.querySelectorAll('.tab-panel');
            btns.forEach(function(b) {
                b.classList.toggle('active', b.getAttribute('data-tab') === name);
            });
            panels.forEach(function(p) {
                p.classList.toggle('active', p.getAttribute('data-panel') === name);
            });
        }
        function toggleTab(name) {
            var btn = document.querySelector('.tab-btn[data-tab="' + name + '"]');
            if (btn && btn.classList.contains('active')) {
                // 같은 탭 다시 누르면 닫기
                setActiveTab(null);
            } else {
                setActiveTab(name);
            }
        }

        function renderWeapon() {
            var box = $('weaponBox');
            var em = $('weaponEmoji');
            var nm = $('weaponName');
            var lv = $('weaponLevel');
            var st = $('weaponStyle');
            if (state.item) {
                var emoji = Array.from(state.item)[0] || '⚔️';
                var name = state.item.substring(emoji.length);
                box.classList.remove('empty');
                em.textContent = emoji;
                nm.innerHTML = '';
                nm.appendChild(document.createTextNode(name + ' '));
                if (!lv) {
                    lv = document.createElement('span');
                    lv.id = 'weaponLevel';
                    lv.className = 'level';
                    nm.appendChild(lv);
                } else {
                    nm.appendChild(lv);
                }
                lv.textContent = '+' + state.enhance;
                if (state.style) {
                    if (!st) {
                        st = document.createElement('div');
                        st.id = 'weaponStyle';
                        st.className = 'style';
                        var line = box.querySelector('.weapon-line');
                        var nameEl = line && line.querySelector('.name');
                        if (line && nameEl) line.insertBefore(st, nameEl);
                        else if (line) line.insertBefore(st, line.firstChild);
                        else box.appendChild(st);
                    }
                    st.textContent = state.style;
                    st.style.display = '';
                } else if (st) {
                    st.style.display = 'none';
                }
                $('buyCard').style.display = 'none';
                $('enhanceCard').style.display = '';
            } else {
                box.classList.add('empty');
                em.textContent = '⚔️';
                nm.textContent = '무기 없음 · 구매해주세요';
                if (st) st.style.display = 'none';
                $('buyCard').style.display = '';
                $('enhanceCard').style.display = 'none';
            }
        }

        function renderHistory(items) {
            var box = $('historyList');
            if (!box) return;
            if (!items || !items.length) {
                box.innerHTML = '<div class="history-empty">기록이 없습니다.</div>';
                return;
            }
            var labels = { success: '⚔️ 성공', fail_protect: '👼 실패·수호', fail_break: '💥 실패·파손' };
            box.innerHTML = items.map(function(row) {
                var result = row.result || '';
                var label = labels[result] || result;
                var weapon = row.item || '(무기)';
                if (row.style) weapon = row.style + ' ' + weapon;
                var before = parseInt(row.enhance_before, 10) || 0;
                var after = parseInt(row.enhance_after, 10) || 0;
                var change = result === 'success' ? ('+' + before + ' → +' + after)
                    : (result === 'fail_protect' ? ('+' + before + ' 유지') : ('+' + before + ' 파손'));
                var time = row.regdate ? String(row.regdate).substring(5, 16).replace('T', ' ') : '';
                var extra = [];
                if (row.challenge_mode === '1' || row.challenge_mode === 1) {
                    extra.push('+20도전' + (row.challenge_target ? '[' + row.challenge_target + ']' : ''));
                }
                if (row.eunchong === '1' || row.eunchong === 1) extra.push('은총');
                if (row.suho_used === '1' || row.suho_used === 1) extra.push('수호소모');
                var sub = '주사위 ' + (row.dice_roll || 0) + ' · ' + fmtNyang(row.cost_fmt, row.cost || 0);
                if (extra.length) sub += ' · ' + extra.join(' · ');
                return '<div class="history-item">'
                    + '<div class="hi-top"><span class="hi-result ' + result + '">' + label + '</span><span class="hi-time">' + time + '</span></div>'
                    + '<div>' + weapon + ' ' + change + '</div>'
                    + '<div class="hi-sub">' + sub + '</div>'
                    + '</div>';
            }).join('');
        }

        var historyOpen = false;
        var castOpen = false;

        function setCastOpen(open) {
            castOpen = !!open;
            var card = $('castCard');
            var toggle = $('castToggle');
            var body = $('castBody');
            if (card) card.classList.toggle('open', castOpen);
            if (toggle) toggle.setAttribute('aria-expanded', castOpen ? 'true' : 'false');
            if (body) body.hidden = !castOpen;
        }

        function toggleCast() {
            setCastOpen(!castOpen);
        }

        function setHistoryOpen(open) {
            historyOpen = !!open;
            var card = $('historyCard');
            var toggle = $('historyToggle');
            var body = $('historyBody');
            if (card) card.classList.toggle('open', historyOpen);
            if (toggle) toggle.setAttribute('aria-expanded', historyOpen ? 'true' : 'false');
            if (body) body.hidden = !historyOpen;
            if (historyOpen) {
                var box = $('historyList');
                if (box && !box.querySelector('.history-item')) {
                    box.innerHTML = '<div class="history-empty">불러오는 중…</div>';
                }
                loadHistory();
            }
        }

        function toggleHistory() {
            setHistoryOpen(!historyOpen);
        }

        function loadHistory() {
            ajax({ action: 'history', limit: 15 }, function(j) {
                if (!j.ok) {
                    renderHistory([]);
                    return;
                }
                renderHistory(j.items || []);
            });
        }

                function isAttackWeapon(item) {
            var t = parseInt(state.weaponType || 0, 10);
            if (t === 1 || t === 2) return true;
            if (!item) return false;
            return item === '🪈단소' || item === '🏹활' || item === '🏹 활'
                || item.indexOf('젓가락') >= 0 || item.indexOf('리코더') >= 0
                || item.indexOf('피리') >= 0 || item.indexOf('대금') >= 0
                || item.indexOf('플루트') >= 0 || item.indexOf('생황') >= 0 || item.indexOf('나발') >= 0
                || item.indexOf('태평소') >= 0 || item.indexOf('엑스칼리버') >= 0
                || item.indexOf('단소') >= 0;
        }
        function isMagicWeapon(item) {
            var t = parseInt(state.weaponType || 0, 10);
            if (t === 3) return true;
            return item === '🪄마법' || item === '🪄 마법';
        }

        function renderTrialCast() {
            var box = $('castCard');
            var title = $('castCardTitle');
            var castStatus = $('trialCastStatus');
            var protectStatus = $('trialProtectStatus');
            var protectStatusRow = $('trialProtectStatusRow');
            var btnTrialCast = $('btnTrialCast');
            var btnTrialProtect = $('btnTrialProtect');
            var trialRow = $('trialBtnRow');
            var hint = $('trialHint');
            if (!box) return;
            var isAttack = isAttackWeapon(state.item);
            var isMagic = isMagicWeapon(state.item);
            var show = state.item && state.enhance >= 10 && (isAttack || isMagic);
            box.style.display = show ? '' : 'none';
            if (!show) return;

            if (title) title.textContent = isMagic ? '✨ 마법 맛보기' : '⚔️ 무기 공격 맛보기';
            var castAvailable = !!state.trial_cast_available;
            var protectAvailable = !!state.trial_protect_available;

            if (isMagic) {
                if (protectStatusRow) protectStatusRow.style.display = '';
                if (castStatus && castStatus.parentElement) {
                    castStatus.parentElement.querySelector('span').textContent = '맛보기 시전 (하루 1회)';
                }
                if (castStatus) castStatus.textContent = castAvailable ? '가능' : '사용 완료';
                if (protectStatus) protectStatus.textContent = protectAvailable ? '가능' : '사용 완료';
            } else {
                if (protectStatusRow) protectStatusRow.style.display = 'none';
                if (castStatus && castStatus.parentElement) {
                    castStatus.parentElement.querySelector('span').textContent = '오늘 맛보기 (하루 1회)';
                }
                if (castStatus) castStatus.textContent = castAvailable ? '가능' : '사용 완료';
            }

            if (hint) {
                hint.textContent = isMagic
                    ? '시전·보호 각 하루 1회 · 한도·내구도·본인 비용 없음'
                    : '한도·내구도·본인 비용 없음 · 대상 효과는 실제 적용';
            }
            if (trialRow) trialRow.className = 'cast-btns' + (isMagic ? '' : ' single');
            if (btnTrialProtect) btnTrialProtect.style.display = isMagic ? '' : 'none';
            if (btnTrialCast) {
                btnTrialCast.disabled = !castAvailable;
                btnTrialCast.textContent = isMagic ? '👅 맛보기 시전' : '👅 맛보기 공격';
            }
            if (btnTrialProtect) btnTrialProtect.disabled = !protectAvailable;
        }

        function renderSuhoPanel() {
            var stack = state.enhance_suho || 0;
            var items = state.suho_item || 0;
            var stackMini = $('suhoStackMini');
            if (stackMini) stackMini.textContent = fmt(stack) + '회';
            var useSec = $('suhoUseSection');
            var buyAlert = $('suhoBuyAlert');
            if (useSec) useSec.style.display = items > 0 ? '' : 'none';
            if (buyAlert) buyAlert.style.display = (items < 1 && stack < 1) ? '' : 'none';
            var mini = $('suhoItemMini');
            if (mini) mini.textContent = fmt(items) + '개 보유';
            var priceMini = $('suhoPriceMini');
            if (priceMini) priceMini.textContent = fmtNyang(state.suho_price_fmt, state.suho_price || 0);
            var tabSuho = $('tabSuhoCount');
            if (tabSuho) tabSuho.textContent = fmt(items);
            var maxBtn = $('btnBuyEnhanceSuhoMax');
            if (maxBtn) {
                var qty = parseInt(state.suho_max_buy || 0, 10) || 0;
                var gain = parseInt(state.suho_max_gain || 0, 10) || 0;
                maxBtn.setAttribute('data-buy-enhance-suho', String(qty));
                maxBtn.setAttribute('data-suho-gain', String(gain));
                maxBtn.setAttribute('data-suho-cost', String(state.suho_max_cost || '0'));
                maxBtn.setAttribute('data-suho-exp', String(state.suho_max_exp || 0));
                maxBtn.setAttribute('data-suho-token', String(state.suho_max_token || ''));
                if (gain >= 1 && qty >= 1) {
                    maxBtn.style.display = '';
                    maxBtn.disabled = false;
                    maxBtn.textContent = '👼 ' + fmt(gain) + '개 구매하기';
                } else {
                    maxBtn.style.display = 'none';
                    maxBtn.disabled = true;
                }
            }
        }

        function applyEunchongFieldsFromResponse(j) {
            if (typeof j.은총활성 !== 'undefined') state.은총활성 = j.은총활성;
            if (typeof j.은총개수 !== 'undefined') state.은총개수 = j.은총개수;
            if (typeof j.은총_end !== 'undefined') state.은총_end = j.은총_end;
            if (typeof j.은총_left_sec !== 'undefined') state.은총_left_sec = j.은총_left_sec;
            if (typeof j.은총_tier !== 'undefined') state.은총_tier = j.은총_tier;
            if (typeof j.은총_label !== 'undefined') state.은총_label = j.은총_label;
            if (typeof j.은총_zeros !== 'undefined') state.은총_zeros = j.은총_zeros;
            if (typeof j.은총_cost_discount !== 'undefined') state.은총_cost_discount = j.은총_cost_discount;
            if (typeof j.은총_mega_cost !== 'undefined') state.은총_mega_cost = j.은총_mega_cost;
            if (typeof j.은총_show_mega !== 'undefined') state.은총_show_mega = j.은총_show_mega;
        }

        function applyStatusFromResponse(j) {
            if (typeof j.point !== 'undefined') {
                state.point = j.point;
                state.point_fmt = j.point_fmt || state.point_fmt;
            }
            if (typeof j.item !== 'undefined') state.item = j.item;
            if (typeof j.weaponType !== 'undefined') state.weaponType = parseInt(j.weaponType || 0, 10);
            if (typeof j.enhance !== 'undefined') state.enhance = j.enhance;
            if (typeof j.style !== 'undefined') state.style = j.style;
            if (typeof j.enhance_suho !== 'undefined') state.enhance_suho = j.enhance_suho;
            if (typeof j.suho_item !== 'undefined') state.suho_item = j.suho_item;
            if (typeof j.suho_price !== 'undefined') state.suho_price = j.suho_price;
            if (typeof j.suho_price_fmt !== 'undefined') state.suho_price_fmt = j.suho_price_fmt;
            if (typeof j.suho_max_buy !== 'undefined') state.suho_max_buy = j.suho_max_buy;
            if (typeof j.suho_max_gain !== 'undefined') state.suho_max_gain = j.suho_max_gain;
            if (typeof j.suho_max_cost !== 'undefined') state.suho_max_cost = j.suho_max_cost;
            if (typeof j.suho_max_exp !== 'undefined') state.suho_max_exp = j.suho_max_exp;
            if (typeof j.suho_max_token !== 'undefined') state.suho_max_token = j.suho_max_token;
            if (typeof j.cost_next !== 'undefined') state.cost_next = j.cost_next;
            if (typeof j.cost_next_fmt !== 'undefined') state.cost_next_fmt = j.cost_next_fmt;
            if (typeof j.success_rate !== 'undefined') state.success_rate = j.success_rate;
            if (typeof j.durability !== 'undefined') state.durability = j.durability;
            applyEunchongFieldsFromResponse(j);
            if (typeof j.trial_cast_available !== 'undefined') state.trial_cast_available = j.trial_cast_available;
            if (typeof j.trial_protect_available !== 'undefined') state.trial_protect_available = j.trial_protect_available;
            if (typeof j.newpoint !== 'undefined') state.newpoint = j.newpoint;
            if (typeof j.newpoint_fmt !== 'undefined') state.newpoint_fmt = j.newpoint_fmt;
        }

        var BATCH_TIMES = [100, 500, 1000, 3000, 5000];
        function batchNewpointCost(times) {
            times = parseInt(times, 10) || 0;
            if (times === 1000) return 10;
            if (times === 3000) return 30;
            if (times === 5000) return 50;
            return 0;
        }
        function batchButtons() {
            return document.querySelectorAll('.btn-enhance-batch');
        }
        function batchTimesAllowed(times) {
            times = parseInt(times, 10) || 0;
            if (times === 5000) {
                return state.enhance >= (state.batch_5000_min_enhance || 50);
            }
            return true;
        }

        function syncBatchButton() {
            var show = !!(state.item && state.enhance < state.max_level);
            batchButtons().forEach(function(btn) {
                var times = parseInt(btn.getAttribute('data-times'), 10) || 0;
                var showThis = show && batchTimesAllowed(times);
                btn.style.display = showThis ? '' : 'none';
                btn.disabled = !showThis;
            });
        }

        function setEnhanceButtonsBusy(busy) {
            var btnE = $('btnEnhance');
            if (busy) {
                if (btnE) btnE.disabled = true;
                batchButtons().forEach(function(btn) { btn.disabled = true; });
                return;
            }
            var maxed = state.enhance >= state.max_level;
            if (btnE) btnE.disabled = maxed;
            syncBatchButton();
        }

        function openSuhoBuyPanel() {
            setActiveTab('suho');
            var tabs = document.querySelector('.item-tabs');
            if (tabs && tabs.scrollIntoView) {
                tabs.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            renderSuhoPanel();
        }

        function openBreakSuhoModal(j) {
            var modal = $('breakSuhoModal');
            if (!modal) return;
            var weaponEl = $('breakSuhoWeapon');
            var brokenItem = (j && j.broken_item) ? String(j.broken_item) : '';
            var brokenEnh = (j && typeof j.broken_enhance !== 'undefined') ? parseInt(j.broken_enhance, 10) : 0;
            if (weaponEl) {
                if (brokenItem) {
                    weaponEl.textContent = brokenItem + (brokenEnh > 0 ? (' +' + brokenEnh) : '') + ' 파손';
                    weaponEl.style.display = '';
                } else {
                    weaponEl.textContent = '';
                    weaponEl.style.display = 'none';
                }
            }
            modal.hidden = false;
            modal.classList.add('show');
        }

        function closeBreakSuhoModal() {
            var modal = $('breakSuhoModal');
            if (!modal) return;
            modal.classList.remove('show');
            modal.hidden = true;
        }

        function applyEnhanceResult(j) {
            enhanceGen++;
            statusReqId++; // 진행 중·직후 status가 강화도를 되돌리지 못하게
            applyStatusFromResponse(j);
            var kind = 'info';
            if (j.result === 'success') kind = 'success';
            else if (j.result === 'fail_protect') kind = 'info';
            else if (j.result === 'fail_break') kind = 'fail';
            // 연속 강화는 채굴처럼 한 줄 요약이 읽기 좋고 렌더도 빠름
            var msg = j.data || '';
            if (j.type === 'enhance_batch' && msg) {
                msg = String(msg).replace(/\n+/g, ' · ');
            }
            showResult(msg, kind);
            if (j.result === 'fail_break' || j.need_suho) {
                openBreakSuhoModal(j);
            }
            // 연속 강화: 이력 재조회를 미뤄 UI 먼저 갱신
            if (j.type === 'enhance_batch') {
                var keepHist = historyOpen;
                historyOpen = false;
                renderAll();
                historyOpen = keepHist;
                if (keepHist) {
                    setTimeout(loadHistory, 120);
                }
            } else {
                renderAll();
            }
        }

        function renderAll() {
            var npEl = $('stNewpoint');
            if (npEl) npEl.textContent = state.newpoint_fmt || (Number(state.newpoint || 0).toLocaleString('ko-KR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '냥');
            $('stPoint').textContent = fmtNyang(state.point_fmt, state.point);
            $('stSuho').textContent = fmt(state.enhance_suho) + '회';
            $('stSuhoItem').textContent = fmt(state.suho_item) + '개';
            renderSuhoPanel();
            renderWeapon();
            if (state.item) {
                var next = Math.min(state.max_level, state.enhance + 1);
                $('levNext').textContent = '+' + state.enhance + ' → +' + next;
                $('costNext').textContent = fmtNyang(state.cost_next_fmt, state.cost_next);
                $('rateNext').textContent = state.success_rate || '—';
                var dEl = $('durability');
                if (state.durability > 0) {
                    if (!dEl) {
                        var costBlock = $('enhanceCard');
                        var row = document.createElement('div');
                        row.className = 'cost-line';
                        row.innerHTML = '<span>예상 최대 내구도</span><b id="durability">' + state.durability + '</b>';
                        costBlock.insertBefore(row, costBlock.querySelector('.enhance-btns'));
                    } else {
                        dEl.textContent = state.durability;
                        dEl.parentElement.style.display = '';
                    }
                } else if (dEl) {
                    dEl.parentElement.style.display = 'none';
                }
                var btnE = $('btnEnhance');
                if (btnE) btnE.disabled = (state.enhance >= state.max_level);
                if (state.enhance >= state.max_level) {
                    if (btnE) btnE.textContent = '⚔️ 최대';
                } else {
                    if (btnE) btnE.textContent = '⚔️ 1회';
                }
            }
            syncBatchButton();
            renderEunchong();
            renderTrialCast();
            if (historyOpen) loadHistory();
        }

        function doTrialCast(castKind) {
            castKind = castKind || 'cast';
            var btnTrialCast = $('btnTrialCast');
            var btnTrialProtect = $('btnTrialProtect');
            if (btnTrialCast) btnTrialCast.disabled = true;
            if (btnTrialProtect) btnTrialProtect.disabled = true;
            showResult(castKind === 'protect' ? '맛보기 보호 중...' : '맛보기 시전 중...', 'info');
            ajax({ action: 'trial_cast', cast_kind: castKind }, function(j) {
                if (!j.ok) {
                    showResult(j.data || '맛보기 실패', 'fail');
                    renderTrialCast();
                    return;
                }
                if (typeof j.trial_cast_available !== 'undefined') state.trial_cast_available = j.trial_cast_available;
                if (typeof j.trial_protect_available !== 'undefined') state.trial_protect_available = j.trial_protect_available;
                if (typeof j.point !== 'undefined') {
                    state.point = j.point;
                    state.point_fmt = j.point_fmt || state.point_fmt;
                }
                var kind = j.cast_kind === 'attack' && !j.blocked ? 'success' : 'info';
                showResult(j.data, kind);
                renderAll();
            });
        }

        function handleNeedSwap(j, retryFn) {
            showResult(j.data || '게임냥이 부족해요.', 'fail');
            if (!j.need_swap) {
                return;
            }
            var conf = j.swap_confirm || '본냥을 게임냥으로 스왑하시겠습니까?\n(본냥 1,000냥 남김 · 5% 삭제)';
            if (!window.confirm(conf)) {
                return;
            }
            doSwapNpToPt(retryFn);
        }

        function doSwapNpToPt(retryFn) {
            var btn = $('btnSwapNp');
            if (btn) btn.disabled = true;
            showResult('본냥 → 게임냥 스왑 중...', 'info');
            ajax({ action: 'swap_np_to_pt' }, function(sj) {
                if (btn) btn.disabled = false;
                if (!sj.ok) {
                    showResult(sj.data || '스왑 실패', 'fail');
                    return;
                }
                if (typeof sj.point !== 'undefined' && sj.point !== null) {
                    state.point = sj.point;
                    state.point_fmt = sj.point_fmt || state.point_fmt;
                }
                if (typeof sj.newpoint !== 'undefined' && sj.newpoint !== null) {
                    state.newpoint = sj.newpoint;
                    state.newpoint_fmt = sj.newpoint_fmt || state.newpoint_fmt;
                }
                renderAll();
                showResult(sj.data || '스왑 완료', 'success');
                if (typeof retryFn === 'function') {
                    setTimeout(retryFn, 350);
                }
            });
        }

        function doBuy(weapon) {
            var btns = document.querySelectorAll('#buyCard .btn');
            btns.forEach(function(b) { b.disabled = true; });
            showResult('구매 중...', 'info');
            ajax({ action: 'buy', weapon: weapon }, function(j) {
                btns.forEach(function(b) { b.disabled = false; });
                if (!j.ok) {
                    handleNeedSwap(j, function() { doBuy(weapon); });
                    return;
                }
                showResult(j.data, 'success');
                refreshStatus();
            });
        }

        function doEnhance() {
            if (!canRunEnhanceNow()) return;
            enhancing = true;
            statusReqId++; // 진행 중 status가 강화도를 되돌리지 못하게
            setEnhanceButtonsBusy(true);
            showResult('강화 중...', 'info');
            ajax({ action: 'enhance', booster: isMegaBoosterChecked() ? 1 : 0 }, function(j) {
                if (!j.ok) {
                    enhancing = false;
                    setEnhanceButtonsBusy(false);
                    handleNeedSwap(j, function() { doEnhance(); });
                    return;
                }
                statusReqId++; // 응답 도착 전 시작된 status도 폐기
                applyEnhanceResult(j);
                enhancing = false;
                setEnhanceButtonsBusy(false);
            });
        }

        function doEnhanceBatchTimes(n) {
            if (!canRunEnhanceBatchNow()) return;
            n = parseInt(n, 10) || 0;
            if (BATCH_TIMES.indexOf(n) === -1) return;
            if (!batchTimesAllowed(n)) {
                var need = state.batch_5000_min_enhance || 50;
                showResult('5000회 연속 강화는 무기 +' + need + ' 이상부터 가능해요. (현재 +' + state.enhance + ')', 'info');
                return;
            }
            var np = batchNewpointCost(n);
            enhancing = true;
            statusReqId++;
            setEnhanceButtonsBusy(true);
            var msg = n + '회 연속 강화 중...';
            if (np > 0) msg += ' (본방 ' + np + '냥)';
            showResult(msg, 'info');
            ajax({ action: 'enhance_batch', times: n, booster: isMegaBoosterChecked() ? 1 : 0 }, function(j) {
                if (!j.ok) {
                    enhancing = false;
                    setEnhanceButtonsBusy(false);
                    handleNeedSwap(j, function() { doEnhanceBatchTimes(n); });
                    return;
                }
                statusReqId++;
                applyEnhanceResult(j);
                enhancing = false;
                setEnhanceButtonsBusy(false);
            });
        }

        function doBuyEnhanceSuho(qty, opts) {
            opts = opts || {};
            var autoSwapHalf = !!opts.autoSwapHalf;
            var fromBreakModal = !!opts.fromBreakModal;
            var buyMax = !!opts.buyMax;
            var btns = document.querySelectorAll('[data-buy-enhance-suho]');
            var breakBtn = $('btnBreakBuySuho100');
            btns.forEach(function(b) { b.disabled = true; });
            if (breakBtn) breakBtn.disabled = true;
            var gain = parseInt(opts.gain || 0, 10) || 0;
            showResult('강화 수호 ' + (buyMax && gain > 0 ? (fmt(gain) + '개') : (qty + '회')) + ' 구매 중...', 'info');
            var payload = { action: 'use_suho', count: qty, buy: 1 };
            if (autoSwapHalf) payload.auto_swap_half = 1;
            if (buyMax) {
                payload.buy_max = 1;
                payload.gain = gain;
                payload.cost = opts.cost || state.suho_max_cost || '0';
                payload.exp = opts.exp || state.suho_max_exp || 0;
                payload.token = opts.token || state.suho_max_token || '';
            }
            ajax(payload, function(j) {
                btns.forEach(function(b) { b.disabled = false; });
                if (breakBtn) breakBtn.disabled = false;
                if (!j.ok) {
                    showResult(j.data || '강화 수호 구매 실패', 'fail');
                    return;
                }
                if (typeof j.enhance_suho !== 'undefined') state.enhance_suho = j.enhance_suho;
                if (typeof j.suho_item !== 'undefined') state.suho_item = j.suho_item;
                if (typeof j.suho_price !== 'undefined') state.suho_price = j.suho_price;
                if (typeof j.suho_price_fmt !== 'undefined') state.suho_price_fmt = j.suho_price_fmt;
                if (typeof j.suho_max_buy !== 'undefined') state.suho_max_buy = j.suho_max_buy;
                if (typeof j.suho_max_gain !== 'undefined') state.suho_max_gain = j.suho_max_gain;
                if (typeof j.suho_max_cost !== 'undefined') state.suho_max_cost = j.suho_max_cost;
                if (typeof j.suho_max_exp !== 'undefined') state.suho_max_exp = j.suho_max_exp;
                if (typeof j.suho_max_token !== 'undefined') state.suho_max_token = j.suho_max_token;
                if (typeof j.point !== 'undefined') {
                    state.point = j.point;
                    state.point_fmt = j.point_fmt || state.point_fmt;
                }
                if (typeof j.newpoint !== 'undefined' && j.newpoint !== null) {
                    state.newpoint = j.newpoint;
                    state.newpoint_fmt = j.newpoint_fmt || state.newpoint_fmt;
                }
                showResult(j.data, 'success');
                if (fromBreakModal) closeBreakSuhoModal();
                renderAll();
            });
        }

        function doUseSuho() {
            var n = parseInt($('suhoCount').value, 10);
            if (!n || n < 1) { showResult('1 이상의 숫자를 입력하세요.', 'fail'); return; }
            var b = $('btnUseSuho');
            b.disabled = true;
            showResult('수호 사용 중...', 'info');
            ajax({ action: 'use_suho', count: n }, function(j) {
                b.disabled = false;
                if (!j.ok) { showResult(j.data || '수호 사용 실패', 'fail'); return; }
                if (typeof j.enhance_suho !== 'undefined') state.enhance_suho = j.enhance_suho;
                if (typeof j.suho_item !== 'undefined') state.suho_item = j.suho_item;
                if (typeof j.suho_price !== 'undefined') state.suho_price = j.suho_price;
                if (typeof j.suho_price_fmt !== 'undefined') state.suho_price_fmt = j.suho_price_fmt;
                if (typeof j.point !== 'undefined') {
                    state.point = j.point;
                    state.point_fmt = j.point_fmt || state.point_fmt;
                }
                showResult(j.data, 'success');
                renderAll();
            });
        }

        function doUseEunchong(tier) {
            tier = parseInt(tier || 1, 10);
            if (tier < 1) tier = 1;
            if (tier > 2) tier = 2;
            var b = (tier >= 2) ? $('btnUseMegaEunchong') : $('btnUseEunchong');
            if (!b || b.disabled) return;
            var other = (tier >= 2) ? $('btnUseEunchong') : $('btnUseMegaEunchong');
            b.disabled = true;
            if (other) other.disabled = true;
            var genAtStart = enhanceGen;
            var enhanceAtStart = parseInt(state.enhance || 0, 10);
            showResult((tier >= 2 ? '메가은총' : '은총') + ' 사용 중...', 'info');
            ajax({ action: 'use_eunchong', tier: tier }, function(j) {
                if (!j.ok) {
                    showResult(j.data || '은총 사용 실패', 'fail');
                    renderEunchong();
                    return;
                }
                // 은총은 enhance를 바꾸지 않음. 직후 강화 성공을 stale 응답이 되돌리지 않게
                // enhance/item 은 절대 덮지 않고 버프·개수만 반영.
                statusReqId++;
                showResult(j.data, 'success');
                applyEunchongFieldsFromResponse(j);
                if (typeof j.은총개수 !== 'undefined') state.은총개수 = j.은총개수;
                // 강화가 끼어들지 않았을 때만 비용·확률·잔액 반영
                if (!enhancing && genAtStart === enhanceGen && parseInt(state.enhance || 0, 10) === enhanceAtStart) {
                    if (typeof j.point !== 'undefined') {
                        state.point = j.point;
                        state.point_fmt = j.point_fmt || state.point_fmt;
                    }
                    if (typeof j.success_rate !== 'undefined') state.success_rate = j.success_rate;
                    if (typeof j.cost_next !== 'undefined') state.cost_next = j.cost_next;
                    if (typeof j.cost_next_fmt !== 'undefined') state.cost_next_fmt = j.cost_next_fmt;
                } else if (!enhancing) {
                    // 강화가 먼저 반영됐으면 최신 확률만 맞춤 (enhance는 refresh가 낮춰도 무시)
                    refreshStatus();
                }
                renderAll();
            });
        }

        // 이벤트 바인딩
        document.querySelectorAll('.btn-buy, .btn-buy-random').forEach(function(b) {
            b.addEventListener('click', function() {
                doBuy(b.getAttribute('data-weapon'));
            });
        });
        var be = $('btnEnhance');
        if (be) be.addEventListener('click', doEnhance);
        batchButtons().forEach(function(btn) {
            btn.addEventListener('click', function() {
                doEnhanceBatchTimes(btn.getAttribute('data-times'));
            });
        });
        var bSwap = $('btnSwapNp');
        if (bSwap) {
            bSwap.addEventListener('click', function() {
                if (!window.confirm('본냥 → 게임냥 스왑할까요?\n\n※ 본냥 1,000냥 남김\n※ 5% 차감(삭제) · 나머지 전액 환율 교환')) {
                    return;
                }
                doSwapNpToPt(null);
            });
        }
        var btt = $('btnTrialCast');
        if (btt) btt.addEventListener('click', function() { doTrialCast('cast'); });
        var btp = $('btnTrialProtect');
        if (btp) btp.addEventListener('click', function() { doTrialCast('protect'); });
        $('btnUseSuho').addEventListener('click', doUseSuho);
        document.querySelectorAll('[data-buy-enhance-suho]').forEach(function(b) {
            b.addEventListener('click', function() {
                var qty = parseInt(b.getAttribute('data-buy-enhance-suho'), 10);
                if (!qty || qty < 1) {
                    showResult('본방 10냥을 남기면 구매할 수 있는 수호가 없어요.', 'fail');
                    return;
                }
                if (b.id === 'btnBuyEnhanceSuhoMax') {
                    var gain = parseInt(b.getAttribute('data-suho-gain'), 10) || 0;
                    if (gain < 1) {
                        showResult('미리뽑기 수치가 없어요. 새로고침 해주세요.', 'fail');
                        return;
                    }
                    doBuyEnhanceSuho(qty, {
                        buyMax: true,
                        gain: gain,
                        cost: b.getAttribute('data-suho-cost') || state.suho_max_cost,
                        exp: parseInt(b.getAttribute('data-suho-exp'), 10) || state.suho_max_exp,
                        token: b.getAttribute('data-suho-token') || state.suho_max_token
                    });
                    return;
                }
                doBuyEnhanceSuho(qty);
            });
        });
        var btnBreakBuy = $('btnBreakBuySuho100');
        if (btnBreakBuy) {
            btnBreakBuy.addEventListener('click', function() {
                doBuyEnhanceSuho(100, { autoSwapHalf: true, fromBreakModal: true });
            });
        }
        var btnBreakClose = $('btnBreakSuhoClose');
        if (btnBreakClose) btnBreakClose.addEventListener('click', closeBreakSuhoModal);
        var breakModal = $('breakSuhoModal');
        if (breakModal) {
            breakModal.addEventListener('click', function(e) {
                if (e.target === breakModal) closeBreakSuhoModal();
            });
        }
        var bEun = $('btnUseEunchong');
        if (bEun) bEun.addEventListener('click', function() { doUseEunchong(1); });
        var bMega = $('btnUseMegaEunchong');
        if (bMega) bMega.addEventListener('click', function() { doUseEunchong(2); });
        $('btnRefresh').addEventListener('click', refreshStatus);
        var ht = $('historyToggle');
        if (ht) ht.addEventListener('click', toggleHistory);
        var ct = $('castToggle');
        if (ct) ct.addEventListener('click', toggleCast);
        document.querySelectorAll('.tab-btn').forEach(function(b) {
            b.addEventListener('click', function() {
                toggleTab(b.getAttribute('data-tab'));
            });
        });

        function isEnhanceShortcutBlocked() {
            var el = document.activeElement;
            if (!el) return false;
            var tag = el.tagName ? el.tagName.toUpperCase() : '';
            if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
            if (tag === 'INPUT') {
                var ty = (el.type || '').toLowerCase();
                if (ty === 'text' || ty === 'password' || ty === 'search' || ty === 'number' || ty === 'email' || ty === 'tel') {
                    return true;
                }
            }
            return !!el.isContentEditable;
        }

        function isEnhanceCardVisible() {
            var card = $('enhanceCard');
            return !!(card && card.offsetParent !== null);
        }

        function canRunEnhanceNow() {
            if (enhancing) return false;
            if (!isEnhanceCardVisible()) return false;
            if (state.enhance >= state.max_level) return false;
            var btn = $('btnEnhance');
            return !!(btn && !btn.disabled);
        }

        function canRunEnhanceBatchNow() {
            if (enhancing) return false;
            if (!isEnhanceCardVisible()) return false;
            if (!state.item || state.enhance >= state.max_level) return false;
            var btn = document.querySelector('.btn-enhance-batch');
            return !!(btn && btn.style.display !== 'none' && !btn.disabled);
        }

        document.addEventListener('keydown', function(e) {
            if (e.altKey || e.ctrlKey || e.metaKey) return;
            if (isEnhanceShortcutBlocked()) return;
            var key = e.key;
            var isOne = key === '1' || e.code === 'Digit1' || e.code === 'Numpad1';
            var isTwo = key === '2' || e.code === 'Digit2' || e.code === 'Numpad2';
            var isThree = key === '3' || e.code === 'Digit3' || e.code === 'Numpad3';
            var isFour = key === '4' || e.code === 'Digit4' || e.code === 'Numpad4';
            var isFive = key === '5' || e.code === 'Digit5' || e.code === 'Numpad5';
            var isSix = key === '6' || e.code === 'Digit6' || e.code === 'Numpad6';
            if (!isOne && !isTwo && !isThree && !isFour && !isFive && !isSix) return;
            if (isOne && canRunEnhanceNow()) {
                e.preventDefault();
                doEnhance();
            } else if (isTwo && canRunEnhanceBatchNow()) {
                e.preventDefault();
                doEnhanceBatchTimes(100);
            } else if (isThree && canRunEnhanceBatchNow()) {
                e.preventDefault();
                doEnhanceBatchTimes(500);
            } else if (isFour && canRunEnhanceBatchNow()) {
                e.preventDefault();
                doEnhanceBatchTimes(1000);
            } else if (isFive && canRunEnhanceBatchNow()) {
                e.preventDefault();
                doEnhanceBatchTimes(3000);
            } else if (isSix && canRunEnhanceBatchNow()) {
                e.preventDefault();
                doEnhanceBatchTimes(5000);
            }
        });

        // 은총 버프 카운트다운 (1초마다)
        setInterval(function() {
            if (state.은총_left_sec > 0) {
                state.은총_left_sec -= 1;
                if (state.은총_left_sec <= 0) {
                    state.은총_left_sec = 0;
                    state.은총활성 = 0;
                    state.은총_zeros = 0;
                    state.은총_tier = 1;
                    state.은총_label = '은총';
                    state.은총_cost_discount = 0;
                    renderEunchong();
                    // 버프 종료 시 상태 새로고침 (성공률 등)
                    refreshStatus();
                } else {
                    renderEunchong();
                }
            }
        }, 1000);
        // 초기 상태 반영
        renderAll();
    })();
    </script>
    <?php } ?>
<?php
if (!$enchant_need_code && $enchant_code !== '') {
  require_once __DIR__ . '/../api/game/wallet_nav_fab.inc.php';
  wallet_nav_fab_render(['code' => $enchant_code]);
}
?>
</body>
</html>
