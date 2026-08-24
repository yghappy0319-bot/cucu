<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/_coupon.php';

$cp_idx = max(0, (int) ($_GET['cp_idx'] ?? 0));
$title     = '쿠폰 회원별 내역';
$ad_topbar = $ad;
$ad_menu   = 'coupons';

$ready = coupon_table_ready() && member_coupon_table_ready();
$coupon = null;
$rows   = [];
$st     = isset($_GET['st']) ? trim((string) $_GET['st']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 30;
$offset  = ($page_no - 1) * $per;
$total   = 0;
$total_page = 1;

$st_map = [
    ''  => '상태 전체',
    '0' => '사용가능',
    '1' => '사용완료',
    '9' => '취소',
];

if ($ready && $cp_idx > 0) {
    $coupon = db_assoc(db_query("SELECT * FROM tb_coupon WHERE cp_idx = {$cp_idx} LIMIT 1"));
}

if ($coupon) {
    $where = ["mc.cp_idx = {$cp_idx}"];
    if ($st === '0' || $st === '1' || $st === '9') {
        $where[] = 'mc.mc_status = ' . (int) $st;
    }
    $where_sql = implode(' AND ', $where);
    $total = (int) db_result("SELECT COUNT(*) FROM tb_member_coupon mc WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT mc.*, m.mb_id, m.mb_nick
        FROM tb_member_coupon mc
        LEFT JOIN tb_member m ON m.mb_idx = mc.mb_idx
        WHERE {$where_sql}
        ORDER BY mc.mc_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

include __DIR__ . '/include/admin_header.php';
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">쿠폰 테이블이 없습니다.</div>
    <?php elseif (!$coupon): ?>
        <div class="ad-alert ad-alert--error">쿠폰을 찾을 수 없습니다.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">쿠폰 회원별 내역 · #<?php echo (int) $cp_idx; ?></h1>
            <p class="ad-p">
                <strong><?php echo htmlspecialchars((string) $coupon['cp_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                · <?php echo htmlspecialchars(coupon_format_discount($coupon), ENT_QUOTES, 'UTF-8'); ?>
                · <?php echo htmlspecialchars((string) $coupon['cp_valid_from'], ENT_QUOTES, 'UTF-8'); ?>
                ~ <?php echo htmlspecialchars((string) $coupon['cp_valid_until'], ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <p class="ad-p" style="margin-top:0;">
                <a href="/admin/coupons.php">← 발행 목록</a>
            </p>

            <form class="ad-toolbar ad-form" method="get" action="/admin/coupon_members.php">
                <input type="hidden" name="cp_idx" value="<?php echo (int) $cp_idx; ?>">
                <div class="ad-field">
                    <label for="f_st">상태</label>
                    <select id="f_st" name="st">
                        <?php foreach ($st_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $st === $k ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">조회</button>
                </div>
            </form>

            <p class="ad-p" style="margin-top:0;">총 <?php echo number_format($total); ?>건</p>

            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>보유#</th>
                        <th>회원</th>
                        <th>상태</th>
                        <th>결제#</th>
                        <th class="ad-nowrap">사용일</th>
                        <th class="ad-nowrap">발행일</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="ad-muted">내역이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $mc_st = (int) ($r['mc_status'] ?? 0);
                        $st_lab = $mc_st === MEMBER_COUPON_STATUS_USED ? '사용완료'
                            : ($mc_st === MEMBER_COUPON_STATUS_CANCELLED ? '취소' : '사용가능');
                        ?>
                        <tr>
                            <td>#<?php echo (int) ($r['mc_idx'] ?? 0); ?></td>
                            <td>
                                <a href="/admin/member_edit.php?idx=<?php echo (int) ($r['mb_idx'] ?? 0); ?>">
                                    <?php echo htmlspecialchars((string) (($r['mb_nick'] ?? '') ?: ($r['mb_id'] ?? '') ?: ('#' . ($r['mb_idx'] ?? 0))), ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($st_lab, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if ((int) ($r['mc_pay_idx'] ?? 0) > 0): ?>
                                    <a href="/admin/trade_payments.php?q=<?php echo (int) $r['mc_pay_idx']; ?>">#<?php echo (int) $r['mc_pay_idx']; ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="ad-nowrap"><?php echo htmlspecialchars((string) ($r['mc_used_at'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-nowrap"><?php echo htmlspecialchars((string) ($r['mc_issued_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
