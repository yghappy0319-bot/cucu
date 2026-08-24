<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_address.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/member_address.php'));
}

$mb_idx = (int) $me['mb_idx'];
$table_ready   = member_address_table_ready();
$message_ready = member_address_message_column_ready();
$addresses     = $table_ready ? member_address_list($mb_idx) : [];
$default_phone = (string) ($me['mb_phone'] ?? '');

$page  = 'member_address';
$title = '배송지 주소록';
$meta_description = '등록한 배송지를 관리하고 기본 배송지를 설정합니다.';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '마이페이지', 'url' => '/page/mypage.php'],
    ['name' => '배송지 주소록', 'url' => '/page/member_address.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage member-address">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">배송지 주소록</h1>
                <p class="board-desc">경매·택배 거래에 사용할 배송지를 등록하고 관리합니다. (최대 <?php echo (int) MEMBER_ADDRESS_MAX; ?>개)</p>
            </div>
            <div class="mypage-head-actions">
                <a href="/page/mypage.php" class="btn btn-outline btn-sm">마이페이지</a>
                <?php if ($table_ready): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="member-address-add-btn">배송지 추가</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$table_ready): ?>
            <div class="member-address-alert">
                배송지 기능을 사용하려면 DB에 <code>sql/tb_member_address.sql</code> 을 적용해 주세요.
            </div>
        <?php else: ?>
            <?php if (!$message_ready): ?>
                <div class="member-address-alert">
                    배송 메시지를 저장하려면 <code>sql/migrate_tb_member_address_message.sql</code> 을 적용해 주세요.
                </div>
            <?php endif; ?>
            <div id="member-address-list" class="member-address-list" aria-live="polite">
                <?php if (empty($addresses)): ?>
                    <div class="member-address-empty" id="member-address-empty">
                        <p>등록된 배송지가 없습니다.</p>
                        <button type="button" class="btn btn-primary btn-sm" data-member-address-add>첫 배송지 등록</button>
                    </div>
                <?php else: ?>
                    <?php foreach ($addresses as $addr): ?>
                        <?php
                        $addr_idx   = (int) $addr['addr_idx'];
                        $is_default = (int) ($addr['addr_is_default'] ?? 0) === 1;
                        $full_addr  = trim(
                            '[' . $addr['addr_zip'] . '] ' . $addr['addr_road']
                            . ($addr['addr_extra'] !== '' ? ' ' . $addr['addr_extra'] : '')
                            . ($addr['addr_detail'] !== '' ? ', ' . $addr['addr_detail'] : '')
                        );
                        ?>
                        <article class="member-address-card<?php echo $is_default ? ' is-default' : ''; ?>"
                                 data-addr-idx="<?php echo $addr_idx; ?>">
                            <div class="member-address-card-head">
                                <div class="member-address-card-title">
                                    <strong><?php echo htmlspecialchars($addr['addr_label']); ?></strong>
                                    <?php if ($is_default): ?>
                                        <span class="member-address-badge">기본</span>
                                    <?php endif; ?>
                                </div>
                                <div class="member-address-card-actions">
                                    <?php if (!$is_default): ?>
                                        <button type="button" class="btn btn-outline btn-sm" data-member-address-default="<?php echo $addr_idx; ?>">기본 설정</button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-outline btn-sm" data-member-address-edit="<?php echo $addr_idx; ?>">수정</button>
                                    <button type="button" class="btn btn-sm member-address-delete-btn" data-member-address-delete="<?php echo $addr_idx; ?>">삭제</button>
                                </div>
                            </div>
                            <dl class="member-address-card-dl">
                                <div>
                                    <dt>받는 분</dt>
                                    <dd><?php echo htmlspecialchars($addr['addr_name']); ?></dd>
                                </div>
                                <div>
                                    <dt>연락처</dt>
                                    <dd><?php echo htmlspecialchars($addr['addr_phone']); ?></dd>
                                </div>
                                <div class="member-address-card-addr">
                                    <dt>주소</dt>
                                    <dd><?php echo htmlspecialchars($full_addr); ?></dd>
                                </div>
                                <?php if ($message_ready && trim((string) ($addr['addr_message'] ?? '')) !== ''): ?>
                                <div>
                                    <dt>배송 메시지</dt>
                                    <dd><?php echo htmlspecialchars((string) $addr['addr_message']); ?></dd>
                                </div>
                                <?php endif; ?>
                            </dl>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($table_ready): ?>
<?php include __DIR__ . '/../include/member_address_modal.php'; ?>

<script>
window.__MEMBER_ADDRESS__ = {
    apiUrl: '/page/member_address_api.php',
    defaultPhone: <?php echo json_encode($default_phone, JSON_UNESCAPED_UNICODE); ?>,
    memberName: <?php echo json_encode((string) ($me['mb_name'] ?? ''), JSON_UNESCAPED_UNICODE); ?>,
    messageReady: <?php echo $message_ready ? 'true' : 'false'; ?>,
    messageMax: <?php echo (int) MEMBER_ADDRESS_MESSAGE_MAX; ?>
};
</script>
<?php
$__addr_js = dirname(__DIR__) . '/assets/js/member_address.js';
$__addr_v  = is_readable($__addr_js) ? '?m=' . (string) filemtime($__addr_js) : '';
$page_footer_extra = '<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>'
    . '<script src="/assets/js/member_address.js' . htmlspecialchars($__addr_v, ENT_QUOTES, 'UTF-8') . '"></script>';
?>
<?php endif; ?>

<?php include __DIR__ . '/../include/footer.php'; ?>
