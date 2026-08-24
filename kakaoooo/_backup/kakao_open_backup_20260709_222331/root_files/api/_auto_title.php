<?php
include_once "/home/kakao/public_html/lib/_function.php";
include_once "/home/kakao/public_html/api/function.php";
$현재시간 = date("Y-m-d H:i:s");

/** 🌠전설 보유자는 (아래 전설 블록 제외) 다른 자동 칭호에서 덮어쓰지 않는다 */
function tb_member_title_is_protected_legend(?string $title): bool {
    return trim((string)$title) === '🌠전설';
}

/** 🎴타짜 보유 여부 (🎴 이모지 유니코드 형태 차이로 PHP 문자열 엄격 비교만으로는 매치 실패할 수 있음) */
function tb_member_title_is_tyzza(?string $title): bool {
    $t = trim((string)$title);
    if ($t === '') {
        return false;
    }
    if ($t === '🎴타짜') {
        return true;
    }
    return (bool)preg_match('/타짜/u', $t);
}

/** 🆘신불자 상태 타이틀 (이모지 표현 차이 보조) */
function tb_member_title_is_credit_debtor(?string $title): bool {
    $t = trim((string)$title);
    if ($t === '') {
        return false;
    }
    if ($t === '🆘신불자') {
        return true;
    }
    return (bool)preg_match('/신불자/u', $t);
}

/** 신불·마이너스 냥이면 아래 자동 칭호(왕실·타짜 등) 부여·교체에서 제외 */
function tb_member_skip_auto_titles_for_credit(?string $title, int $point): bool {
    if (tb_member_title_is_credit_debtor($title)) {
        return true;
    }
    return $point < 0;
}

$현재시 = (int)date('H');
$현재분 = (int)date('i');
// tb_point_log·tb_battle 풀스캔 칭호 집계는 5분마다 (신불·신용회복은 매 분)
$타이틀스캔_주기분 = 5;
$타이틀스캔실행 = ($현재분 % $타이틀스캔_주기분 === 0);

