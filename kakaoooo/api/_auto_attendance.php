<?php
include_once "/home/kakao/public_html/lib/_function.php";
include_once "/home/kakao/public_html/api/function.php";

// 매월 1일 우리방 시즌 초기화 (config.우리방초기화월 로 월 1회)
require_once __DIR__ . '/room_reset.inc.php';
if (function_exists('우리방초기화_월간자동_시도')) {
    @우리방초기화_월간자동_시도();
}

$오늘출석 = date('Y-m-d');
$출석일행 = db_select("SELECT 출석일 FROM config LIMIT 1");

// 진행중 대출 · 달력일 기준 원금 10% 누적이자
if (is_file(__DIR__ . '/game/loan.inc.php')) {
    require_once __DIR__ . '/game/loan.inc.php';
    if (function_exists('대출_전원이자_반영')) {
        @대출_전원이자_반영();
    }
}
if (($출석일행['출석일'] ?? '') !== $오늘출석) {
    // 전날 .출석포기(attendance=2) — 당일 한정이므로 날짜가 바뀌면 해제
    db_query("UPDATE tb_member SET attendance = 1 WHERE attendance = 2");
}
db_query("UPDATE config SET `누적출석냥` = 0, `출석일` = '{$오늘출석}' WHERE `출석일` IS NULL OR `출석일` <> '{$오늘출석}' LIMIT 1");

$현재시 = (int)date('H');
$현재분 = (int)date('i');
// tb_msg 집계: 매분(크론 주기) — 100타 출석 알람 지연 최소화
// 23:55·00:05는 해당 분에 반드시 실행
$출석스캔_주기분 = 1;
$출석스캔_시간민감 = ($현재시 === 0 && $현재분 === 5)
    || ($현재시 === 23 && $현재분 === 55);
$출석스캔실행 = $출석스캔_시간민감 || ($현재분 % $출석스캔_주기분 === 0);

// 생타 200+ 연속 3일 — 어제까지 정산(누락 소급) 후, 당일 200타 달성 시 즉시 지급
include_once __DIR__ . '/_auto_raw_tasu_streak.php';
if (function_exists('생타연속_자정마감')) {
    생타연속_자정마감();
}

if ($출석스캔실행) {
// ── 일 자동 출석: 100타=tasu 합(화면 오늘타수와 동일)·연속일 — 오늘 메시지 로우 1000·30, 2000·60, 3000·100박스(각 당일 1회)
$계급출석비율 = 0.003;
// 100타 일출석 1명당: "해당 출석자의 보유 newpoint" × 0.1% 를 누적출석냥에 더함
// (전체 보유 냥 합계 기준이 아님)

$어제출석 = date('Y-m-d', strtotime('-1 day'));
$오늘시작 = $오늘출석 . ' 00:00:00';
$내일시작 = date('Y-m-d', strtotime($오늘출석 . ' +1 day')) . ' 00:00:00';

$일출석대상_sql = "
    SELECT
        m.idx,
        m.name,
        m.point,
        m.streak,
        m.last_att_date,
        m.attendance,
        t.today_row_cnt,
        t.today_tasu_sum
    FROM tb_member m
    INNER JOIN (
        SELECT
            nickname,
            " . 생타_SQL_select_expr('msg') . " AS today_row_cnt,
            " . 버프타_SQL_select_expr('msg', 'tasu') . " AS today_tasu_sum
        FROM tb_msg
        WHERE regdate >= '{$오늘시작}'
          AND regdate < '{$내일시작}'
          AND tasu != 0
        GROUP BY nickname
    ) t ON t.nickname = m.name
    WHERE m.status = 0
      AND (t.today_row_cnt >= 100 OR t.today_tasu_sum >= 1000)
";
$일출석_rs = db_query($일출석대상_sql);
while ($일출석 = db_fetch($일출석_rs)) {
    $닉 = $일출석['name'];
    $닉_esc = addslashes($닉);
    $midx = (int)$일출석['idx'];
    $today_row_cnt = (int)$일출석['today_row_cnt'];
    $today_tasu_sum = (int)$일출석['today_tasu_sum'];

    // tb_mission (버프1000타는 .미션 목록에는 미표시, 홀짝 조회 전용)
    if ($today_row_cnt >= 1000) {
        미션완료_기록_if_new($닉, '타수', '1000타');
    }
    if ($today_tasu_sum >= 1000) {
        미션완료_기록_if_new($닉, '타수', '버프1000타');
    }

    // ── 오늘 tasu 합 100 이상: 출석 + 연속출석(streak) + 랜덤박스 3
    $출석존재 = db_select("
        SELECT COUNT(*) AS cnt
        FROM tb_attendance
        WHERE regdate = '{$오늘출석}' AND nickname = '{$닉_esc}'
    ");
    
    if ((int)($출석존재['cnt'] ?? 0) === 0 && $today_row_cnt >= 100) {
        $정보 = db_select("SELECT * FROM tb_member WHERE name = '{$닉_esc}' ");
        if (empty($정보)) {
            continue;
        }
        $출석포기자여부 = ((int)($정보['attendance'] ?? 0) === 2);

        if ($정보['last_att_date'] == $어제출석) {
            $newStreak = (int)$정보['streak'] + 1;
        } else {
            $newStreak = 1;
        }

        db_query("
            INSERT INTO tb_attendance
            SET regdate = '{$오늘출석}', nickname = '{$닉_esc}'
        ");

        db_query("
            UPDATE tb_member
            SET streak = {$newStreak},
                last_att_date = '{$오늘출석}',
                attendance = 1
            WHERE name = '{$닉_esc}'
        ");

        // 출석한 친구 본인 보유 newpoint 기준 0.1% 적립
        $출석자보유냥 = (float)($정보['newpoint'] ?? 0);
        $출석누적분 = round($출석자보유냥 * $계급출석비율, 1);

        $멘트100 = "{$닉} 100타 달성🎉 - 출석미션완료!\n 연속출석 {$newStreak}일차\n보유 냥의 0.1% 적립";
        if ($출석포기자여부) {
            $멘트100 = "{$닉} 100타 달성🎉 - 출석미션완료!\n 연속출석 {$newStreak}일차\n(출석포기 해제) · 보유 냥의 0.1% 적립";
        }
        $멘트100_esc = addslashes($멘트100);
        db_query("
            INSERT INTO tb_lotto_info
            SET status = 0, msg = '{$멘트100_esc}', leverage = 0, item = '{$닉_esc}', regdate = NOW()
        ");

        if ($출석누적분 > 0) {
            db_query("UPDATE config SET `누적출석냥` = COALESCE(`누적출석냥`, 0) + {$출석누적분} LIMIT 1");
        }

        미션완료_기록_if_new($닉, '타수', '100타');
    }

    if ($today_row_cnt >= 200 && function_exists('생타연속_오늘200_지급시도')) {
        생타연속_오늘200_지급시도($midx, $닉, $today_row_cnt);
    }

}

} // $출석스캔실행

// ── 시간 민감 작업: 평타 자동 정산(23:55)

// 평타 정산: 매일 23:55 한 분 — 본방 `.정산`과 동일 지급, 공지는 tb_lotto_info(item=평타정산)
if ($현재시 === 23 && $현재분 === 55) {
    include_once __DIR__ . '/_pyunta_settle_cron.php';
    평타_자동정산_23시55분();
    exit;
}

exit;

