<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_search_rank.php';
require_once __DIR__ . '/../lib/_member_public.php';
require_once __DIR__ . '/lib/_trade_board.php';

$page  = 'trade';
$title = '거래게시판';
$meta_description = '포켓몬 카드와 미개봉 부스터박스를 안전하게 사고 팔고 교환해 보세요. 판매·구매·교환글을 한 눈에 확인할 수 있는 Pokazone 거래게시판.';
$meta_keywords    = '포켓몬카드 거래, 포켓몬카드 판매, 포켓몬카드 구매, 포켓몬 교환, 포켓몬 박스, 부스터박스, 미개봉박스, 포켓몬151';
$meta_type        = 'website';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '거래게시판', 'url' => '/trade/trade.php'],
];

$types = trade_board_types();
$item_filters = trade_board_item_filters();
$deal_status_labels = trade_board_deal_status_labels();

$filters = trade_board_parse_filters();
$cur_type  = $filters['type'];
$cur_item  = $filters['item'];
$keyword   = $filters['keyword'];
$hide_done = $filters['hide_done'];
$mine_only = $filters['mine_only'];

$me = login_member();
if ($keyword !== '') {
    search_log_keyword($keyword, $me ? (int) $me['mb_idx'] : null);
}
if ($mine_only && !$me) {
    $ret_mine = '/trade/trade.php' . trade_board_query_string(array_merge($filters, ['mine_only' => true]));
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode($ret_mine));
}

if ($mine_only) {
    $title = '내 거래글';
    $meta_breadcrumb[] = ['name' => '내 거래글', 'url' => '/trade/trade.php?mine=1'];
}

// 무한 스크롤: 첫 화면은 항상 1페이지부터
$result = trade_board_fetch_page($filters, 1, $me);
$rows = $result['rows'];
$has_more = $result['has_more'];

$has_wish = db_table_exists('tb_trade_like');
$user_wishes = ($has_wish && $me)
    ? trade_user_wished_map(array_column($rows, 'tr_idx'), (int) $me['mb_idx'])
    : [];

$qs = function ($overrides = []) use ($filters) {
    $next = $filters;
    if (array_key_exists('type', $overrides)) {
        $next['type'] = (string) $overrides['type'];
    }
    if (array_key_exists('item', $overrides)) {
        $next['item'] = (string) $overrides['item'];
    }
    if (array_key_exists('q', $overrides)) {
        $next['keyword'] = (string) $overrides['q'];
    }
    if (array_key_exists('active', $overrides)) {
        $next['hide_done'] = !empty($overrides['active']);
    }
    if (array_key_exists('mine', $overrides)) {
        $next['mine_only'] = !empty($overrides['mine']);
    }
    return trade_board_query_string($next) ?: '?';
};

$api_query = [
    'type'   => $cur_type,
    'item'   => $cur_item,
    'q'      => $keyword,
    'active' => $hide_done ? 1 : '',
    'mine'   => $mine_only ? 1 : '',
];

$__inf_js = __DIR__ . '/assets/js/trade_infinite.js';
$__inf_v = is_readable($__inf_js) ? ('?m=' . (string) filemtime($__inf_js)) : '';
$page_footer_extra = '<script src="/trade/assets/js/trade_infinite.js' . htmlspecialchars($__inf_v, ENT_QUOTES, 'UTF-8') . '" defer></script>';

include __DIR__ . '/../include/header.php';
?>

<section class="community trade-board">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title"><?php echo $mine_only ? '내 거래글' : '거래게시판'; ?></h1>
                <p class="board-desc">
                    <?php if ($mine_only): ?>
                        내가 등록한 거래글만 모아서 볼 수 있습니다.
                    <?php else: ?>
                        포켓몬 카드 · 미개봉 박스까지, 안전하게 사고 팔고 교환해 보세요.
                    <?php endif; ?>
                </p>
            </div>
            <div class="board-head-actions">
                <?php if ($me): ?>
                    <a href="<?php echo htmlspecialchars($mine_only ? $qs(['mine' => '']) : $qs(['mine' => 1]), ENT_QUOTES, 'UTF-8'); ?>"
                       class="btn btn-outline<?php echo $mine_only ? ' is-active' : ''; ?>">
                        <?php echo $mine_only ? '전체 보기' : '내 거래글'; ?>
                    </a>
                <?php endif; ?>
                <a href="/trade/trade_write.php<?php echo $cur_type ? '?type='.urlencode($cur_type) : ''; ?>" class="btn btn-primary">거래글 등록</a>
            </div>
        </div>

        <div class="board-tabs">
            <?php foreach ($types as $key => $label): ?>
                <a href="<?php echo htmlspecialchars($qs(['type' => $key]), ENT_QUOTES, 'UTF-8'); ?>"
                   class="board-tab <?php echo $cur_type === $key ? 'is-active' : ''; ?>">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="board-tabs board-tabs-sub">
            <?php foreach ($item_filters as $key => $label): ?>
                <a href="<?php echo htmlspecialchars($qs(['item' => $key]), ENT_QUOTES, 'UTF-8'); ?>"
                   class="board-tab <?php echo $cur_item === $key ? 'is-active' : ''; ?>">
                    <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form class="board-search" method="get" action="/trade/trade.php">
            <?php if ($cur_type !== ''): ?>
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($cur_type); ?>">
            <?php endif; ?>
            <?php if ($cur_item !== ''): ?>
                <input type="hidden" name="item" value="<?php echo htmlspecialchars($cur_item); ?>">
            <?php endif; ?>
            <label class="toggle-active">
                <input type="checkbox" name="active" value="1" <?php echo $hide_done ? 'checked' : ''; ?>>
                <span>거래완료 숨김</span>
            </label>
            <?php if ($me): ?>
            <label class="toggle-active">
                <input type="checkbox" name="mine" value="1" <?php echo $mine_only ? 'checked' : ''; ?>>
                <span>내 글만</span>
            </label>
            <?php endif; ?>
            <input type="search" name="q" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="상품명·제목으로 검색">
            <button type="submit" class="btn btn-outline btn-sm">검색</button>
        </form>

        <?php if (empty($rows)): ?>
            <div class="board-list">
                <div class="board-empty">
                    <div class="empty-emoji">🪪</div>
                    <p><?php if ($mine_only): ?>등록한 거래글이 없어요.<br>거래글을 올려보세요!<?php else: ?>조건에 맞는 거래글이 없어요.<br>첫 거래글을 올려보세요!<?php endif; ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="trade-infinite"
                 data-trade-infinite
                 data-has-more="<?php echo $has_more ? '1' : '0'; ?>"
                 data-next-page="2"
                 data-query="<?php echo htmlspecialchars(json_encode($api_query, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="trade-grid" data-trade-grid>
                    <?php
                    foreach ($rows as $row) {
                        include __DIR__ . '/include/trade_list_item.php';
                    }
                    ?>
                </div>
                <div class="trade-infinite-foot" data-trade-sentinel<?php echo $has_more ? '' : ' hidden'; ?>>
                    <p class="trade-infinite-status" data-trade-load-status hidden></p>
                    <div class="trade-infinite-spinner" aria-hidden="true"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
