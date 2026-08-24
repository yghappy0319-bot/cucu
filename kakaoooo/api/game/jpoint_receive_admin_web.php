<?php
/**
 * 민생지원냥(jpoint) → 게임냥(point) 일괄 마이그레이션
 * URL: /api/game/jpoint_receive_admin_web.php
 * (code 인증 불필요 — DB만 연결되면 됨)
 */
require_once __DIR__ . '/../_bootstrap.php';

$scan_error = '';
$rows = array();
$stats = array('count' => 0, 'total_jpoint_fmt' => '0');
$done_msg = '';

function jpoint_migrate_db_ok() {
    if (function_exists('db_ensure_connection')) {
        return db_ensure_connection();
    }
    global $conn;
    return function_exists('db_query') && isset($conn) && ($conn instanceof mysqli);
}

function jpoint_migrate_db_error_msg() {
    if (!function_exists('db_query')) {
        return 'lib/_function.php 를 불러오지 못했습니다. (code 문제 아님 · 서버 경로 확인)';
    }
    if (function_exists('db_connection_error')) {
        return 'mysqli 연결 실패: ' . db_connection_error() . ' (code 문제 아님 · site_info.php·DB 설정 확인)';
    }
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return 'DB 연결 객체 없음 (code 문제 아님 · site_info.php 확인)';
    }
    return 'DB 연결 실패';
}

function jpoint_migrate_table_exists($table) {
    $table = addslashes($table);
    $rs = db_query("SHOW TABLES LIKE '{$table}'");
    return ($rs && db_fetch($rs));
}

function jpoint_migrate_fetch_list() {
    global $scan_error;

    if (!jpoint_migrate_db_ok()) {
        $scan_error = jpoint_migrate_db_error_msg();
        return array();
    }
    if (!jpoint_migrate_table_exists('tb_member')) {
        $scan_error = 'tb_member 테이블 없음';
        return array();
    }

    $col = db_select("SHOW COLUMNS FROM tb_member LIKE 'jpoint'");
    if (empty($col['Field'])) {
        $scan_error = 'jpoint 컬럼 없음 — tb_member에 jpoint INT 컬럼이 필요해요.';
        return array();
    }

    $has_mining = jpoint_migrate_table_exists('tb_member_mining');
    if ($has_mining) {
        $sql = "
            SELECT m.name, IFNULL(m.point,0) AS point, IFNULL(m.jpoint,0) AS jpoint,
                   IFNULL(mm.mining_pending,0) AS mining_pending
            FROM tb_member m
            LEFT JOIN tb_member_mining mm ON mm.nick = m.name
            WHERE IFNULL(m.jpoint,0) > 0
               OR IFNULL(mm.mining_pending,0) > 0
            ORDER BY m.jpoint DESC, m.name ASC
        ";
    } else {
        $sql = "
            SELECT m.name, IFNULL(m.point,0) AS point, IFNULL(m.jpoint,0) AS jpoint, 0 AS mining_pending
            FROM tb_member m
            WHERE IFNULL(m.jpoint,0) > 0
            ORDER BY m.jpoint DESC, m.name ASC
        ";
    }

    $rs = db_query($sql);
    if (!$rs) {
        global $conn;
        $scan_error = '조회 실패';
        if ($conn && function_exists('mysqli_error')) {
            $scan_error .= ': ' . mysqli_error($conn);
        }
        return array();
    }

    $out = array();
    while ($row = db_fetch($rs)) {
        $jpoint = (int)$row['jpoint'];
        $mining = (float)$row['mining_pending'];
        if ($jpoint <= 0 && $mining <= 0) {
            continue;
        }
        $out[] = array(
            'nick' => $row['name'],
            'jpoint' => $jpoint,
            'jpoint_fmt' => number_format($jpoint),
            'point_fmt' => number_format((int)$row['point']),
            'mining_fmt' => rtrim(rtrim(sprintf('%.6f', $mining), '0'), '.'),
            'can_migrate' => ($jpoint > 0),
        );
    }
    return $out;
}

function jpoint_migrate_run_all() {
    $list = jpoint_migrate_fetch_list();
    $count = 0;
    $total = 0;
    foreach ($list as $r) {
        if (!empty($r['can_migrate'])) {
            $count++;
            $total += (int)$r['jpoint'];
        }
    }
    if ($count <= 0) {
        return array('ok' => false, 'msg' => '마이그레이션할 민생지원냥이 없어요.');
    }
    db_query("UPDATE tb_member SET point = IFNULL(point,0) + IFNULL(jpoint,0), jpoint = 0 WHERE IFNULL(jpoint,0) > 0");
    return array(
        'ok' => true,
        'msg' => number_format($total) . '냥 · ' . $count . '명 마이그레이션 완료 (jpoint → point)',
        'count' => $count,
        'total' => $total,
    );
}

// POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'migrate_all') {
        echo json_encode(jpoint_migrate_run_all(), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'migrate_one') {
        $nick = isset($_POST['nick']) ? trim($_POST['nick']) : '';
        $nick_esc = addslashes($nick);
        $row = db_select("SELECT name, IFNULL(jpoint,0) AS jpoint FROM tb_member WHERE name='{$nick_esc}' LIMIT 1");
        if (empty($row['name']) || (int)$row['jpoint'] <= 0) {
            echo json_encode(array('ok' => false, 'msg' => '대상 없음'), JSON_UNESCAPED_UNICODE);
            exit;
        }
        $j = (int)$row['jpoint'];
        db_query("UPDATE tb_member SET point=IFNULL(point,0)+{$j}, jpoint=0 WHERE name='{$nick_esc}' LIMIT 1");
        echo json_encode(array('ok' => true, 'msg' => $nick . ' +' . number_format($j) . '냥'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(array('ok' => false, 'msg' => 'unknown'), JSON_UNESCAPED_UNICODE);
    exit;
}

// GET ?migrate=1 — 버튼 없이 바로 실행
if (isset($_GET['migrate']) && $_GET['migrate'] === '1') {
    $result = jpoint_migrate_run_all();
    $done_msg = !empty($result['ok']) ? $result['msg'] : ('실패: ' . $result['msg']);
}

$rows = jpoint_migrate_fetch_list();
$receive_count = 0;
$total_jpoint = 0;
foreach ($rows as $r) {
    if (!empty($r['can_migrate'])) {
        $receive_count++;
        $total_jpoint += (int)$r['jpoint'];
    }
}
$stats['count'] = $receive_count;
$stats['total_jpoint_fmt'] = number_format($total_jpoint);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>민생지원냥 → 게임냥 마이그레이션</title>
    <style>
        body { font-family: sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .wrap { max-width: 720px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        h1 { margin: 0 0 8px; font-size: 1.2rem; }
        .sub { color: #666; font-size: 0.9rem; margin-bottom: 16px; line-height: 1.5; }
        .box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 14px; margin-bottom: 16px; }
        .err { color: #c00; }
        .ok { color: #080; font-weight: bold; }
        .btn { background: #2563eb; color: #fff; border: 0; padding: 12px 18px; border-radius: 8px; font-weight: bold; cursor: pointer; margin-right: 8px; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn-sm { padding: 6px 10px; font-size: 0.85rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { border-bottom: 1px solid #eee; padding: 8px; text-align: left; }
        th { background: #f1f3f5; }
        td.num { text-align: right; }
        code { background: #eee; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>민생지원냥 → 게임냥 마이그레이션</h1>
    <p class="sub">
        <code>UPDATE tb_member SET point = point + jpoint, jpoint = 0</code><br>
        홍보방 <code>.수령</code> 과 동일한 처리입니다.
    </p>

    <?php if ($scan_error !== '') { ?>
    <div class="box err"><?php echo htmlspecialchars($scan_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>
    <?php if ($done_msg !== '') { ?>
    <div class="box ok"><?php echo htmlspecialchars($done_msg, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>

    <div class="box">
        <strong>수령 대상 <?php echo (int)$stats['count']; ?>명</strong>
        · 총 민생지원냥 <strong><?php echo htmlspecialchars($stats['total_jpoint_fmt'], ENT_QUOTES, 'UTF-8'); ?></strong>냥
    </div>

    <p>
        <button type="button" class="btn" id="btnAll"<?php if ($stats['count'] <= 0) echo ' disabled'; ?>>
            전원 마이그레이션 (<?php echo (int)$stats['count']; ?>명)
        </button>
        <a class="btn" href="?migrate=1" onclick="return confirm('전원 마이그레이션할까요?');" style="text-decoration:none;display:inline-block;<?php if ($stats['count']<=0) echo 'opacity:.5;pointer-events:none;'; ?>">
            GET 바로 실행
        </a>
        <button type="button" class="btn" onclick="location.reload()" style="background:#6c757d;">새로고침</button>
    </p>

    <?php if (empty($rows)) { ?>
    <p>표시할 회원이 없어요.</p>
    <?php } else { ?>
    <table>
        <tr>
            <th>닉</th>
            <th class="num">민생지원냥</th>
            <th class="num">현재 게임냥</th>
            <th class="num">채굴냥(참고)</th>
            <th></th>
        </tr>
        <?php foreach ($rows as $row) { ?>
        <tr data-nick="<?php echo htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8'); ?>">
            <td><?php echo htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="num"><?php echo htmlspecialchars($row['jpoint_fmt'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="num"><?php echo htmlspecialchars($row['point_fmt'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="num"><?php echo htmlspecialchars($row['mining_fmt'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php if (!empty($row['can_migrate'])) { ?><button type="button" class="btn btn-sm btn-one">1명</button><?php } else { echo '—'; } ?></td>
        </tr>
        <?php } ?>
    </table>
    <?php } ?>
</div>
<script>
(function(){
    function post(d, cb) {
        var x = new XMLHttpRequest();
        x.open('POST', location.pathname, true);
        x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        x.onload = function() {
            var j = null; try { j = JSON.parse(x.responseText); } catch(e) {}
            cb(j);
        };
        var s = []; for (var k in d) s.push(encodeURIComponent(k)+'='+encodeURIComponent(d[k]));
        x.send(s.join('&'));
    }
    var b = document.getElementById('btnAll');
    if (b) b.onclick = function() {
        if (!confirm('전원 마이그레이션?')) return;
        post({action:'migrate_all'}, function(j) {
            alert((j && j.msg) ? j.msg : '실패');
            if (j && j.ok) location.reload();
        });
    };
    var ones = document.getElementsByClassName('btn-one');
    for (var i=0; i<ones.length; i++) ones[i].onclick = function() {
        var nick = this.parentNode.parentNode.getAttribute('data-nick');
        if (!confirm(nick + ' 마이그레이션?')) return;
        post({action:'migrate_one', nick:nick}, function(j) {
            alert((j && j.msg) ? j.msg : '실패');
            if (j && j.ok) location.reload();
        });
    };
})();
</script>
</body>
</html>
