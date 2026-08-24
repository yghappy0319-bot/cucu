<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/record_board.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!db_table_exists('tb_admin_record_board')) {
    alert_goto('기록 게시판 테이블이 없습니다. sql/tb_admin_record_board.sql 을 실행해 주세요.', '/admin/record_board.php');
}

$action = trim((string) ($_POST['action'] ?? ''));

$rb_valid_date_post = static function (string $s): ?string {
    if ($s === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
};

/** @return int|null 양의 정수만, 아니면 null */
$parse_won = static function ($v): ?int {
    if ($v === '' || $v === null) {
        return 0;
    }
    if (!is_numeric($v)) {
        return null;
    }
    $n = (int) round((float) $v);
    if ($n < 0) {
        return null;
    }
    return $n;
};

/** @return int[]|null */
$parse_sells = static function ($post_sells, int $expected_count) use ($parse_won): ?array {
    if (!is_array($post_sells)) {
        return null;
    }
    $post_sells = array_values($post_sells);
    if (count($post_sells) !== $expected_count) {
        return null;
    }
    $out = [];
    foreach ($post_sells as $v) {
        $p = $parse_won($v);
        if ($p === null) {
            return null;
        }
        $out[] = $p;
    }
    return $out;
};

if ($action === 'add') {
    $posted_reg = trim((string) ($_POST['reg_date'] ?? ''));
    $add_back   = '/admin/record_board.php';
    $rd_ok      = $rb_valid_date_post($posted_reg);
    if ($rd_ok !== null) {
        $add_back .= '?' . http_build_query(['reg_date' => $rd_ok]);
    }

    $title = trim((string) ($_POST['rb_title'] ?? ''));
    $memo  = trim((string) ($_POST['rb_memo'] ?? ''));
    $buy   = $parse_won($_POST['rb_buy'] ?? '');
    $cnt   = (int) ($_POST['rb_count'] ?? 0);
    $cnt   = max(1, min(20, $cnt));
    $sells = $parse_sells($_POST['rb_sell'] ?? [], $cnt);

    if ($title === '' || strlen($title) > 200) {
        alert_goto('제목을 1~200자로 입력해 주세요.', $add_back);
    }
    if ($buy === null) {
        alert_goto('총 구매가는 0 이상의 숫자로 입력해 주세요.', $add_back);
    }
    if ($sells === null) {
        alert_goto('개수와 판매가 입력 칸이 맞지 않거나, 판매가에 올바른 숫자를 넣어 주세요.', $add_back);
    }

    $at_ymd = $rb_valid_date_post($posted_reg);
    if ($at_ymd !== null && $at_ymd > date('Y-m-d')) {
        alert_goto('등록일은 오늘 이후로 설정할 수 없습니다.', $add_back);
    }
    if ($at_ymd === null) {
        $dt_sql = 'NOW()';
        $goto   = '/admin/record_board.php?' . http_build_query(['reg_date' => date('Y-m-d')]);
    } else {
        $dt_ts  = $at_ymd . ' ' . date('H:i:s');
        $dt_sql = "'" . db_escape($dt_ts) . "'";
        $goto   = '/admin/record_board.php?' . http_build_query(['reg_date' => $at_ymd]);
    }

    $ad_idx    = (int) $ad['ad_idx'];
    $esc_t     = db_escape($title);
    $esc_m     = db_escape($memo);
    $esc_json  = db_escape(json_encode($sells, JSON_UNESCAPED_UNICODE));

    $sql = "
        INSERT INTO tb_admin_record_board (ad_idx, rb_title, rb_memo, rb_count, rb_buy, rb_sells, rb_created_at, rb_updated_at)
        VALUES ({$ad_idx}, '{$esc_t}', '{$esc_m}', {$cnt}, {$buy}, '{$esc_json}', {$dt_sql}, {$dt_sql})
    ";
    if (!db_query($sql)) {
        alert_goto('등록 중 오류가 발생했습니다.', $add_back);
    }
    alert_goto('기록을 등록했습니다.', $goto);
}

if ($action === 'edit') {
    $idx = (int) ($_POST['rb_idx'] ?? 0);
    if ($idx < 1) {
        alert_goto('잘못된 요청입니다.', '/admin/record_board.php');
    }

    $title = trim((string) ($_POST['rb_title'] ?? ''));
    $memo  = trim((string) ($_POST['rb_memo'] ?? ''));
    $buy   = $parse_won($_POST['rb_buy'] ?? '');
    $cnt   = (int) ($_POST['rb_count'] ?? 0);
    $cnt   = max(1, min(20, $cnt));
    $sells = $parse_sells($_POST['rb_sell'] ?? [], $cnt);

    if ($title === '' || strlen($title) > 200) {
        alert_goto('제목을 1~200자로 입력해 주세요.', '/admin/record_board.php?edit=' . $idx);
    }
    if ($buy === null) {
        alert_goto('총 구매가는 0 이상의 숫자로 입력해 주세요.', '/admin/record_board.php?edit=' . $idx);
    }
    if ($sells === null) {
        alert_goto('개수와 판매가 입력 칸이 맞지 않거나, 판매가에 올바른 숫자를 넣어 주세요.', '/admin/record_board.php?edit=' . $idx);
    }

    $esc_t    = db_escape($title);
    $esc_m    = db_escape($memo);
    $esc_json = db_escape(json_encode($sells, JSON_UNESCAPED_UNICODE));

    $exists = db_assoc(db_query("SELECT rb_idx FROM tb_admin_record_board WHERE rb_idx = {$idx} LIMIT 1"));
    if (!$exists) {
        alert_goto('존재하지 않는 글입니다.', '/admin/record_board.php');
    }

    $sql = "
        UPDATE tb_admin_record_board
        SET rb_title = '{$esc_t}', rb_memo = '{$esc_m}', rb_count = {$cnt}, rb_buy = {$buy},
            rb_sells = '{$esc_json}', rb_updated_at = NOW()
        WHERE rb_idx = {$idx} LIMIT 1
    ";
    if (!db_query($sql)) {
        alert_goto('수정 중 오류가 발생했습니다.', '/admin/record_board.php?edit=' . $idx);
    }
    alert_goto('기록을 수정했습니다.', '/admin/record_board.php');
}

if ($action === 'delete') {
    $idx = (int) ($_POST['rb_idx'] ?? 0);
    if ($idx < 1) {
        alert_goto('잘못된 요청입니다.', '/admin/record_board.php');
    }
    if (!db_query("DELETE FROM tb_admin_record_board WHERE rb_idx = {$idx} LIMIT 1")
        || mysqli_affected_rows($GLOBALS['conn']) !== 1) {
        alert_goto('삭제에 실패했습니다.', '/admin/record_board.php');
    }
    alert_goto('기록을 삭제했습니다.', '/admin/record_board.php');
}

alert_goto('잘못된 요청입니다.', '/admin/record_board.php');
