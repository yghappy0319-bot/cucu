<?php
require_once __DIR__ . '/../lib/_function.php';

$me = login_member();
if (!$me) {
    alert_goto('출석체크는 로그인 후 이용할 수 있습니다.', '/login.php?return=' . urlencode('/page/attendance.php'));
}

$mb_idx           = (int) $me['mb_idx'];
$attendance_point = attendance_reward_point();

$rs     = db_query("SELECT mb_point FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
$member = db_assoc($rs);
if (!$member) {
    alert_goto('회원 정보를 찾을 수 없습니다.', '/logout.php');
}

$today = date('Y-m-d');

$checked_today = false;
$rs            = db_query("
    SELECT att_idx FROM tb_attendance
    WHERE mb_idx = {$mb_idx} AND att_ymd = '" . db_escape($today) . "'
    LIMIT 1
");
if (db_assoc($rs)) {
    $checked_today = true;
}

$total_days = (int) db_result("SELECT COUNT(*) FROM tb_attendance WHERE mb_idx = {$mb_idx}");

$recent_ymd = [];
$rs         = db_query("
    SELECT att_ymd FROM tb_attendance
    WHERE mb_idx = {$mb_idx}
      AND att_ymd >= DATE_SUB('" . db_escape($today) . "', INTERVAL 13 DAY)
    ORDER BY att_ymd ASC
");
while ($r = db_assoc($rs)) {
    $recent_ymd[$r['att_ymd']] = true;
}

$calendar_days = [];
for ($i = 13; $i >= 0; $i--) {
    $d                     = date('Y-m-d', strtotime($today . " -{$i} days"));
    $calendar_days[]       = [
        'ymd'   => $d,
        'label' => date('n/j', strtotime($d)),
        'on'    => !empty($recent_ymd[$d]),
        'today' => ($d === $today),
    ];
}

$feed_rows = [];
$rs        = db_query("
    SELECT a.att_ymd, a.att_point, a.att_created_at, a.mb_idx, m.mb_nick
    FROM tb_attendance a
    LEFT JOIN tb_member m ON m.mb_idx = a.mb_idx
    WHERE a.att_ymd = '" . db_escape($today) . "'
    ORDER BY a.att_created_at DESC, a.att_idx DESC
");
while ($r = db_assoc($rs)) {
    $feed_rows[] = $r;
}
$feed_count = count($feed_rows);

$active_tab = trim((string) ($_GET['tab'] ?? 'feed'));
if (!in_array($active_tab, ['feed', 'points'], true)) {
    $active_tab = 'feed';
}

$page_num   = max(1, (int) ($_GET['p'] ?? 1));
$per_page   = 15;
$offset     = ($page_num - 1) * $per_page;
$log_total  = (int) db_result("SELECT COUNT(*) FROM tb_point_log WHERE mb_idx = {$mb_idx}");
$total_page = max(1, (int) ceil($log_total / $per_page));

$point_rows = [];
$rs         = db_query("
    SELECT pl_change, pl_balance, pl_type, pl_memo, pl_created_at
    FROM tb_point_log
    WHERE mb_idx = {$mb_idx}
    ORDER BY pl_idx DESC
    LIMIT {$per_page} OFFSET {$offset}
");
while ($r = db_assoc($rs)) {
    $point_rows[] = $r;
}

$type_labels = [
    'attendance'      => '출석',
    'signup'          => '가입',
    'community_post'  => '커뮤니티',
    'trade_post'      => '거래',
    'auction_del'     => '경매삭제',
    'card_buy'        => '카드시세',
    'seller_review'   => '판매후기',
    'trade_comment'   => '거래댓글',
    'trade_bump'      => '끌올',
    'admin'           => '관리',
    'etc'             => '기타',
];

$page  = 'attendance';
$title = '출석체크';
$meta_description = '매일 출석하고 포인트를 받아 보세요. Pokazone 출석체크, 일일 출석 현황과 포인트 내역을 확인할 수 있습니다.';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '출석체크', 'url' => '/page/attendance.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="attendance-page community">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">출석체크</h1>
                <p class="board-desc">매일 한 번 출석하면 <strong><?php echo number_format($attendance_point); ?>P</strong>가 적립됩니다. (서버 기준 날짜: <?php echo htmlspecialchars($today); ?>)</p>
            </div>
            <a href="/page/mypage.php" class="btn btn-outline btn-sm">마이페이지</a>
        </div>

        <div class="attendance-summary">
            <div class="attendance-stat-card">
                <span class="attendance-stat-label">보유 포인트</span>
                <strong class="attendance-stat-value"><?php echo number_format((int) $member['mb_point']); ?> P</strong>
            </div>
            <div class="attendance-stat-card">
                <span class="attendance-stat-label">누적 출석일</span>
                <strong class="attendance-stat-value"><?php echo number_format($total_days); ?>일</strong>
            </div>
            <div class="attendance-stat-card">
                <span class="attendance-stat-label">오늘 출석</span>
                <strong class="attendance-stat-value <?php echo $checked_today ? 'is-done' : ''; ?>">
                    <?php echo $checked_today ? '완료' : '대기'; ?>
                </strong>
            </div>
        </div>

        <div class="attendance-calendar attendance-card">
            <h2 class="attendance-subtitle">최근 14일 출석</h2>
            <div class="attendance-dots" role="list">
                <?php foreach ($calendar_days as $d): ?>
                    <div class="attendance-dot-wrap" role="listitem" title="<?php echo htmlspecialchars($d['ymd']); ?>">
                        <span class="attendance-dot <?php echo $d['on'] ? 'is-on' : ''; ?> <?php echo $d['today'] ? 'is-today' : ''; ?>"></span>
                        <span class="attendance-dot-label"><?php echo htmlspecialchars($d['label']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="attendance-check attendance-card">
            <?php if ($checked_today): ?>
                <p class="attendance-msg success">오늘 출석을 완료했습니다. 내일 또 만나요!</p>
                <button type="button" class="btn btn-outline" disabled>오늘 출석 완료</button>
            <?php else: ?>
                <p class="attendance-msg">아래 버튼을 눌러 오늘의 출석을 완료해 주세요.</p>
                <form method="post" action="/proc/attendance_proc.php">
                    <button type="submit" class="btn btn-primary attendance-btn-check">출석하기 (+<?php echo number_format($attendance_point); ?>P)</button>
                </form>
            <?php endif; ?>
        </div>

        <nav class="board-tabs attendance-tabs" aria-label="출석 정보">
            <a href="?tab=feed"
               class="board-tab <?php echo $active_tab === 'feed' ? 'is-active' : ''; ?>"
               role="tab"
               aria-selected="<?php echo $active_tab === 'feed' ? 'true' : 'false'; ?>">일일 출석 현황</a>
            <a href="?tab=points"
               class="board-tab <?php echo $active_tab === 'points' ? 'is-active' : ''; ?>"
               role="tab"
               aria-selected="<?php echo $active_tab === 'points' ? 'true' : 'false'; ?>">포인트 내역</a>
        </nav>

        <?php if ($active_tab === 'feed'): ?>
        <div class="attendance-tab-panel" role="tabpanel" aria-label="일일 출석 현황">
        <?php if (empty($feed_rows)): ?>
            <div class="board-empty">
                <p>오늘(<?php echo htmlspecialchars($today); ?>) 아직 출석한 회원이 없습니다. 첫 출석을 남겨 보세요!</p>
            </div>
        <?php else: ?>
            <div class="attendance-feed-table" role="table" aria-label="일일 출석 현황">
                <div class="attendance-feed-row is-head" role="row">
                    <div role="columnheader">일시</div>
                    <div role="columnheader">회원</div>
                    <div role="columnheader">포인트</div>
                </div>
                <?php foreach ($feed_rows as $row):
                    $is_me = (int) $row['mb_idx'] === $mb_idx;
                    $nick  = trim((string) ($row['mb_nick'] ?? ''));
                    if ($nick === '') {
                        $nick = '(탈퇴회원)';
                    }
                ?>
                    <div class="attendance-feed-row<?php echo $is_me ? ' is-me' : ''; ?>" role="row">
                        <div role="cell"><?php echo htmlspecialchars($row['att_created_at']); ?></div>
                        <div role="cell" class="attendance-feed-nick">
                            <?php echo htmlspecialchars($nick); ?>
                            <?php if ($is_me): ?>
                                <span class="attendance-feed-me">나</span>
                            <?php endif; ?>
                        </div>
                        <div role="cell" class="attendance-log-chg is-plus">+<?php echo number_format((int) $row['att_point']); ?> P</div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="attendance-feed-note">오늘(<?php echo htmlspecialchars($today); ?>) <?php echo number_format($feed_count); ?>명이 출석했습니다.</p>
        <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="attendance-tab-panel" role="tabpanel" aria-label="포인트 내역">
        <?php if (empty($point_rows)): ?>
            <div class="board-empty">
                <p>아직 포인트 내역이 없습니다. 출석으로 첫 포인트를 받아 보세요!</p>
            </div>
        <?php else: ?>
            <div class="attendance-log-table" role="table" aria-label="포인트 내역">
                <div class="attendance-log-row is-head" role="row">
                    <div role="columnheader">일시</div>
                    <div role="columnheader">유형</div>
                    <div role="columnheader">내용</div>
                    <div role="columnheader">증감</div>
                    <div role="columnheader">잔액</div>
                </div>
                <?php foreach ($point_rows as $row):
                    $chg = (int) $row['pl_change'];
                    $typ = $row['pl_type'];
                    $tl  = $type_labels[$typ] ?? $typ;
                ?>
                    <div class="attendance-log-row" role="row">
                        <div role="cell"><?php echo htmlspecialchars($row['pl_created_at']); ?></div>
                        <div role="cell"><span class="point-type"><?php echo htmlspecialchars($tl); ?></span></div>
                        <div role="cell" class="attendance-log-memo"><?php echo htmlspecialchars($row['pl_memo'] ?? '—'); ?></div>
                        <div role="cell" class="attendance-log-chg <?php echo $chg >= 0 ? 'is-plus' : 'is-minus'; ?>">
                            <?php echo $chg >= 0 ? '+' : ''; ?><?php echo number_format($chg); ?> P
                        </div>
                        <div role="cell"><?php echo number_format((int) $row['pl_balance']); ?> P</div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_page > 1): ?>
                <nav class="pagination" aria-label="포인트 내역 페이지">
                    <?php for ($p = 1; $p <= $total_page; $p++): ?>
                        <a href="?tab=points&amp;p=<?php echo $p; ?>" class="page-link <?php echo $p === $page_num ? 'is-active' : ''; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
