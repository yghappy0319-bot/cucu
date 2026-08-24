<?php
include("/home/sms/public_html/lib/function.php");
include("/home/sms/public_html/lib/luckybank_function.php");

set_time_limit(180);
ini_set('memory_limit', '512M');

$date_from = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : '2026-02-01';
$date_to   = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : '2026-08-07';
$keyword   = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$selected_acc = isset($_GET['acc']) ? trim($_GET['acc']) : '';
$page      = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page  = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
if (!in_array($per_page, array(30, 50, 100, 200), true)) {
    $per_page = 50;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = '2026-02-01';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = '2026-08-07';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_selected') {
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : array();
    $clean_ids = array();
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $clean_ids[] = $id;
        }
    }
    $deleted = 0;
    if ($clean_ids) {
        $id_list = implode(',', $clean_ids);
        db_query("delete from bankdata where id in ({$id_list})");
        $deleted = (int) mysqli_affected_rows($conn);
        @db_query("delete from bank_data_detail where data_idx in ({$id_list})");
        @db_query("delete from site_bank_data where data_idx in ({$id_list})");
    }
    $redirect = array(
        'date_from' => isset($_POST['date_from']) ? $_POST['date_from'] : $date_from,
        'date_to' => isset($_POST['date_to']) ? $_POST['date_to'] : $date_to,
        'keyword' => isset($_POST['keyword']) ? $_POST['keyword'] : $keyword,
        'acc' => isset($_POST['acc']) ? $_POST['acc'] : $selected_acc,
        'page' => isset($_POST['page']) ? $_POST['page'] : $page,
        'per_page' => isset($_POST['per_page']) ? $_POST['per_page'] : $per_page,
        'deleted' => $deleted,
    );
    header('Location: acount_mismatch.php?' . http_build_query($redirect));
    exit;
}

$deleted_count = isset($_GET['deleted']) ? (int) $_GET['deleted'] : 0;

$from_sql = mysqli_real_escape_string($conn, $date_from . ' 00:00:00');
$to_sql   = mysqli_real_escape_string($conn, $date_to . ' 23:59:59');

$UNKNOWN_ACC = '__unknown__';

$site_sql = "select idx, site, bank, acount, website from site where acount is not null and acount != '' order by site asc";
$site_result = lb_db_query($site_sql);
$sites = array();
$accounts = array();
while ($row = lb_db_fetch($site_result)) {
    $acc = trim($row['acount']);
    if ($acc === '') {
        continue;
    }
    $sites[] = $row;
    $accounts[$acc] = true;
}
$account_list = array_keys($accounts);
$account_count = count($account_list);

$not_likes = array();
foreach ($account_list as $acc) {
    $escaped = mysqli_real_escape_string($conn, $acc);
    $not_likes[] = "sms NOT LIKE '%{$escaped}%'";
}

$base_where = "regdate >= '{$from_sql}' AND regdate <= '{$to_sql}'";
if ($keyword !== '') {
    $escaped_kw = mysqli_real_escape_string($conn, $keyword);
    $base_where .= " AND sms LIKE '%{$escaped_kw}%'";
}
$mismatch_where = $base_where;
if ($not_likes) {
    $mismatch_where .= ' AND ' . implode(' AND ', $not_likes);
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function qs($overrides = array()) {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null) {
            unset($params[$k]);
        }
    }
    return '?' . http_build_query($params);
}

function extract_sms_account($sms) {
    // KB: [KB]06/14 13:49 538801**478
    if (preg_match('/\[KB\][^\d]*\d{2}\/\d{2}\s+\d{2}:\d{2}\s*([0-9*]+)/u', $sms, $m)) {
        return $m[1];
    }
    if (preg_match('/<새마을금고>\s*([0-9*]+)/u', $sms, $m)) {
        return $m[1];
    }
    if (preg_match('/신협\s*([0-9*]+)/u', $sms, $m)) {
        return $m[1];
    }
    if (preg_match('/수협\s*([0-9*]+)/u', $sms, $m)) {
        return $m[1];
    }
    if (preg_match('/우체국\s*([0-9*]+)/u', $sms, $m)) {
        return $m[1];
    }
    if (preg_match('/\[케이뱅크\].*?\((\d{4})\)/u', $sms, $m)) {
        return '(' . $m[1] . ')';
    }
    // 마스킹 계좌 (가장 긴 것)
    if (preg_match_all('/(?<![0-9\/])([0-9]{3,}[*]{1,}[0-9]{1,})(?![0-9])/u', $sms, $m)) {
        $best = '';
        foreach ($m[1] as $cand) {
            if (strlen($cand) > strlen($best)) {
                $best = $cand;
            }
        }
        if ($best !== '') {
            return $best;
        }
    }
    // 마스킹 없는 10~14자리
    if (preg_match_all('/(?<![0-9])([0-9]{10,14})(?![0-9])/u', $sms, $m)) {
        return $m[1][0];
    }
    return '';
}

