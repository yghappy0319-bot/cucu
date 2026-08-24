<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../auction/lib/_member_auction_ban.php';
require_once __DIR__ . '/../lib/_member_cash.php';

$title     = '회원관리';
$ad_topbar = $ad;
$ad_menu   = 'members';
include __DIR__ . '/include/admin_header.php';

$q        = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$st_f     = isset($_GET['st']) ? $_GET['st'] : '';
$page_no  = max(1, (int) ($_GET['p'] ?? 1));
$per      = 20;
$offset   = ($page_no - 1) * $per;

$ready = db_table_exists('tb_member');
$auction_ban_ready = member_auction_ban_column_ready();
$cash_ready = member_cash_column_ready();
$point_log_ready = db_table_exists('tb_point_log');
$rows  = [];
$total = 0;
$total_page = 1;

$status_labels = [
    '0' => '휴면',
    '1' => '정상',
    '2' => '정지',
    '3' => '탈퇴',
];

if ($ready) {
    $where = ['1=1'];
    if ($st_f !== '' && array_key_exists((string) $st_f, $status_labels)) {
        $where[] = 'mb_status = ' . (int) $st_f;
    }
    if ($q !== '') {
        $e = db_escape($q);
        $where[] = "(mb_id LIKE '%{$e}%' OR mb_nick LIKE '%{$e}%' OR mb_email LIKE '%{$e}%' OR mb_name LIKE '%{$e}%')";
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("SELECT COUNT(*) FROM tb_member WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $ban_select = $auction_ban_ready ? ', mb_auction_banned' : '';
    $cash_select = $cash_ready ? ', mb_cash' : '';
    $rs = db_query("
        SELECT mb_idx, mb_id, mb_nick, mb_email, mb_name, mb_level, mb_status, mb_point, mb_created_at, mb_last_login_at{$ban_select}{$cash_select}
        FROM tb_member
        WHERE {$where_sql}
        ORDER BY mb_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

$qs = static function (array $overrides = []) use ($q, $st_f, $page_no) {
    $a = array_merge(['q' => $q, 'st' => $st_f, 'p' => $page_no], $overrides);
    $a = array_filter($a, static fn ($v) => $v !== '' && $v !== null);
    return $a ? '?' . http_build_query($a) : '';
};

$return_path = '/admin/members.php' . $qs([]);
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_member 테이블이 없습니다.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">회원 목록</h1>
            <p class="ad-p" style="margin-top:0;">
                목록에서 회원별 <strong>지급</strong>으로 포인트·캐시를 조정할 수 있습니다. (양수 지급, 음수 차감)
                <?php if ($point_log_ready): ?>
                    · <a href="/admin/points.php">포인트 내역</a>
                <?php endif; ?>
                <?php if ($cash_ready && member_cash_log_table_ready()): ?>
                    · <a href="/admin/cash_logs.php">캐시 내역</a>
                <?php endif; ?>
            </p>
            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label for="f_st">상태</label>
                    <select id="f_st" name="st">
                        <option value=""<?php echo $st_f === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($status_labels as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo (string) $st_f === (string) $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field ad-field--grow">
                    <label for="f_q">검색 (아이디·닉·이메일·이름)</label>
                    <input type="text" id="f_q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="검색어">
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">검색</button>
                </div>
            </form>

            <p class="ad-p" style="margin-top:0;">총 <?php echo number_format($total); ?>명 · <?php echo $page_no; ?> / <?php echo $total_page; ?> 페이지</p>

            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>아이디</th>
                        <th>닉네임</th>
                        <th>이메일</th>
                        <th class="ad-num">등급</th>
                        <th>상태</th>
                        <?php if ($auction_ban_ready): ?><th>경매</th><?php endif; ?>
                        <th class="ad-num">포인트</th>
                        <?php if ($cash_ready): ?><th class="ad-num">캐시</th><?php endif; ?>
                        <th class="ad-nowrap">가입일</th>
                        <th>지급</th>
                        <th>관리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo (int) $r['mb_idx']; ?></td>
                            <td>
                                <a class="ad-table__link" href="/admin/member_edit.php?idx=<?php echo (int) $r['mb_idx']; ?>"><?php echo htmlspecialchars($r['mb_id'], ENT_QUOTES, 'UTF-8'); ?></a>
                            </td>
                            <td><?php echo htmlspecialchars($r['mb_nick'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-title-cell" title="<?php echo htmlspecialchars($r['mb_email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($r['mb_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num"><?php echo (int) $r['mb_level']; ?></td>
                            <td><?php echo htmlspecialchars($status_labels[(string) $r['mb_status']] ?? $r['mb_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php if ($auction_ban_ready): ?>
                            <td>
                                <?php if ((int) ($r['mb_auction_banned'] ?? 0) === 1): ?>
                                    <span style="color:#b45309;font-weight:700;">제한</span>
                                <?php else: ?>
                                    <span class="ad-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td class="ad-num"><?php echo number_format((int) $r['mb_point']); ?></td>
                            <?php if ($cash_ready): ?>
                            <td class="ad-num">₩<?php echo number_format((int) ($r['mb_cash'] ?? 0)); ?></td>
                            <?php endif; ?>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr($r['mb_created_at'], 0, 10), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-grant-cell">
                                <details class="ad-grant-details">
                                    <summary>지급</summary>
                                    <div class="ad-grant-forms">
                                        <?php if ($point_log_ready): ?>
                                        <form class="ad-grant-form" action="/proc/admin_member_point_proc.php" method="post"
                                              onsubmit="return confirm('포인트를 반영할까요?');">
                                            <input type="hidden" name="mb_idx" value="<?php echo (int) $r['mb_idx']; ?>">
                                            <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_path, ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="ad-grant-form__label">포인트</span>
                                            <input type="number" name="delta" required placeholder="+/-">
                                            <input type="text" name="memo" maxlength="200" placeholder="메모">
                                            <button type="submit" class="ad-btn ad-btn--sm">적용</button>
                                        </form>
                                        <?php endif; ?>
                                        <?php if ($cash_ready && member_cash_log_table_ready()): ?>
                                        <form class="ad-grant-form" action="/proc/admin_member_cash_proc.php" method="post"
                                              onsubmit="return confirm('캐시를 반영할까요?');">
                                            <input type="hidden" name="mb_idx" value="<?php echo (int) $r['mb_idx']; ?>">
                                            <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_path, ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="ad-grant-form__label">캐시(원)</span>
                                            <input type="number" name="delta" required placeholder="+/-">
                                            <input type="text" name="memo" maxlength="200" placeholder="메모">
                                            <button type="submit" class="ad-btn ad-btn--sm">적용</button>
                                        </form>
                                        <?php endif; ?>
                                        <?php if (!$point_log_ready && (!$cash_ready || !member_cash_log_table_ready())): ?>
                                            <span class="ad-muted">지급 DB 미설치</span>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </td>
                            <td>
                                <form class="ad-form ad-form--inline" action="/proc/admin_member_proc.php" method="post">
                                    <input type="hidden" name="mb_idx" value="<?php echo (int) $r['mb_idx']; ?>">
                                    <select name="mb_status" style="width:auto;min-width:88px;height:36px;font-size:13px;">
                                        <?php foreach ($status_labels as $k => $lab): ?>
                                            <option value="<?php echo (int) $k; ?>"<?php echo (int) $r['mb_status'] === (int) $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" name="mb_level" min="1" max="9" value="<?php echo (int) $r['mb_level']; ?>" style="width:64px;height:36px;padding:0 8px;" title="등급">
                                    <button type="submit" class="ad-btn">저장</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <div class="ad-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="/admin/members.php<?php echo htmlspecialchars($qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php
                    $from = max(1, $page_no - 3);
                    $to   = min($total_page, $page_no + 3);
                    for ($i = $from; $i <= $to; $i++):
                        if ($i === $page_no):
                            ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/admin/members.php<?php echo htmlspecialchars($qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php
                        endif;
                    endfor;
                    ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/admin/members.php<?php echo htmlspecialchars($qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
