<?php
require_once __DIR__ . '/include/admin_init.php';

$title     = '기록용게시판';
$ad_topbar = $ad;
$ad_menu   = 'record_board';
include __DIR__ . '/include/admin_header.php';

$q          = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$reg_date_in = isset($_GET['reg_date']) ? trim((string) $_GET['reg_date']) : '';

$rb_valid_ymd = static function (string $s): ?string {
    if ($s === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
};

$reg_date = $rb_valid_ymd($reg_date_in);
if ($reg_date === null) {
    $reg_date = date('Y-m-d');
}

$page_no   = max(1, (int) ($_GET['p'] ?? 1));
$per       = 30;
$offset    = ($page_no - 1) * $per;
$edit_idx  = max(0, (int) ($_GET['edit'] ?? 0));

/** @return int[] */
$rb_decode_sells = static function (?string $json): array {
    $a = json_decode((string) $json, true);
    if (!is_array($a)) {
        return [];
    }
    $out = [];
    foreach ($a as $x) {
        $out[] = max(0, (int) $x);
    }
    return $out;
};

$ready       = db_table_exists('tb_admin_record_board');
$rows        = [];
$total       = 0;
$total_page  = 1;
$edit_row    = null;
$edit_sells  = [];
$list_totals = ['sale_slots' => 0, 'buy' => 0, 'net' => 0];
$rb_total_all  = 0;
$rb_day_counts = [];

if ($ready) {
    if ($edit_idx > 0) {
        $edit_row = db_assoc(db_query("
            SELECT rb.*
            FROM tb_admin_record_board rb
            WHERE rb.rb_idx = {$edit_idx} LIMIT 1
        "));
        if (!$edit_row) {
            $edit_idx = 0;
        } else {
            $edit_sells = $rb_decode_sells($edit_row['rb_sells'] ?? '');
            $ec         = max(1, (int) ($edit_row['rb_count'] ?? count($edit_sells)));
            if (count($edit_sells) < $ec) {
                $edit_sells = array_pad($edit_sells, $ec, 0);
            }
            if (count($edit_sells) > $ec) {
                $edit_sells = array_slice($edit_sells, 0, $ec);
            }
        }
    }

    $rb_total_all = (int) db_result('SELECT COUNT(*) FROM tb_admin_record_board');
    $rs_days      = db_query("
        SELECT DATE(rb_created_at) AS d, COUNT(*) AS c
        FROM tb_admin_record_board
        GROUP BY DATE(rb_created_at)
        ORDER BY d DESC
        LIMIT 90
    ");
    while ($dr = db_assoc($rs_days)) {
        $rb_day_counts[] = ['d' => (string) $dr['d'], 'c' => (int) $dr['c']];
    }

    $where = ['1=1'];
    if ($q !== '') {
        $e = db_escape($q);
        $where[] = "(rb.rb_title LIKE '%{$e}%' OR rb.rb_memo LIKE '%{$e}%')";
    }
    $reg_end = (new DateTime($reg_date . ' 00:00:00'))->modify('+1 day')->format('Y-m-d');
    $where[] = "rb.rb_created_at >= '" . db_escape($reg_date . ' 00:00:00') . "'";
    $where[] = "rb.rb_created_at < '" . db_escape($reg_end . ' 00:00:00') . "'";
    $where_sql = implode(' AND ', $where);

    $rs_sum = db_query("SELECT rb_buy, rb_sells, rb_count FROM tb_admin_record_board rb WHERE {$where_sql}");
    while ($tr = db_assoc($rs_sum)) {
        $list_totals['sale_slots'] += max(0, (int) ($tr['rb_count'] ?? 0));
        $list_totals['buy'] += (int) $tr['rb_buy'];
        $sells_t = $rb_decode_sells($tr['rb_sells'] ?? '');
        $list_totals['net'] += array_sum($sells_t) - (int) $tr['rb_buy'];
    }

    $total      = (int) db_result("SELECT COUNT(*) FROM tb_admin_record_board rb WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT rb.*
        FROM tb_admin_record_board rb
        WHERE {$where_sql}
        ORDER BY rb.rb_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

$reg_date_qs = $reg_date;

$build_qs = static function (array $o) use ($q, $page_no, $edit_idx, $reg_date_qs) {
    $base = ['q' => $q, 'p' => $page_no, 'reg_date' => $reg_date_qs];
    if ($edit_idx > 0) {
        $base['edit'] = $edit_idx;
    }
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    if (($base['q'] ?? '') === '') {
        unset($base['q']);
    }
    if ((int) ($base['p'] ?? 1) <= 1) {
        unset($base['p']);
    }
    if ((int) ($base['edit'] ?? 0) <= 0) {
        unset($base['edit']);
    }
    if (($base['reg_date'] ?? '') === '') {
        unset($base['reg_date']);
    }
    return $base ? '?' . http_build_query($base) : '';
};

$edit_sells_json = $edit_row ? htmlspecialchars(json_encode($edit_sells, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') : '';
$edit_count      = $edit_row ? max(1, (int) ($edit_row['rb_count'] ?? count($edit_sells))) : 1;
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_admin_record_board 테이블이 없습니다. <code>sql/tb_admin_record_board.sql</code>을 DB에 실행해 주세요. (기존 단일 판매가 테이블이면 <code>sql/migrate_tb_admin_record_board_multisell.sql</code>도 실행하세요.)</div>
    <?php else: ?>
        <div class="ad-card" style="margin-bottom:1.25rem;">
            <?php if ($edit_row): ?>
                <h2 class="ad-title" style="font-size:1.1rem;">기록 수정</h2>
                <p class="ad-p">#<?php echo (int) $edit_row['rb_idx']; ?> · 등록 <?php echo htmlspecialchars(substr($edit_row['rb_created_at'], 0, 16), ENT_QUOTES, 'UTF-8'); ?></p>
                <form class="ad-form js-record-form" action="/proc/admin_record_board_proc.php" method="post" id="form-record-edit"
                      data-initial-count="<?php echo (int) $edit_count; ?>"
                      data-initial-sells="<?php echo $edit_sells_json; ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="rb_idx" value="<?php echo (int) $edit_row['rb_idx']; ?>">
                    <div class="ad-field">
                        <label for="e_title">제목</label>
                        <input type="text" id="e_title" name="rb_title" maxlength="200" required
                               value="<?php echo htmlspecialchars($edit_row['rb_title'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="ad-field">
                        <label for="e_memo">메모</label>
                        <textarea id="e_memo" name="rb_memo" rows="4" placeholder="비고·상세 메모"><?php echo htmlspecialchars((string) $edit_row['rb_memo'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="ad-split ad-split--2">
                        <div class="ad-field">
                            <label for="e_count">판매 건수 (판매가 칸 개수)</label>
                            <input type="number" id="e_count" name="rb_count" min="1" max="20" step="1" required class="js-record-count"
                                   value="<?php echo (int) $edit_count; ?>">
                        </div>
                        <div class="ad-field">
                            <label for="e_buy">총 구매가 (원)</label>
                            <input type="number" id="e_buy" name="rb_buy" min="0" step="1" required class="js-record-buy"
                                   value="<?php echo (int) $edit_row['rb_buy']; ?>">
                        </div>
                    </div>
                    <p class="ad-p ad-muted" style="margin:0 0 0.5rem;">예: 2건 구매에 총 <strong>229,125원</strong> 쓰셨다면 총 구매가에 그대로 넣고, 각각 <strong>190,000원</strong>씩 판매가를 넣으면 차익은 <strong>(190,000×2) − 229,125</strong>으로 계산됩니다.</p>
                    <div class="js-sell-rows"></div>
                    <div class="js-profit-summary ad-card" style="margin-top:1rem;padding:1rem;background:rgba(0,0,0,.03);border-radius:8px;">
                        <p class="ad-p" style="margin:0 0 0.35rem;">판매가 합계: <strong class="js-sum-sell">0</strong>원</p>
                        <p class="ad-p ad-muted" style="margin:0 0 0.35rem;">총 구매가: <span class="js-sum-buy">0</span>원</p>
                        <p class="ad-p" style="margin:0;">차익 (판매 합계 − 총 구매가): <strong class="js-net-profit" style="font-size:1.05rem;">0원</strong></p>
                    </div>
                    <div class="ad-actions" style="margin-top:1rem;">
                        <button type="submit" class="ad-btn ad-btn--primary">저장</button>
                        <a href="/admin/record_board.php<?php echo htmlspecialchars($build_qs(['edit' => 0]), ENT_QUOTES, 'UTF-8'); ?>" class="ad-btn ad-btn--ghost">취소</a>
                    </div>
                </form>
            <?php else: ?>
                <h2 class="ad-title" style="font-size:1.1rem;">새 기록</h2>
                <p class="ad-p">관리자 전용입니다. <strong>총 구매가</strong>는 묶음 구매 비용 한 번에, 아래에는 건별 <strong>판매가</strong>를 넣습니다. 차익은 <strong>판매가 합계 − 총 구매가</strong>입니다.</p>
                <?php if ($reg_date !== date('Y-m-d')): ?>
                    <p class="ad-p ad-muted" style="margin:0 0 0.75rem;">지금 목록에서 고른 등록일(<strong><?php echo htmlspecialchars($reg_date, ENT_QUOTES, 'UTF-8'); ?></strong>)로 저장됩니다. (오늘 시각이 붙습니다.)</p>
                <?php endif; ?>
                <form class="ad-form js-record-form" action="/proc/admin_record_board_proc.php" method="post" id="form-record-add"
                      data-initial-count="1"
                      data-initial-sells="[0]">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="reg_date" value="<?php echo htmlspecialchars($reg_date, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="ad-field">
                        <label for="rb_title">제목</label>
                        <input type="text" id="rb_title" name="rb_title" maxlength="200" required placeholder="예: OO카드 1박스">
                    </div>
                    <div class="ad-field">
                        <label for="rb_memo">메모</label>
                        <textarea id="rb_memo" name="rb_memo" rows="4" placeholder="비고·상세 메모"></textarea>
                    </div>
                    <div class="ad-split ad-split--2">
                        <div class="ad-field">
                            <label for="rb_count">판매 건수 (판매가 칸 개수)</label>
                            <input type="number" id="rb_count" name="rb_count" min="1" max="20" step="1" required value="1" class="js-record-count">
                        </div>
                        <div class="ad-field">
                            <label for="rb_buy">총 구매가 (원)</label>
                            <input type="number" id="rb_buy" name="rb_buy" min="0" step="1" required value="0" class="js-record-buy">
                        </div>
                    </div>
                    <p class="ad-p ad-muted" style="margin:0 0 0.5rem;">예: 2건 구매에 총 <strong>229,125원</strong> 쓰셨다면 총 구매가에 그대로 넣고, 각각 <strong>190,000원</strong>씩 판매가를 넣으면 차익은 <strong>(190,000×2) − 229,125</strong>으로 계산됩니다.</p>
                    <div class="js-sell-rows"></div>
                    <div class="js-profit-summary ad-card" style="margin-top:1rem;padding:1rem;background:rgba(0,0,0,.03);border-radius:8px;">
                        <p class="ad-p" style="margin:0 0 0.35rem;">판매가 합계: <strong class="js-sum-sell">0</strong>원</p>
                        <p class="ad-p ad-muted" style="margin:0 0 0.35rem;">총 구매가: <span class="js-sum-buy">0</span>원</p>
                        <p class="ad-p" style="margin:0;">차익 (판매 합계 − 총 구매가): <strong class="js-net-profit" style="font-size:1.05rem;">0원</strong></p>
                    </div>
                    <div class="ad-actions" style="margin-top:1rem;">
                        <button type="submit" class="ad-btn ad-btn--primary">등록</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="ad-card">
            <h1 class="ad-title">기록 목록</h1>
            <div style="margin:0 0 1.25rem;padding:1rem;background:rgba(0,0,0,.04);border-radius:8px;">
                <h2 class="ad-title" style="font-size:1rem;margin:0 0 0.5rem;">일별 등록 건수</h2>
                <p class="ad-p" style="margin:0 0 0.75rem;">전체 누적 <strong><?php echo number_format($rb_total_all); ?></strong>건입니다.
                    <?php if ($q !== ''): ?>
                        <span class="ad-muted">(아래 표는 검색어와 무관하게, 전체 기준 집계입니다.)</span>
                    <?php else: ?>
                        <span class="ad-muted">날짜를 누르면 그날 목록으로 이동하고, 상단 새 기록 폼으로 넣은 글은 그 날짜로 저장됩니다.</span>
                    <?php endif; ?>
                </p>
                <?php if ($rb_day_counts === []): ?>
                    <p class="ad-p ad-muted" style="margin:0;">아직 등록된 기록이 없습니다.</p>
                <?php else: ?>
                    <p class="ad-p ad-muted" style="margin:0 0 0.5rem;font-size:.9rem;">등록이 있었던 날짜만 최대 90일치까지 표시합니다.</p>
                    <div class="ad-table-wrap" style="max-height:14rem;overflow:auto;">
                        <table class="ad-table" style="margin:0;">
                            <thead>
                            <tr>
                                <th>등록일</th>
                                <th class="ad-num">건수</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rb_day_counts as $rb_dc):
                                $is_here = ($rb_dc['d'] === $reg_date);
                                ?>
                                <tr<?php echo $is_here ? ' style="background:rgba(59,130,246,.08);"' : ''; ?>>
                                    <td>
                                        <a href="/admin/record_board.php<?php echo htmlspecialchars($build_qs(['reg_date' => $rb_dc['d'], 'p' => 1, 'edit' => 0]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($rb_dc['d'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    </td>
                                    <td class="ad-num"><?php echo number_format($rb_dc['c']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <form id="form-record-list-filter" class="ad-toolbar ad-form" method="get" action="">
                <?php if ($edit_idx > 0): ?>
                    <input type="hidden" name="edit" value="<?php echo (int) $edit_idx; ?>">
                <?php endif; ?>
                <div class="ad-field ad-field--grow">
                    <label for="f_q">제목·메모 검색</label>
                    <input type="text" id="f_q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="검색어">
                </div>
                <div class="ad-field">
                    <label for="f_reg_date">등록일</label>
                    <input type="date" id="f_reg_date" name="reg_date"
                           value="<?php echo htmlspecialchars($reg_date, ENT_QUOTES, 'UTF-8'); ?>"
                           onchange="this.form.submit();">
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">검색</button>
                </div>
            </form>
            <p class="ad-p" style="margin-top:0;">총 <?php echo number_format($total); ?>건
                <span class="ad-muted">(<?php echo htmlspecialchars($reg_date, ENT_QUOTES, 'UTF-8'); ?> 등록분)</span>
            </p>
            <p class="ad-p ad-muted" style="margin-top:-0.35rem;">달력에서 등록일을 선택하면 바로 조회됩니다. 제목·메모는 검색 버튼을 눌러 주세요.</p>

            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>제목</th>
                        <th>메모</th>
                        <th class="ad-num">판매 건수</th>
                        <th class="ad-num">총 구매가</th>
                        <th>판매가·합계·차익</th>
                        <th class="ad-nowrap">등록일</th>
                        <th>처리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $buy        = (int) $r['rb_buy'];
                        $sells      = $rb_decode_sells($r['rb_sells'] ?? '');
                        $cnt_disp   = max(1, (int) ($r['rb_count'] ?? count($sells)));
                        $memo_short = mb_strimwidth((string) $r['rb_memo'], 0, 80, '…', 'UTF-8');
                        $sum_sell   = array_sum($sells);
                        $net        = $sum_sell - $buy;
                        $net_color  = $net >= 0 ? '#166534' : '#991b1b';
                        ?>
                        <tr>
                            <td><?php echo (int) $r['rb_idx']; ?></td>
                            <td class="ad-title-cell"><?php echo htmlspecialchars($r['rb_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-title-cell" title="<?php echo htmlspecialchars((string) $r['rb_memo'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($memo_short, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num"><?php echo (int) $cnt_disp; ?></td>
                            <td class="ad-num"><?php echo number_format($buy); ?></td>
                            <td class="ad-title-cell" style="line-height:1.5;">
                                <?php if ($sells === []): ?>
                                    <span class="ad-muted">—</span>
                                <?php endif; ?>
                                <?php foreach ($sells as $i => $sv): ?>
                                    <div>
                                        판매<?php echo (int) ($i + 1); ?>:
                                        <span class="ad-num"><?php echo number_format((int) $sv); ?></span>원
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($sells !== []): ?>
                                    <div class="ad-muted" style="margin-top:0.35rem;">
                                        판매 합계: <span class="ad-num"><?php echo number_format($sum_sell); ?></span>원
                                    </div>
                                    <div style="margin-top:0.25rem;">
                                        차익 <span class="ad-muted">(합계 − 총 구매)</span>:
                                        <span class="ad-num" style="color:<?php echo $net_color; ?>"><?php echo ($net >= 0 ? '' : '−') . number_format(abs($net)); ?></span>원
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr($r['rb_created_at'], 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <div style="display:flex;flex-wrap:wrap;gap:0.35rem;">
                                    <a class="ad-btn" href="/admin/record_board.php<?php echo htmlspecialchars($build_qs(['edit' => (int) $r['rb_idx']]), ENT_QUOTES, 'UTF-8'); ?>">수정</a>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_record_board_proc.php" method="post"
                                          onsubmit="return confirm('이 기록을 삭제할까요?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="rb_idx" value="<?php echo (int) $r['rb_idx']; ?>">
                                        <button type="submit" class="ad-btn">삭제</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="3"><strong>합계</strong> 
                        
                    </td>
                        <td class="ad-num"><strong><?php echo number_format($list_totals['sale_slots']); ?></strong></td>
                        <td class="ad-num"><strong><?php echo number_format($list_totals['buy']); ?></strong></td>
                        <td style="line-height:1.5;">
                            <span class="ad-muted">총 차익</span>
                            <strong class="ad-num" style="color:<?php echo $list_totals['net'] >= 0 ? '#166534' : '#991b1b'; ?>;font-size:1.05rem;">
                                <?php echo ($list_totals['net'] >= 0 ? '' : '−') . number_format(abs($list_totals['net'])); ?>
                            </strong>원
                        </td>
                        <td colspan="2"></td>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <div class="ad-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="/admin/record_board.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/admin/record_board.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/admin/record_board.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <script>
        (function () {
            var MAX = 20;
            function parseSellsAttr(s) {
                try {
                    var a = JSON.parse(s || '[]');
                    return Array.isArray(a) ? a.map(function (x) { return parseInt(x, 10) || 0; }) : [];
                } catch (e) {
                    return [];
                }
            }
            function clampCount(n) {
                n = parseInt(n, 10);
                if (isNaN(n) || n < 1) n = 1;
                if (n > MAX) n = MAX;
                return n;
            }
            function fmtWon(n) {
                return (n < 0 ? '−' : '') + Math.abs(n).toLocaleString('ko-KR') + '원';
            }
            function renderRows(form) {
                var wrap = form.querySelector('.js-sell-rows');
                var cntIn = form.querySelector('.js-record-count');
                var buyIn = form.querySelector('.js-record-buy');
                if (!wrap || !cntIn || !buyIn) return;
                var n = clampCount(cntIn.value);
                cntIn.value = n;
                var prev = [];
                wrap.querySelectorAll('input[name="rb_sell[]"]').forEach(function (inp) {
                    prev.push(inp.value);
                });
                var init = (form._rbSellBackup != null)
                    ? form._rbSellBackup
                    : parseSellsAttr(form.getAttribute('data-initial-sells'));
                var vals = [];
                for (var i = 0; i < n; i++) {
                    if (prev[i] !== undefined && prev[i] !== '') vals[i] = prev[i];
                    else if (init[i] !== undefined) vals[i] = String(init[i]);
                    else vals[i] = '0';
                }
                wrap.innerHTML = '';
                for (var j = 0; j < n; j++) {
                    var row = document.createElement('div');
                    row.className = 'js-sell-row ad-field';
                    row.innerHTML =
                        '<label>판매가 ' + (j + 1) + ' (원)</label>' +
                        '<input type="number" name="rb_sell[]" min="0" step="1" required class="js-sell-in" value="' + String(vals[j]).replace(/"/g, '&quot;') + '">';
                    wrap.appendChild(row);
                }
                form._rbSellBackup = undefined;
                syncSummary(form);
                wrap.querySelectorAll('.js-sell-in').forEach(function (inp) {
                    inp.addEventListener('input', function () { syncSummary(form); });
                });
                buyIn.removeEventListener('input', form._rbBuySync);
                form._rbBuySync = function () { syncSummary(form); };
                buyIn.addEventListener('input', form._rbBuySync);
            }
            function syncSummary(form) {
                var buyIn = form.querySelector('.js-record-buy');
                var sumEl = form.querySelector('.js-sum-sell');
                var buyEl = form.querySelector('.js-sum-buy');
                var netEl = form.querySelector('.js-net-profit');
                if (!buyIn || !sumEl || !buyEl || !netEl) return;
                var b = parseInt(buyIn.value, 10) || 0;
                var sum = 0;
                form.querySelectorAll('.js-sell-in').forEach(function (inp) {
                    sum += parseInt(inp.value, 10) || 0;
                });
                var net = sum - b;
                sumEl.textContent = sum.toLocaleString('ko-KR');
                buyEl.textContent = b.toLocaleString('ko-KR');
                netEl.textContent = fmtWon(net);
                netEl.style.color = net >= 0 ? '#166534' : '#991b1b';
            }
            function bindForm(form) {
                if (!form) return;
                var cntIn = form.querySelector('.js-record-count');
                if (!cntIn) return;
                var ic = clampCount(form.getAttribute('data-initial-count') || '1');
                if (!form.id || form.id !== 'form-record-edit') {
                    cntIn.value = ic;
                }
                renderRows(form);
                cntIn.addEventListener('change', function () {
                    form._rbSellBackup = [];
                    form.querySelectorAll('input[name="rb_sell[]"]').forEach(function (i) {
                        form._rbSellBackup.push(i.value);
                    });
                    renderRows(form);
                });
            }
            bindForm(document.getElementById('form-record-add'));
            bindForm(document.getElementById('form-record-edit'));
        })();
        </script>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