function guess_bank($sms) {
    if (stripos($sms, '[KB]') !== false || strpos($sms, '국민') !== false) return '국민은행';
    if (strpos($sms, '농협') !== false || stripos($sms, 'NH') !== false) return '농협은행';
    if (strpos($sms, '신한') !== false) return '신한은행';
    if (strpos($sms, '기업') !== false || strpos($sms, 'IBK') !== false) return '기업은행';
    if (strpos($sms, '우리') !== false) return '우리은행';
    if (strpos($sms, '하나') !== false) return '하나은행';
    if (strpos($sms, '수협') !== false) return '수협';
    if (strpos($sms, '신협') !== false) return '신협중앙회';
    if (strpos($sms, '새마을') !== false) return '새마을금고';
    if (strpos($sms, '우체국') !== false) return '우체국';
    if (strpos($sms, '케이뱅크') !== false) return '케이뱅크';
    if (strpos($sms, 'SC') !== false || strpos($sms, '제일') !== false) return 'SC제일은행';
    return '';
}

function acc_label($acc, $unknown_key) {
    return $acc === $unknown_key ? '계좌번호 확인불가' : $acc;
}

$total_sms = (int) db_result("select count(*) from bankdata where {$base_where}");
$mismatch_count = (int) db_result("select count(*) from bankdata where {$mismatch_where}");
$matched_count = max(0, $total_sms - $mismatch_count);

$groups = array();
$group_scan = db_query("select sms, regdate from bankdata where {$mismatch_where}");
if ($group_scan) {
    while ($row = mysqli_fetch_assoc($group_scan)) {
        $acc = extract_sms_account($row['sms']);
        if ($acc === '' || isset($accounts[$acc])) {
            $acc = $UNKNOWN_ACC;
        }
        if (!isset($groups[$acc])) {
            $groups[$acc] = array(
                'acount' => $acc,
                'count' => 0,
                'bank' => guess_bank($row['sms']),
                'last_date' => $row['regdate'],
            );
        }
        $groups[$acc]['count']++;
        if ($row['regdate'] > $groups[$acc]['last_date']) {
            $groups[$acc]['last_date'] = $row['regdate'];
            if ($groups[$acc]['bank'] === '') {
                $groups[$acc]['bank'] = guess_bank($row['sms']);
            }
        }
    }
}

uasort($groups, function ($a, $b) {
    if ($a['count'] === $b['count']) {
        return strcmp((string)$b['last_date'], (string)$a['last_date']);
    }
    return $b['count'] - $a['count'];
});
$group_count = count($groups);

$list_rows = array();
$total_pages = 1;
$detail_count = 0;