if ($타이틀스캔실행) {

// 1️⃣ 왕자/여왕: 타수(tasu) 순 1·2·3·4·5등만 고려한다.
//    해당 순위에 깡패·타짜 등 왕실이 아닌 칭호가 있으면 건너뛴다 · 빈 칭호 또는 성별에 맞는 왕실만 부여 가능.
//    더 높은 타수의 적격 후보가 있으면 기존 단일 왕실 보유자는 회수 후 새 멤버에게 부여.

// 현재 왕자/여왕 보유자 조회
$현재보유자 = db_select("
    SELECT idx, name, level, tasu, title
    FROM tb_member
    WHERE title IN ('🫅🏿왕자', '👸🏻여왕')
    LIMIT 1
");

$sql = "SELECT * FROM tb_member ORDER BY tasu DESC, idx ASC LIMIT 5";
$result = db_query($sql);
$부여할멤버 = null;
$순위 = 0;

while ($row = db_fetch($result)) {
    $순위++;
    $현재타이틀 = trim((string)($row['title'] ?? ''));
    $row_point = (int)($row['point'] ?? 0);

    if (tb_member_skip_auto_titles_for_credit($현재타이틀, $row_point)) {
        continue;
    }

    // 깡패·보룸왕 등 왕실 제외 다른 칭호 → 이 순위는 스킵 (1등이어도 그대로 둠)
    if ($현재타이틀 !== '') {
        if ($현재타이틀 !== '🫅🏿왕자' && $현재타이틀 !== '👸🏻여왕') {
            continue;
        }
        // 이미 왕실이면 성별과 일치하는 칭호일 때만 이 순위 채택(유지/정상화)
        $기대타이틀후보 = ((int)$row['gender'] === 1) ? '🫅🏿왕자' : '👸🏻여왕';
        if ($현재타이틀 !== $기대타이틀후보) {
            continue;
        }
    }

    $부여할멤버 = $row;
    $부여할멤버['순위'] = $순위;
    break;
}

if ($부여할멤버) {
    $새멤버_idx = (int)$부여할멤버['idx'];

    // 현재 보유자가 있고, 대상이 달라졌다면 기존 타이틀 제거
    if (!empty($현재보유자) && (int)$현재보유자['idx'] !== $새멤버_idx) {
        db_query("UPDATE tb_member SET title = '' WHERE idx = " . (int)$현재보유자['idx']);
    }

    // 대상에게 왕자/여왕 타이틀 부여 (성별에 따라 결정)
    $기대타이틀 = ((int)$부여할멤버['gender'] === 1) ? '🫅🏿왕자' : '👸🏻여왕';
    $member_name = $부여할멤버['name'];
    $total_tasu = (int)$부여할멤버['tasu'];
    $title = $기대타이틀;
    $타수설명 = ((int)$부여할멤버['순위'] === 1)
        ? "최고타수 총 {$total_tasu}타 달성으로"
        : "누적 타수 {$total_tasu}타 (타수 {$부여할멤버['순위']}등에서 선정)";

    // 이미 같은 사람이 같은 타이틀을 가지고 있으면 아무 것도 하지 않음
    if (empty($현재보유자) || (int)$현재보유자['idx'] !== $새멤버_idx || $현재보유자['title'] !== $title) {
        db_query("UPDATE tb_member SET title = '{$title}' WHERE idx = {$새멤버_idx}");

        $타이틀부여 = addslashes($타수설명 . " [ {$member_name} ]에게 타이틀 '{$title}' 부여!");
        echo $타이틀부여;

        db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$타이틀부여}', leverage = 0, item = '{$member_name}', regdate = NOW()");
    }
}


// 🎴타짜: 홀짝 순이익 상위부터 1·2·3·4등까지 탐색
// 👸🏻여왕/🫅🏿왕자 보유자는 타짜 대상 제외 · 🆘신불자/보유냥 마이너스 제외 · 그 다음 순위에게 부여 (순이익 0 초과만)
$타짜타이틀 = '🎴타짜';
if (!function_exists('홀짝_타짜_랭킹_행목록')) {
  require_once __DIR__ . '/game/odd_even_profit.inc.php';
}
홀짝_순이익_백필_필요시();
$타짜_sql = "
    SELECT
        m.idx,
        m.name,
        m.title,
        IFNULL(m.point, 0) AS point,
        IFNULL(m.credit, 0) AS credit,
        p.total_nyang
    FROM tb_odd_even_profit p
    INNER JOIN tb_member m ON m.name = p.nick
    ORDER BY p.total_nyang DESC, m.idx ASC
    LIMIT 4
";
$타짜_rs = db_query($타짜_sql);

$타짜_target = null;
$타짜_등수 = 0;
$등수 = 0;
if ($타짜_rs) {
    while ($row = db_fetch($타짜_rs)) {
        if (!$row) {
            break;
        }
        $등수++;
        if ((int)$row['total_nyang'] <= 0) {
            continue;
        }
        $표시타이틀 = trim((string)($row['title'] ?? ''));
        if ($표시타이틀 === '🫅🏿왕자' || $표시타이틀 === '👸🏻여왕') {
            continue;
        }
        if (tb_member_skip_auto_titles_for_credit($표시타이틀, (int)($row['point'] ?? 0))) {
            continue;
        }
        if (tb_member_title_is_protected_legend($표시타이틀)) {
            continue;
        }
        $타짜_target = $row;
        $타짜_등수 = $등수;
        break;
    }
}

$현재타짜 = db_select("
    SELECT idx
    FROM tb_member
    WHERE title = '" . addslashes($타짜타이틀) . "'
    LIMIT 1
");
$현재타짜_raw_idx = (int)($현재타짜['idx'] ?? 0);

if ($타짜_target && $타짜_등수 > 0) {
    $타짜_member_idx  = (int)$타짜_target['idx'];
    $타짜_member_name = $타짜_target['name'];
    $타짜_total_nyang = (int)$타짜_target['total_nyang'];
    $타짜_target_title = trim((string)($타짜_target['title'] ?? ''));

    $타짜칭호일치 = tb_member_title_is_tyzza($타짜_target_title);
    $보유자일치 = ($현재타짜_raw_idx === $타짜_member_idx)
        || ($현재타짜_raw_idx <= 0 && $타짜칭호일치);

    if ($보유자일치 && $타짜칭호일치) {
        // 이미 선정 멤버에게 타짜 칭호가 정상 반영됨 — 재발송·불필요 UPDATE 없음
    } else {
        db_query("
            UPDATE tb_member
            SET title = ''
            WHERE title = '" . addslashes($타짜타이틀) . "'
        ");
        db_query("
            UPDATE tb_member
            SET title = '" . addslashes($타짜타이틀) . "'
            WHERE idx = {$타짜_member_idx}
        ");

        // tb_lotto_info 는 실제 보유자(idx)가 바뀔 때만 1회 (타이틀 문자열만 어긋난 경우는 알림 생략)
        $타짜알림 = ($현재타짜_raw_idx !== $타짜_member_idx);
        if ($현재타짜_raw_idx <= 0 && tb_member_title_is_tyzza($타짜_target_title)) {
            $타짜알림 = false;
        }
        if ($타짜알림) {
            $타짜_등수문구 = (int)$타짜_등수 === 1 ? '1위' : ((int)$타짜_등수 . '위');
            $타짜_금액문구 = function_exists('냥축약표시')
                ? 냥축약표시($타짜_total_nyang)
                : (number_format($타짜_total_nyang) . '냥');
            $타짜_msg = addslashes("홀짝 누적 순이익 {$타짜_금액문구} {$타짜_등수문구} [ {$타짜_member_name} ] {$타짜타이틀} 타이틀 부여!");
            $타짜_name_esc = addslashes($타짜_member_name);
            db_query("
                INSERT INTO tb_lotto_info
                SET status = 0,
                    msg = '{$타짜_msg}',
                    leverage = 0,
                    item = '{$타짜_name_esc}',
                    regdate = NOW()
            ");
        }
    }
}



// 👊🏻깡패: 맞다이 승리 횟수 상위 1~3등까지 탐색 (1분마다 크론)
// 1·2등이 다른 칭호 보유 시 다음 순위에게 부여 · 빈 칭호·깡패만 가능
$title = '👊🏻깡패';

$깡패_sql = "
    SELECT
        m.idx,
        m.name,
        m.title,
        COALESCE(m.point, 0) AS point,
        w.total_win
    FROM (
        SELECT win AS name, COUNT(*) AS total_win
        FROM tb_battle
        WHERE TRIM(IFNULL(win, '')) <> ''
        GROUP BY win
        ORDER BY total_win DESC
        LIMIT 3
    ) w
    INNER JOIN tb_member m ON m.name = w.name
    ORDER BY w.total_win DESC, m.idx ASC
";
$깡패_rs = db_query($깡패_sql);

$깡패_target = null;
$깡패_등수 = 0;
$등수 = 0;
if ($깡패_rs) {
    while ($row = db_fetch($깡패_rs)) {
        if (!$row) {
            break;
        }
        $등수++;
        $표시타이틀 = trim((string)($row['title'] ?? ''));
        if (tb_member_skip_auto_titles_for_credit($표시타이틀, (int)($row['point'] ?? 0))) {
            continue;
        }
        if ($표시타이틀 !== '' && $표시타이틀 !== $title) {
            continue;
        }
        $깡패_target = $row;
        $깡패_등수 = $등수;
        break;
    }
}

// 현재 👊🏻깡패 보유자
$현재깡패 = db_select("
    SELECT idx
    FROM tb_member
    WHERE title = '{$title}'
    LIMIT 1
");
$현재깡패_idx = (int)($현재깡패['idx'] ?? 0);

if ($깡패_target && $깡패_등수 > 0) {
    $member_idx  = (int)$깡패_target['idx'];
    $member_name = $깡패_target['name'];
    $total_win   = (int)$깡패_target['total_win'];

    if ($현재깡패_idx !== $member_idx) {
        db_query("
            UPDATE tb_member
            SET title = ''
            WHERE title = '{$title}'
        ");

        db_query("
            UPDATE tb_member
            SET title = '{$title}'
            WHERE idx = {$member_idx}
        ");

        $등수문구 = ((int)$깡패_등수 === 1) ? '최고 승리자' : ((int)$깡패_등수 . '위');
        $msg = addslashes("맞다이 {$total_win}회 {$등수문구} [ {$member_name} ] 👊🏻깡패 타이틀 부여!");
        db_query("
            INSERT INTO tb_lotto_info
            SET status = 0,
                msg = '{$msg}',
                leverage = 0,
                item = '{$member_name}',
                regdate = NOW()
        ");
    }
}


// 🎓천재: 초성(tb_question) 정답 맞춘 문제 수 1위 1명에게만 부여 (1분마다 크론)
// 완료·정답자 기록만 집계: status=1 이고 nick 있음 (.초성초기화만 된 행은 nick 비어 제외)
// 1등은 칭호가 천재가 아니어도(깡패 등) 천재로 덮어씀 · DB상 천재 보유자가 1등과 다르면 회수 후 1등에게 부여
$천재타이틀 = '🎓천재';

$천재_sql = "
    SELECT
        m.idx,
        m.name,
        m.title,
        COALESCE(m.point, 0) AS point,
        COUNT(*) AS solved_cnt
    FROM tb_question q
    INNER JOIN tb_member m ON m.name = q.nick
    WHERE q.status = 1
      AND TRIM(IFNULL(q.nick, '')) <> ''
    GROUP BY m.idx, m.name, m.title, m.point
    ORDER BY solved_cnt DESC, m.idx ASC
    LIMIT 1
";
$천재_rs = db_query($천재_sql);
$천재_target = db_fetch($천재_rs);

$현재천재 = db_select("
    SELECT idx
    FROM tb_member
    WHERE title = '" . addslashes($천재타이틀) . "'
    LIMIT 1
");
$현재천재_idx = (int)($현재천재['idx'] ?? 0);

if ($천재_target && (int)$천재_target['solved_cnt'] > 0) {
    $천재_member_idx  = (int)$천재_target['idx'];
    $천재_member_name = $천재_target['name'];
    $천재_solved      = (int)$천재_target['solved_cnt'];
    $천재_target_title = trim((string)($천재_target['title'] ?? ''));
    $천재_point = (int)($천재_target['point'] ?? 0);

    if (!tb_member_skip_auto_titles_for_credit($천재_target_title, $천재_point)
        && !tb_member_title_is_protected_legend($천재_target_title)
        && ($현재천재_idx !== $천재_member_idx || $천재_target_title !== $천재타이틀)) {
        db_query("
            UPDATE tb_member
            SET title = ''
            WHERE title = '" . addslashes($천재타이틀) . "'
        ");
        db_query("
            UPDATE tb_member
            SET title = '" . addslashes($천재타이틀) . "'
            WHERE idx = {$천재_member_idx}
        ");

        $천재_msg = addslashes("초성 퀴즈 {$천재_solved}문제 최다 정답 [ {$천재_member_name} ] {$천재타이틀} 타이틀 부여!");
        $천재_name_esc = addslashes($천재_member_name);
        db_query("
            INSERT INTO tb_lotto_info
            SET status = 0,
                msg = '{$천재_msg}',
                leverage = 0,
                item = '{$천재_name_esc}',
                regdate = NOW()
        ");
    }
}


// 🦹‍♂️/🦹🏻‍♀️ 금고털이 타이틀: 가장 많이 턴(=금고털이 로그 최다) 친구 1명
// - 전체에서 최다 턴 1명을 먼저 고름
// - 그 최다 1명이 이미 title이 있으면(어떤 타이틀이든) 부여/로그 추가를 하지 않음
$금고털이_남_타이틀 = '🦹‍♂️도둑';
$금고털이_여_타이틀 = '🦹🏻‍♀️도둑';

$금고털이_부여대상_sql = "
    SELECT
        m.idx,
        m.name,
        m.gender,
        m.title,
        COALESCE(m.point, 0) AS point,
        g.total_turn,
        g.total_stolen,
        g.last_regdate
    FROM (
        SELECT
            nick,
            COUNT(*) AS total_turn,
            SUM(COALESCE(point, 0)) AS total_stolen,
            MAX(regdate) AS last_regdate
        FROM tb_point_log
        WHERE status IN ('금고털이', '금고털이-대성공')
        GROUP BY nick
        ORDER BY total_turn DESC, total_stolen DESC, last_regdate DESC
        LIMIT 1
    ) g
    INNER JOIN tb_member m ON m.name = g.nick
    LIMIT 1
";
$금고털이_부여대상_rs = db_query($금고털이_부여대상_sql);
$금고털이_부여대상 = db_fetch($금고털이_부여대상_rs);

if ($금고털이_부여대상 && (int)$금고털이_부여대상['total_turn'] > 0) {
    $member_idx = (int)$금고털이_부여대상['idx'];
    $member_name = $금고털이_부여대상['name'];
    $total_turn = (int)$금고털이_부여대상['total_turn'];
    $total_stolen = (int)($금고털이_부여대상['total_stolen'] ?? 0);

    $기대타이틀 = ((int)$금고털이_부여대상['gender'] === 1) ? $금고털이_남_타이틀 : $금고털이_여_타이틀;

    // 최다 턴 1명만 확인:
    // - 이미 title이 있으면 (어떤 타이틀이든) 추가 부여/로그 없음
    $현재타이틀 = trim((string)($금고털이_부여대상['title'] ?? ''));
    $도둑_point = (int)($금고털이_부여대상['point'] ?? 0);
    if ($현재타이틀 !== '') {
        // 이미 부여되어 있는 상태이므로 종료
    } elseif (tb_member_skip_auto_titles_for_credit($현재타이틀, $도둑_point)) {
        // 신불·마이너스 냥: 도둑 칭호 부여 제외
    } else {
        // 기존 도둑 타이틀이 있으면 제거 후, 새로 부여 (한 명만 유지)
        db_query("
            UPDATE tb_member
            SET title = ''            WHERE title IN ('{$금고털이_남_타이틀}', '{$금고털이_여_타이틀}')
        ");

        db_query("
            UPDATE tb_member
            SET title = '{$기대타이틀}'            WHERE idx = {$member_idx}
        ");

        $msg = addslashes("금고털이 {$total_turn}회 / 총 ".number_format($total_stolen) . "냥 최다 [ {$member_name} ] {$기대타이틀} 타이틀 부여!");
        db_query("
            INSERT INTO tb_lotto_info
            SET status = 0,
                msg = '{$msg}',
                leverage = 0,
                item = '{$member_name}',
                regdate = NOW()
        ");
    }
}

echo "야신부여<Br>";
// 🎲 주사위 역대 최고점(config 캐시) — tb_point_log REGEXP 풀스캔 대체
$야신타이틀 = '🎲야신';

if (function_exists('야바위_최고점수_캐시_없으면_시드')) {
    야바위_최고점수_캐시_없으면_시드();
}
$야바위최고 = function_exists('야바위_최고점수_캐시_읽기')
    ? 야바위_최고점수_캐시_읽기()
    : ['점수' => 0, '닉' => ''];

$야신부여대상 = null;
if ((int)($야바위최고['점수'] ?? 0) > 0 && trim((string)($야바위최고['닉'] ?? '')) !== '') {
    $야신닉_esc = addslashes(trim((string)$야바위최고['닉']));
    $순위row = db_select("
        SELECT idx AS member_idx, name, title, COALESCE(point, 0) AS mpoint
        FROM tb_member
        WHERE name = '{$야신닉_esc}'
        LIMIT 1
    ");
    if ($순위row) {
        $최고점_현재타이틀 = trim((string)($순위row['title'] ?? ''));
        $최고점_point = (int)($순위row['mpoint'] ?? 0);
        if (tb_member_skip_auto_titles_for_credit($최고점_현재타이틀, $최고점_point)) {
            $야신부여대상 = null;
        } elseif ($최고점_현재타이틀 !== '' && $최고점_현재타이틀 !== $야신타이틀) {
            $야신부여대상 = null;
        } else {
            $현재야신 = db_select("
                SELECT idx
                FROM tb_member
                WHERE title = '" . addslashes($야신타이틀) . "'
                LIMIT 1
            ");
            $현재야신_idx = (int)($현재야신['idx'] ?? 0);
            $최고점_idx = (int)($순위row['member_idx'] ?? 0);

            if ($현재야신_idx === 0 || $현재야신_idx !== $최고점_idx) {
                $야신부여대상 = $순위row;
            }
        }
    }
}

if ($야신부여대상 && (int)$야신부여대상['member_idx'] > 0) {
    $야신수혜자_idx  = (int)$야신부여대상['member_idx'];
    $야신수혜자_name = $야신부여대상['name'];

    // 기존 🎲야신 보유자에게서 타이틀 제거
    db_query("
        UPDATE tb_member
        SET title = ''        WHERE title = '" . addslashes($야신타이틀) . "'
    ");
    // 타이틀 비어 있던 순위자에게 🎲야신 부여
    db_query("
        UPDATE tb_member
        SET title = '" . addslashes($야신타이틀) . "'        WHERE idx = {$야신수혜자_idx}
    ");
    $야신멘트 = addslashes("주사위 역대 최고점 달성 [ {$야신수혜자_name} ] 🎲야신 타이틀 부여!");
    echo $야신멘트."<Br>";
    db_query("
        INSERT INTO tb_lotto_info
        SET status = 0, msg = '{$야신멘트}', leverage = 0, item = '" . addslashes($야신수혜자_name) . "', regdate = NOW()
    ");
}















// 🌠전설: 무기 강화 수치 최고 + 무기 장착만 기준. 🆘신불자·마이너스 냥은 자동 부여 제외
$전설타이틀 = '🌠전설';
$전설_sql = "
    SELECT
        idx,
        name,
        title,
        COALESCE(point, 0) AS point,
        COALESCE(enhance, 0) AS enhance_lv
    FROM tb_member
    WHERE COALESCE(enhance, 0) > 0
      AND TRIM(IFNULL(item, '')) <> ''
    ORDER BY
        COALESCE(enhance, 0) DESC,
        (강화성공시간 IS NULL) ASC,
        강화성공시간 ASC,
        idx ASC
    LIMIT 1
";
$전설_rs = db_query($전설_sql);
$전설_target = ($전설_rs) ? db_fetch($전설_rs) : null;

$현재전설 = db_select("
    SELECT idx
    FROM tb_member
    WHERE title = '" . addslashes($전설타이틀) . "'
    LIMIT 1
");
$현재전설_idx = (int)($현재전설['idx'] ?? 0);

if ($전설_target) {
    $전설_member_idx = (int)$전설_target['idx'];
    $전설_member_name = trim((string)($전설_target['name'] ?? ''));
    $전설_enhn = (int)($전설_target['enhance_lv'] ?? 0);
    $전설_target_title = trim((string)($전설_target['title'] ?? ''));
    $전설_point = (int)($전설_target['point'] ?? 0);
    if ($전설_member_idx > 0 && $전설_member_name !== ''
        && !tb_member_skip_auto_titles_for_credit($전설_target_title, $전설_point)
        && ($현재전설_idx !== $전설_member_idx
            || $전설_target_title !== $전설타이틀)) {

        db_query("
            UPDATE tb_member
            SET title = ''
            WHERE title = '" . addslashes($전설타이틀) . "'
        ");
        db_query("
            UPDATE tb_member
            SET title = '" . addslashes($전설타이틀) . "'
            WHERE idx = {$전설_member_idx}
        ");

        $전설_msg = addslashes("무기 강화 +{$전설_enhn} 최고 [ {$전설_member_name} ] {$전설타이틀} 칭호 부여!");
        $전설_name_esc = addslashes($전설_member_name);
        db_query("
            INSERT INTO tb_lotto_info
            SET status = 0,
                msg = '{$전설_msg}',
                leverage = 0,
                item = '{$전설_name_esc}',
                regdate = NOW()
        ");
    }
} else {
    db_query("
        UPDATE tb_member
        SET title = ''
        WHERE title = '" . addslashes($전설타이틀) . "'
    ");
}

} // $타이틀스캔실행

// 🆘 신불자 처리
//  - point가 0 미만인 회원은 '🆘신불자' 타이틀 부여 + tb_member.credit = 1
//  - 최초 진입 시 credit_debt_entered_at 기록 + 로또 정보 1회 로그
//  - 보유냥 0 이상 회복: 타이틀 해제 + credit 초기화
//  - 누적 신불 회차(credit_debt_times): 최초 진입 시마다 +1, 신용 회복 시 초기화하지 않음
$신불자_rs = db_query("
    SELECT idx, name, point, title, credit_debt_at, credit_debt_entered_at
    FROM tb_member
    WHERE point < 0
");
if ($신불자_rs) {
    while ($row = db_fetch($신불자_rs)) {
        $midx = (int)$row['idx'];
        $닉 = trim((string)$row['name']);
        if ($midx <= 0 || $닉 === '') continue;
        $닉_esc = addslashes($닉);
        $point = (int)$row['point'];
        $현재타이틀 = trim((string)($row['title'] ?? ''));
        $enteredRaw = $row['credit_debt_entered_at'] ?? null;
        $entered비었음 = ($enteredRaw === null || $enteredRaw === '');

        if ($entered비었음) {
            if ($현재타이틀 !== '🆘신불자') {
                db_query("UPDATE tb_member SET title = '🆘신불자', credit = 1, credit_debt_at = NOW(), credit_debt_entered_at = NOW(), credit_recovery_plus3_at = NULL, credit_debt_times = IFNULL(credit_debt_times, 0) + 1 WHERE idx = {$midx}");
                $minusAbs = abs($point);
                $logMsg = "🆘 {$닉} 신불자 진입\n현재 마이너스 " . number_format($minusAbs) . "냥\n보유냥 0 이상 회복 시 타이틀 해제";
                $logMsg_esc = addslashes($logMsg);
                db_query("
                    INSERT INTO tb_lotto_info
                    SET status = 0, msg = '{$logMsg_esc}', leverage = 0, item = '{$닉_esc}', regdate = NOW()
                ");
            } else {
                db_query("UPDATE tb_member SET credit_debt_entered_at = COALESCE(credit_debt_at, NOW()), credit_debt_times = GREATEST(IFNULL(credit_debt_times, 0), 1) WHERE idx = {$midx} AND credit_debt_entered_at IS NULL");
            }
        } elseif ($현재타이틀 !== '🆘신불자') {
            db_query("UPDATE tb_member SET title = '🆘신불자', credit = 1 WHERE idx = {$midx}");
        }
    }
}

// ✅ 신용 회복: credit = 1 이고 보유냥이 마이너스가 아니면 알림 + 신불 타이틀 해제 + credit 초기화
$신용회복_rs = db_query("
    SELECT idx, name, point, title
    FROM tb_member
    WHERE IFNULL(credit, 0) = 1
      AND point >= 0
");
if ($신용회복_rs) {
    while ($r = db_fetch($신용회복_rs)) {
        $midx_r = (int)$r['idx'];
        $닉_r = trim((string)($r['name'] ?? ''));
        if ($midx_r <= 0 || $닉_r === '') {
            continue;
        }
        $보유냥_r = (int)($r['point'] ?? 0);
        if ($보유냥_r < 0) {
            continue;
        }
        $타이틀_r = trim((string)($r['title'] ?? ''));
        $닉_r_esc = addslashes($닉_r);
        if ($타이틀_r === '🆘신불자') {
            db_query("UPDATE tb_member SET credit = 0, title = '', credit_debt_at = NULL, credit_debt_entered_at = NULL, credit_recovery_plus3_at = NULL WHERE idx = {$midx_r}");
        } else {
            db_query("UPDATE tb_member SET credit = 0, credit_debt_at = NULL, credit_debt_entered_at = NULL, credit_recovery_plus3_at = NULL WHERE idx = {$midx_r}");
        }
        $회복멘트 = "✅ {$닉_r} 신용 회복!\n보유냥이 마이너스가 아니어서 신불자 타이틀이 해제되었습니다.";
        $회복멘트_esc = addslashes($회복멘트);
        db_query("
            INSERT INTO tb_lotto_info
            SET status = 0, msg = '{$회복멘트_esc}', leverage = 0, item = '{$닉_r_esc}', regdate = NOW()
        ");
    }
}

