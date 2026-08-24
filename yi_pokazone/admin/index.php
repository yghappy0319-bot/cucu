<?php
require_once __DIR__ . '/include/admin_init.php';

$title     = '관리자';
$ad_topbar = $ad;
$ad_menu   = '';
include __DIR__ . '/include/admin_header.php';

$presence = site_presence_stats();

$stats = [
    'members'   => db_table_exists('tb_member') ? (int) db_result("SELECT COUNT(*) FROM tb_member WHERE mb_status = 1") : null,
    'community' => db_table_exists('tb_community') ? (int) db_result("SELECT COUNT(*) FROM tb_community WHERE co_status = 1") : null,
    'trade'     => db_table_exists('tb_trade') ? (int) db_result("SELECT COUNT(*) FROM tb_trade WHERE tr_status = 1") : null,
    'inquiry_new' => db_table_exists('tb_inquiry') ? (int) db_result("SELECT COUNT(*) FROM tb_inquiry WHERE iq_status IN (1,2)") : null,
];
?>

<div class="ad-page">
    <div class="ad-card">
        <h1 class="ad-title">관리자 홈</h1>
        <p class="ad-p">왼쪽 메뉴에서 게시판·회원·설정을 관리할 수 있습니다.</p>
        <div class="ad-actions">
            <a href="/" class="ad-btn ad-btn--ghost">사이트로</a>
        </div>

        <div class="ad-stat-grid">
            <div class="ad-stat ad-stat--accent">
                <div class="ad-stat__val" id="ad-online-total"><?php echo $presence['ready'] ? number_format($presence['total']) : '—'; ?></div>
                <div class="ad-stat__label">현재 접속<?php echo $presence['ready'] ? ' (최근 ' . (int) $presence['idle_minutes'] . '분)' : ''; ?></div>
                <?php if ($presence['ready']): ?>
                <div class="ad-stat__sub" id="ad-online-detail">로그인 <?php echo number_format($presence['members']); ?> · 비로그인 <?php echo number_format($presence['guests']); ?></div>
                <?php elseif (!site_presence_table_ready()): ?>
                <div class="ad-stat__sub">sql/migrate_tb_site_presence.sql 실행 필요</div>
                <?php endif; ?>
            </div>
            <div class="ad-stat">
                <div class="ad-stat__val"><?php echo $stats['members'] !== null ? number_format($stats['members']) : '—'; ?></div>
                <div class="ad-stat__label">정상 회원</div>
            </div>
            <div class="ad-stat">
                <div class="ad-stat__val"><?php echo $stats['community'] !== null ? number_format($stats['community']) : '—'; ?></div>
                <div class="ad-stat__label">커뮤니티 글</div>
            </div>
            <div class="ad-stat">
                <div class="ad-stat__val"><?php echo $stats['trade'] !== null ? number_format($stats['trade']) : '—'; ?></div>
                <div class="ad-stat__label">거래 글</div>
            </div>
            <div class="ad-stat">
                <div class="ad-stat__val"><?php echo $stats['inquiry_new'] !== null ? number_format($stats['inquiry_new']) : '—'; ?></div>
                <div class="ad-stat__label">미완료 문의</div>
            </div>
        </div>
    </div>
</div>

<?php if ($presence['ready']): ?>
<script>
(function () {
    var totalEl = document.getElementById('ad-online-total');
    var detailEl = document.getElementById('ad-online-detail');
    if (!totalEl) return;

    function refreshOnline() {
        fetch('/admin/online_stats_api.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok || !d.stats) return;
                totalEl.textContent = Number(d.stats.total || 0).toLocaleString('ko-KR');
                if (detailEl) {
                    detailEl.textContent = '로그인 ' + Number(d.stats.members || 0).toLocaleString('ko-KR')
                        + ' · 비로그인 ' + Number(d.stats.guests || 0).toLocaleString('ko-KR');
                }
            })
            .catch(function () {});
    }

    setInterval(refreshOnline, 60000);
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