if ($selected_acc !== '') {
    if ($selected_acc === $UNKNOWN_ACC) {
        $unknown_rows = array();
        $scan = db_query("select * from bankdata where {$mismatch_where} order by regdate desc");
        if ($scan) {
            while ($row = mysqli_fetch_assoc($scan)) {
                $acc = extract_sms_account($row['sms']);
                if ($acc === '' || isset($accounts[$acc])) {
                    $unknown_rows[] = $row;
                }
            }
        }
        $detail_count = count($unknown_rows);
        $total_pages = max(1, (int) ceil($detail_count / $per_page));
        if ($page > $total_pages) {
            $page = $total_pages;
        }
        $list_rows = array_slice($unknown_rows, ($page - 1) * $per_page, $per_page);
    } else {
        $escaped_acc = mysqli_real_escape_string($conn, $selected_acc);
        $detail_where = $mismatch_where . " AND sms LIKE '%{$escaped_acc}%'";
        $detail_count = (int) db_result("select count(*) from bankdata where {$detail_where}");
        $total_pages = max(1, (int) ceil($detail_count / $per_page));
        if ($page > $total_pages) {
            $page = $total_pages;
        }
        $offset = ($page - 1) * $per_page;
        $scan = db_query("select * from bankdata where {$detail_where} order by regdate desc limit {$offset}, {$per_page}");
        if ($scan) {
            while ($row = mysqli_fetch_assoc($scan)) {
                $list_rows[] = $row;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>계좌 미일치 SMS 조회</title>
<style>
    :root {
        --bg: #0f1419;
        --panel: #1a222c;
        --panel2: #222c38;
        --line: #2e3a48;
        --text: #e8eef5;
        --muted: #8b9bb0;
        --accent: #3d8bfd;
        --warn: #f5a524;
        --danger: #ef5b5b;
        --ok: #3dd68c;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: "Malgun Gothic", "Apple SD Gothic Neo", sans-serif;
        background: var(--bg);
        color: var(--text);
        font-size: 14px;
    }
    .wrap { max-width: 1400px; margin: 0 auto; padding: 24px 16px 48px; }
    h1 { font-size: 22px; margin: 0 0 6px; }
    h2 { font-size: 16px; margin: 0 0 12px; }
    .sub { color: var(--muted); margin-bottom: 20px; line-height: 1.5; }
    .cards { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 18px; }
    .card {
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: 10px;
        padding: 16px;
    }
    .card .label { color: var(--muted); font-size: 12px; margin-bottom: 6px; }
    .card .num { font-size: 24px; font-weight: 700; }
    .card.warn .num { color: var(--warn); }
    .card.ok .num { color: var(--ok); }
    .card.danger .num { color: var(--danger); }
    form.filter {
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: 10px;
        padding: 14px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: end;
        margin-bottom: 16px;
    }
    label { display: block; color: var(--muted); font-size: 12px; margin-bottom: 4px; }
    input[type="date"], input[type="text"], select {
        background: var(--panel2);
        border: 1px solid var(--line);
        color: var(--text);
        border-radius: 6px;
        padding: 8px 10px;
        min-width: 150px;
    }
    button, .btn {
        background: var(--accent);
        color: #fff;
        border: 0;
        border-radius: 6px;
        padding: 9px 16px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        font-size: 14px;
    }
    button.ghost, .btn.ghost {
        background: var(--panel2);
        border: 1px solid var(--line);
    }
    button.danger, .btn.danger {
        background: var(--danger);
    }
    button.danger:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }
    .flash {
        background: rgba(61, 214, 140, 0.15);
        border: 1px solid var(--ok);
        color: var(--ok);
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 14px;
    }
    .chk { width: 36px; text-align: center; vertical-align: middle; }
    .toolbar { display: flex; gap: 8px; align-items: center; margin: 0 0 10px; }
    table { width: 100%; border-collapse: collapse; background: var(--panel); border-radius: 10px; overflow: hidden; }
    th, td { padding: 10px 12px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
    th { background: var(--panel2); color: var(--muted); font-weight: 600; font-size: 12px; }
    tr:hover td { background: rgba(61, 139, 253, 0.06); }
    .sms { white-space: pre-wrap; word-break: break-all; line-height: 1.45; }
    .meta { color: var(--muted); font-size: 12px; }
    .pager { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 16px; align-items: center; }
    .pager a, .pager span {
        padding: 6px 10px;
        border-radius: 6px;
        background: var(--panel);
        border: 1px solid var(--line);
        color: var(--text);
        text-decoration: none;
    }
    .pager .on { background: var(--accent); border-color: var(--accent); }
    details {
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: 10px;
        padding: 12px 14px;
        margin-bottom: 16px;
    }
    details summary { cursor: pointer; color: var(--muted); }
    .acc-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px 14px; margin-top: 10px; font-size: 13px; }
    .empty { padding: 40px; text-align: center; color: var(--muted); background: var(--panel); border-radius: 10px; }
    .group-row {
        display: grid;
        grid-template-columns: minmax(180px, 1.4fr) 140px 90px 180px;
        gap: 12px;
        padding: 12px 14px;
        background: var(--panel);
        border: 1px solid var(--line);
        border-bottom: 0;
        color: var(--text);
        text-decoration: none;
        align-items: center;
    }
    .group-row:first-of-type { border-radius: 10px 10px 0 0; }
    .group-list .group-row:last-child { border-bottom: 1px solid var(--line); border-radius: 0 0 10px 10px; }
    .group-list .group-row:first-of-type:last-child { border-radius: 10px; border-bottom: 1px solid var(--line); }
    .group-row:hover { background: rgba(61, 139, 253, 0.12); }
    .group-row.on { background: rgba(61, 139, 253, 0.22); }
    .group-head {
        display: grid;
        grid-template-columns: minmax(180px, 1.4fr) 140px 90px 180px;
        gap: 12px;
        padding: 8px 14px;
        color: var(--muted);
        font-size: 12px;
    }
    .acc-no { font-weight: 700; letter-spacing: 0.3px; }
    .count-num { color: var(--warn); font-weight: 700; }
    .detail-top { display: flex; gap: 10px; align-items: center; margin-bottom: 12px; flex-wrap: wrap; }
    .detail-top .acc-no { font-size: 18px; }
    @media (max-width: 900px) {
        .cards { grid-template-columns: 1fr 1fr; }
        .acc-grid { grid-template-columns: 1fr; }
        .group-head, .group-row { grid-template-columns: 1fr 1fr; }
    }
</style>
</head>
<body>
<div class="wrap">
    <h1>계좌 미일치 SMS 조회</h1>
    <div class="sub">
        럭키뱅크 <b>site.acount</b> 에 없는 계좌를 문자에서 뽑아 그룹으로 보여줍니다.<br>
        계좌를 누르면 해당 계좌 SMS만 확인합니다. (기본: 2026-02-01 ~ 2026-08-07)
    </div>

    <div class="cards">
        <div class="card">
            <div class="label">럭키뱅크 등록 계좌</div>
            <div class="num"><?= number_format($account_count) ?></div>
        </div>
        <div class="card">
            <div class="label">기간 내 전체 SMS</div>
            <div class="num"><?= number_format($total_sms) ?></div>
        </div>
        <div class="card ok">
            <div class="label">계좌 일치</div>
            <div class="num"><?= number_format($matched_count) ?></div>
        </div>
        <div class="card danger">
            <div class="label">계좌 미일치 SMS</div>
            <div class="num"><?= number_format($mismatch_count) ?></div>
        </div>
        <div class="card warn">
            <div class="label">미등록 계좌 그룹</div>
            <div class="num"><?= number_format($group_count) ?></div>
        </div>
    </div>

    <form class="filter" method="get">
        <div>
            <label>시작일</label>
            <input type="date" name="date_from" value="<?= h($date_from) ?>">
        </div>
        <div>
            <label>종료일</label>
            <input type="date" name="date_to" value="<?= h($date_to) ?>">
        </div>
        <div>
            <label>문자 검색</label>
            <input type="text" name="keyword" value="<?= h($keyword) ?>" placeholder="SMS 내용 검색">
        </div>
        <div>
            <label>페이지당</label>
            <select name="per_page">
                <?php foreach (array(30, 50, 100, 200) as $n) { ?>
                <option value="<?= $n ?>" <?= $per_page === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php } ?>
            </select>
        </div>
        <?php if ($selected_acc !== '') { ?>
        <input type="hidden" name="acc" value="<?= h($selected_acc) ?>">
        <?php } ?>
        <button type="submit">조회</button>
        <a class="btn ghost" href="acount_mismatch.php">기본기간</a>
    </form>

    <?php if ($deleted_count > 0) { ?>
        <div class="flash"><?= number_format($deleted_count) ?>건을 삭제했습니다.</div>
    <?php } ?>

    <details>
        <summary>비교에 사용한 럭키뱅크 계좌 <?= number_format($account_count) ?>건</summary>
        <div class="acc-grid">
            <?php foreach ($sites as $s) { ?>
            <div>
                <b><?= h($s['site']) ?></b>
                <span class="meta"> / <?= h($s['bank']) ?> / <?= h($s['acount']) ?></span>
            </div>
            <?php } ?>
        </div>
    </details>

    <?php if ($mismatch_count === 0) { ?>
        <div class="empty">해당 기간에 계좌 미일치 데이터가 없습니다.</div>
    <?php } elseif ($selected_acc === '') { ?>
        <h2>미등록 계좌 그룹</h2>
        <div class="group-head">
            <div>계좌번호</div>
            <div>은행</div>
            <div>건수</div>
            <div>최근 수신</div>
        </div>
        <div class="group-list">
            <?php foreach ($groups as $g) { ?>
            <a class="group-row" href="<?= h(qs(array('acc' => $g['acount'], 'page' => 1))) ?>">
                <div class="acc-no"><?= h(acc_label($g['acount'], $UNKNOWN_ACC)) ?></div>
                <div class="meta"><?= h($g['bank'] !== '' ? $g['bank'] : '-') ?></div>
                <div class="count-num"><?= number_format($g['count']) ?>건</div>
                <div class="meta"><?= h($g['last_date']) ?></div>
            </a>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="detail-top">
            <a class="btn ghost" href="<?= h(qs(array('acc' => null, 'page' => 1))) ?>">← 계좌 목록</a>
            <div class="acc-no"><?= h(acc_label($selected_acc, $UNKNOWN_ACC)) ?></div>
            <div class="meta"><?= number_format($detail_count) ?>건</div>
        </div>

        <?php if (!$list_rows) { ?>
            <div class="empty">이 계좌에 해당하는 SMS가 없습니다.</div>
        <?php } else { ?>
            <form method="post" id="delete_form" onsubmit="return confirmDelete();">
                <input type="hidden" name="action" value="delete_selected">
                <input type="hidden" name="date_from" value="<?= h($date_from) ?>">
                <input type="hidden" name="date_to" value="<?= h($date_to) ?>">
                <input type="hidden" name="keyword" value="<?= h($keyword) ?>">
                <input type="hidden" name="acc" value="<?= h($selected_acc) ?>">
                <input type="hidden" name="page" value="<?= (int)$page ?>">
                <input type="hidden" name="per_page" value="<?= (int)$per_page ?>">
                <div class="toolbar">
                    <button type="submit" class="danger" id="delete_btn" disabled>선택 삭제</button>
                    <span class="meta" id="selected_count">0건 선택</span>
                </div>
            <table>
                <thead>
                    <tr>
                        <th class="chk"><input type="checkbox" id="check_all" title="현재 페이지 전체 선택"></th>
                        <th style="width:70px">ID</th>
                        <th style="width:120px">전화번호</th>
                        <th>SMS 내용</th>
                        <th style="width:80px">status</th>
                        <th style="width:80px">가공</th>
                        <th style="width:110px">page</th>
                        <th style="width:160px">수신일시</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($list_rows as $row) { ?>
                    <tr>
                        <td class="chk"><input type="checkbox" name="ids[]" value="<?= (int)$row['id'] ?>" class="row-check"></td>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= h($row['tel']) ?></td>
                        <td class="sms"><?= h($row['sms']) ?></td>
                        <td><?= h($row['status']) ?></td>
                        <td><?= h(isset($row['가공']) ? $row['가공'] : '') ?></td>
                        <td><?= h($row['page']) ?></td>
                        <td class="meta"><?= h($row['regdate']) ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
            </form>

            <div class="pager">
                <?php
                $start = max(1, $page - 4);
                $end = min($total_pages, $page + 4);
                if ($page > 1) {
                    echo '<a href="'.h(qs(array('page' => $page - 1))).'">이전</a>';
                }
                for ($i = $start; $i <= $end; $i++) {
                    if ($i === $page) {
                        echo '<span class="on">'.$i.'</span>';
                    } else {
                        echo '<a href="'.h(qs(array('page' => $i))).'">'.$i.'</a>';
                    }
                }
                if ($page < $total_pages) {
                    echo '<a href="'.h(qs(array('page' => $page + 1))).'">다음</a>';
                }
                ?>
                <span class="meta"><?= number_format($page) ?> / <?= number_format($total_pages) ?> 페이지</span>
            </div>
        <?php } ?>
    <?php } ?>
</div>
<script>
function updateDeleteState() {
    var boxes = document.querySelectorAll('.row-check');
    var checked = document.querySelectorAll('.row-check:checked');
    var btn = document.getElementById('delete_btn');
    var count = document.getElementById('selected_count');
    var all = document.getElementById('check_all');
    if (!btn) return;
    btn.disabled = checked.length === 0;
    if (count) count.textContent = checked.length + '건 선택';
    if (all) all.checked = boxes.length > 0 && checked.length === boxes.length;
}
function confirmDelete() {
    var n = document.querySelectorAll('.row-check:checked').length;
    if (n === 0) return false;
    return confirm('선택한 ' + n + '건을 삭제할까요?');
}
document.addEventListener('change', function (e) {
    if (e.target && e.target.id === 'check_all') {
        document.querySelectorAll('.row-check').forEach(function (el) {
            el.checked = e.target.checked;
        });
        updateDeleteState();
    } else if (e.target && e.target.classList.contains('row-check')) {
        updateDeleteState();
    }
});
</script>
</body>
</html>
