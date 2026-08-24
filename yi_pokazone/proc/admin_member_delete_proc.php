<?php
/**
 * 회원 영구 삭제 (관리자)
 *
 * 순서 요약: 회원을 참조하는 자식 행 → 거래 채팅(메시지→방→판매글) → 커뮤니티(좋아요·본인 댓글→본인 글)
 * → 공지/문의 참조 해제 → 로그인 로그 → tb_member
 *
 * 레거시 DB에만 있는 추가 FK가 있으면 해당 테이블 DELETE/UPDATE 를 이 파일에 더해야 합니다.
 */
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/members.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!db_table_exists('tb_member')) {
    alert_goto('회원 테이블이 없습니다.', '/admin/members.php');
}

global $conn;
if (!$conn instanceof mysqli) {
    alert_goto('DB 연결이 없습니다.', '/admin/members.php');
}

$mb_idx = (int) ($_POST['mb_idx'] ?? 0);
if ($mb_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/admin/members.php');
}

$confirm = trim((string) ($_POST['confirm_mb_id'] ?? ''));
if ($confirm === '') {
    alert_goto('삭제 확인을 위해 해당 회원의 아이디를 입력해 주세요.', '/admin/member_edit.php?idx=' . $mb_idx);
}

$rs = db_query("SELECT mb_idx, mb_id, mb_level FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
$row = db_assoc($rs);
if (!$row) {
    alert_goto('존재하지 않는 회원입니다.', '/admin/members.php');
}

if ($confirm !== $row['mb_id']) {
    alert_goto('입력한 아이디가 회원 정보와 일치하지 않습니다.', '/admin/member_edit.php?idx=' . $mb_idx);
}

$admin_count = (int) db_result(
    "SELECT COUNT(*) FROM tb_member WHERE mb_level >= 9 AND mb_status = 1"
);
if ((int) $row['mb_level'] >= 9 && $admin_count <= 1) {
    alert_goto('등급 9 이상 회원이 한 명뿐이라 삭제할 수 없습니다.', '/admin/member_edit.php?idx=' . $mb_idx);
}

$last_sql_err = '';

$run = static function (string $sql) use ($conn, &$last_sql_err): bool {
    $last_sql_err = '';
    $ok           = db_query($sql);
    if (!$ok) {
        $last_sql_err = mysqli_error($conn) ?: '(DB 오류, 메시지 없음)';

        return false;
    }

    return true;
};

$fail = static function (string $hint) use ($conn, &$last_sql_err, $mb_idx): void {
    mysqli_rollback($conn);
    $detail = $last_sql_err !== '' ? ' DB: ' . $last_sql_err : '';
    alert_goto($hint . $detail, '/admin/member_edit.php?idx=' . $mb_idx);
};

if (!mysqli_begin_transaction($conn)) {
    alert_goto(
        '트랜잭션을 시작할 수 없습니다.' . (mysqli_error($conn) ? ' ' . mysqli_error($conn) : ''),
        '/admin/member_edit.php?idx=' . $mb_idx
    );
}

if (db_table_exists('tb_web_push_subscription')) {
    if (!$run("DELETE FROM tb_web_push_subscription WHERE mb_idx = {$mb_idx}")) {
        $fail('웹 푸시 구독 삭제에 실패했습니다.');
    }
}
if (db_table_exists('tb_point_log')) {
    if (!$run("DELETE FROM tb_point_log WHERE mb_idx = {$mb_idx}")) {
        $fail('포인트 내역 삭제에 실패했습니다.');
    }
}
if (db_table_exists('tb_cash_log')) {
    if (!$run("DELETE FROM tb_cash_log WHERE mb_idx = {$mb_idx}")) {
        $fail('캐시 내역 삭제에 실패했습니다.');
    }
}
if (db_table_exists('tb_attendance')) {
    if (!$run("DELETE FROM tb_attendance WHERE mb_idx = {$mb_idx}")) {
        $fail('출석 기록 삭제에 실패했습니다.');
    }
}

/* 거래 채팅: 방·거래 삭제 전 발신자(mb_idx) 행 제거 — 일부 DB에 msg→member FK 가 있을 수 있음 */
if (db_table_exists('tb_trade_room_msg')) {
    if (!$run("DELETE FROM tb_trade_room_msg WHERE mb_idx = {$mb_idx}")) {
        $fail('거래 메시지 삭제에 실패했습니다.');
    }
}
/* 구매자로 참여한 방 (메시지는 위에서 일부 삭제됐고, 방 삭제 시 나머지 메시지는 CASCADE) */
if (db_table_exists('tb_trade_room')) {
    if (!$run("DELETE FROM tb_trade_room WHERE buyer_mb_idx = {$mb_idx}")) {
        $fail('거래 문의방 삭제에 실패했습니다.');
    }
}
/* 거래글 삭제 전: 본인이 남긴 공개 댓글 제거 (타인 글 댓 댓글) */
if (db_table_exists('tb_trade_comment')) {
    if (!$run("DELETE FROM tb_trade_comment WHERE mb_idx = {$mb_idx}")) {
        $fail('거래글 댓글 삭제에 실패했습니다.');
    }
}
/* 판매자 본인 거래글 — CASCADE 로 해당 거래의 방·메시지·이미지 행 정리 */
if (db_table_exists('tb_trade')) {
    if (!$run("DELETE FROM tb_trade WHERE mb_idx = {$mb_idx}")) {
        $fail('등록한 거래글 삭제에 실패했습니다.');
    }
}

/*
 * 커뮤니티: 회원 기준 좋아요·본인 댓글 제거 후 본인 글 삭제.
 * (타인 글에 단 본인 댓글은 첫 번째 DELETE 로 제거, 본인 글의 타인 댓글은 글 삭제 시 fk_cc_co CASCADE)
 */
if (db_table_exists('tb_community_like')) {
    if (!$run("DELETE FROM tb_community_like WHERE mb_idx = {$mb_idx}")) {
        $fail('커뮤니티 좋아요 삭제에 실패했습니다.');
    }
}
if (db_table_exists('tb_trade_like')) {
    if (!$run("DELETE FROM tb_trade_like WHERE mb_idx = {$mb_idx}")) {
        $fail('거래글 찜 삭제에 실패했습니다.');
    }
}
if (db_table_exists('tb_community_comment')) {
    if (!$run("DELETE FROM tb_community_comment WHERE mb_idx = {$mb_idx}")) {
        $fail('커뮤니티 댓글 삭제에 실패했습니다.');
    }
}
if (db_table_exists('tb_community')) {
    if (!$run("DELETE FROM tb_community WHERE mb_idx = {$mb_idx}")) {
        $fail('커뮤니티 게시글 삭제에 실패했습니다.');
    }
}

/* 공지 mb_idx 는 NULL 허용 스키마 필요 (migrate_tb_notice_admin_author.sql). 아니면 여기서 실패 메시지로 확인 가능 */
if (db_table_exists('tb_notice')) {
    if (!$run("UPDATE tb_notice SET mb_idx = NULL WHERE mb_idx = {$mb_idx}")) {
        $fail('공지 작성자 참조 해제에 실패했습니다.');
    }
}

if (db_table_exists('tb_inquiry')) {
    if (!$run("UPDATE tb_inquiry SET mb_idx = NULL WHERE mb_idx = {$mb_idx}")) {
        $fail('문의 회원 참조 해제에 실패했습니다.');
    }
}
if (db_table_exists('tb_member_login_log')) {
    if (!$run("DELETE FROM tb_member_login_log WHERE mb_idx = {$mb_idx}")) {
        $fail('로그인 이력 삭제에 실패했습니다.');
    }
}

if (!$run("DELETE FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1")) {
    $fail('회원 레코드 삭제에 실패했습니다.');
}
if (mysqli_affected_rows($conn) !== 1) {
    mysqli_rollback($conn);
    alert_goto('삭제 대상 회원이 없거나 이미 삭제되었습니다.', '/admin/members.php');
}

if (!mysqli_commit($conn)) {
    mysqli_rollback($conn);
    $detail = mysqli_error($conn) ?: '';
    alert_goto(
        '삭제 확정(커밋)에 실패했습니다.' . ($detail !== '' ? ' DB: ' . $detail : ''),
        '/admin/member_edit.php?idx=' . $mb_idx
    );
}

alert_goto('회원을 삭제했습니다.', '/admin/members.php');
