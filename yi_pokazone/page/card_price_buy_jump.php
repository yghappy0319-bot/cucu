<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/card_price_buy_gate.php';

$t_raw      = isset($_GET['t']) ? trim((string) $_GET['t']) : '';
$charge_err = (string) ($_GET['charge_err'] ?? '') === '1';

$error           = '';
$sold_out_modal  = false;

if ($t_raw === '') {
    $error = '잘못된 접근입니다.';
} elseif (!card_price_buy_force_redirect_external()) {
    header('Location: /page/card_price_buy.php?t=' . rawurlencode($t_raw));
    exit;
}

$jump_return = '/page/card_price_buy_jump.php?t=' . rawurlencode($t_raw);

if ($error === '') {
    $tok = card_price_buy_token_verify($t_raw);
    if ($tok === null) {
        $error = '안내 시간이 만료되었거나 링크가 온전하지 않습니다.';
    } else {
        $url_key = trim((string) ($tok['u'] ?? ''));
        $offer   = card_price_buy_offer_by_product_url($url_key);
        if ($offer === null) {
            $error = '해당 외부 링크를 박스 시세에서 찾을 수 없습니다.';
        } else {
            $candidate = trim((string) ($offer['product_url'] ?? ''));
            if ($candidate === '' || $candidate !== $url_key || !card_price_valid_http_url($candidate)) {
                $error = '판매 페이지 주소가 올바르지 않습니다.';
            } else {
                $avail = card_price_buy_offer_available($offer);
                if ($avail && !$charge_err) {
                    $purchase = card_price_buy_try_purchase($t_raw, false, $jump_return . '&charge_err=1');
                    if (!empty($purchase['need_login'])) {
                        header('Location: /login.php?return=' . urlencode($jump_return));
                        exit;
                    }
                    if (!empty($purchase['ok'])) {
                        header('Location: ' . ($purchase['dest'] ?? ''));
                        exit;
                    }
                    $a = $purchase['alert'] ?? null;
                    if (is_array($a) && isset($a['msg'])) {
                        $redir = array_key_exists('url', $a) ? $a['url'] : '';
                        alert_goto((string) $a['msg'], $redir !== null ? (string) $redir : '');
                    }
                    $error = '처리 중 오류가 발생했습니다.';
                } elseif ($avail && $charge_err) {
                    $error = '포인트가 부족하거나 처리에 실패했습니다. 적립 후 다시 시도해 주세요.';
                } else {
                    $sold_out_modal = true;
                }
            }
        }
    }
}

$page          = 'card_price';
$title         = $sold_out_modal ? '상품 안내 · 박스 시세' : ($error !== '' ? '연결 안내 · 박스 시세' : '판매처로 이동 중');
$meta_noindex  = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '박스 시세', 'url' => '/page/card_price.php'],
];

$cost_display = number_format(CARD_PRICE_BUY_POINT_COST);

include __DIR__ . '/../include/header.php';
?>

<section class="community card-buy-jump-page">
    <div class="container card-buy-jump-inner">
        <?php if ($sold_out_modal): ?>
            <div id="card-buy-sold-modal"
                 class="card-buy-sold-modal"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="card-buy-sold-title"
                 aria-describedby="card-buy-sold-desc">
                <div class="card-buy-sold-modal__backdrop" aria-hidden="true"></div>
                <div class="card-buy-sold-modal__panel">
                    <p class="card-buy-sold-modal__badge" aria-hidden="true">구매 불가 안내</p>
                    <h2 id="card-buy-sold-title" class="card-buy-sold-modal__heading">품절입니다</h2>
                    <p id="card-buy-sold-desc" class="card-buy-sold-modal__desc">
                        이 판매처 링크는 현재 포인트 구매 안내가 비활성화된 상태입니다.<br>
                        <strong><?php echo htmlspecialchars($cost_display, ENT_QUOTES, 'UTF-8'); ?>P 차감 없이</strong> 나가거나,
                        아래 선택으로 외부 판매 페이지를 열 수 있습니다.
                    </p>
                    <div class="card-buy-sold-modal__note">
                        외부 상품 페이지로 이동할 때만 <strong><?php echo htmlspecialchars($cost_display, ENT_QUOTES, 'UTF-8'); ?>P</strong>가 차감됩니다.
                    </div>
                    <div class="card-buy-sold-modal__actions">
                        <button type="button" class="btn btn-sm card-buy-sold-modal__btn-ack" id="card-buy-sold-ack">
                            확인했습니다
                        </button>
                        <form class="card-buy-sold-modal__force-form" method="post" action="/proc/card_price_buy_proc.php">
                            <input type="hidden" name="t" value="<?php echo htmlspecialchars($t_raw, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="sold_out_force" value="1">
                            <input type="hidden" name="jump_flow" value="1">
                            <button type="submit" class="btn btn-sm card-buy-sold-modal__btn-force">
                                그래도 확인해 볼래요 (<?php echo htmlspecialchars($cost_display, ENT_QUOTES, 'UTF-8'); ?>P)
                            </button>
                        </form>
                    </div>
                    <div class="card-buy-sold-modal__footer">
                        <a href="/page/card_price.php">박스 시세 목록으로</a>
                    </div>
                </div>
            </div>
            <script>
            (function () {
                function goBackSafe() {
                    if (window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.location.href = '/page/card_price.php';
                    }
                }
                var btn = document.getElementById('card-buy-sold-ack');
                if (btn) {
                    btn.addEventListener('click', goBackSafe);
                }
            })();
            </script>
        <?php elseif ($error !== ''): ?>
            <p class="card-buy-confirm-alert" role="alert"><?php echo htmlspecialchars($error); ?></p>
            <p class="shop-prep-desc">
                <a class="btn btn-outline btn-sm" href="/page/card_price.php">박스 시세로 돌아가기</a>
                <?php if ($t_raw !== ''): ?>
                    <span class="card-buy-jump-muted"> 또는 </span>
                    <a class="btn btn-outline btn-sm" href="<?php echo htmlspecialchars('/page/card_price_buy.php?t=' . rawurlencode($t_raw), ENT_QUOTES, 'UTF-8'); ?>">
                        확인 페이지로 이동
                    </a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
