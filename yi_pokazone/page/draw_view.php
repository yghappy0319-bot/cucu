<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/draw_cards.php';

if (!login_member()) {
    alert_goto('로그인 후 이용 가능합니다.', '/login.php?return=' . urlencode($_SERVER['REQUEST_URI'] ?? '/page/draw.php'));
}

$idx = max(0, (int) ($_GET['idx'] ?? 0));
if ($idx < 1) {
    alert_goto('잘못된 접근입니다.', '/page/draw.php');
}
if (!db_table_exists('tb_draw_product')) {
    alert_goto('뽑기 테이블이 없습니다.', '/page/draw.php');
}

$row = db_assoc(db_query("
    SELECT dp_idx, dp_name, dp_code, dp_image_path, dp_pack_type, dp_pack_count
    FROM tb_draw_product
    WHERE dp_idx = {$idx} AND dp_status = 1
    LIMIT 1
"));
if (!$row) {
    alert_goto('존재하지 않는 팩입니다.', '/page/draw.php');
}

$name = trim((string) ($row['dp_name'] ?? ''));
$code = trim((string) ($row['dp_code'] ?? ''));
$img = trim((string) ($row['dp_image_path'] ?? ''));
$pack_type = trim((string) ($row['dp_pack_type'] ?? 'expansion'));
$pack_count = (int) ($row['dp_pack_count'] ?? 0);

$pack_label_map = [
    'expansion' => '확장팩',
    'enhanced' => '강화확장팩',
    'highclass' => '하이클래스팩',
];
$pack_count_map = [
    'expansion' => 30,
    'enhanced' => 20,
    'highclass' => 10,
];
$draw_cards_count_map = [
    'expansion' => 5,
    'enhanced' => 5,
    'highclass' => 10,
];
if (!isset($pack_label_map[$pack_type])) {
    $pack_type = 'expansion';
}
if ($pack_count <= 0) {
    $pack_count = (int) $pack_count_map[$pack_type];
}
$draw_cards_count = (int) ($draw_cards_count_map[$pack_type] ?? 5);
$pack_label = $pack_label_map[$pack_type];
$code_label = $code !== '' ? $code : ('#' . (int) ($row['dp_idx'] ?? 0));

$draw_opened_slots = [];
if (draw_pack_type_supports_box_plan($pack_type)) {
    $draw_box_info = draw_box_session_summary((int) ($row['dp_idx'] ?? 0), $pack_type, $pack_count);
    if ($draw_box_info) {
        $draw_opened_slots = is_array($draw_box_info['opened_slots'] ?? null) ? $draw_box_info['opened_slots'] : [];
    }
}

$page  = 'draw';
$title = ($name !== '' ? $name : '뽑기 상세');
$meta_description = '뽑기 팩 상세 페이지';
$meta_keywords    = '포카존, 뽑기, 상세';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '뽑기', 'url' => '/page/draw.php'],
    ['name' => $name !== '' ? $name : '상세', 'url' => '/page/draw_view.php?idx=' . (int) $row['dp_idx']],
];
$hide_footer_menu = true;
$draw_dp_idx_js = (int) ($row['dp_idx'] ?? 0);
$draw_cards_count_js = (int) $draw_cards_count;
$draw_cardback_url_js = json_encode(
    public_url('/assets/img/cardback.png'),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
$page_footer_extra = <<<HTML
<script>
window.addEventListener('load', function () {
    var modal = document.getElementById('drawBallLayer');
    var closeBtn = document.getElementById('drawBallLayerClose');
    var backdrop = modal ? modal.querySelector('.draw-layer-backdrop') : null;
    if (!modal) return;

    var balls = document.querySelectorAll('.draw-ball');
    var stage = document.getElementById('drawCardsStage');
    var swiperHost = document.getElementById('drawCardsSwiperHost');
    var swiperEl = document.getElementById('drawCardsSwiper');
    var swiperWrap = document.getElementById('drawCardsSwiperWrapper');
    var dpIdx = {$draw_dp_idx_js};
    var drawCount = {$draw_cards_count_js};
    var cardbackUrl = {$draw_cardback_url_js};
    var pickUrl = '/page/draw_cards_api.php?dp_idx=' + dpIdx + '&count=' + drawCount;
    var drawSwiper = null;
    var loadToken = 0;

    var escapeHtml = function (str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    };

    var buildSlideHtml = function (slide, backUrl) {
        var frontSrc = escapeHtml(slide.front_src || backUrl);
        var frontAlt = escapeHtml(slide.front_alt || '카드');
        var backSrc = escapeHtml(backUrl);
        return ''
            + '<div class="swiper-slide draw-card" data-revealed="0">'
            + '<div class="draw-card-hit" role="button" tabindex="0" aria-label="카드 뒤집기">'
            + '<div class="draw-card-inner">'
            + '<div class="draw-card-face draw-card-face--back">'
            + '<img src="' + backSrc + '" alt="카드 뒷면" draggable="false">'
            + '</div>'
            + '<div class="draw-card-face draw-card-face--front">'
            + '<img src="' + frontSrc + '" alt="' + frontAlt + '" draggable="false">'
            + '</div>'
            + '</div>'
            + '<span class="draw-card-hint">탭하여 카드 확인</span>'
            + '</div>'
            + '</div>';
    };

    var destroyDrawSwiper = function () {
        if (drawSwiper) {
            drawSwiper.destroy(true, true);
            drawSwiper = null;
        }
        if (swiperEl) swiperEl.classList.remove('is-ready');
        if (swiperWrap) swiperWrap.innerHTML = '';
        if (swiperHost) swiperHost.hidden = true;
    };

    var bindCardReveal = function (root) {
        if (!root) return;
        var pointerStart = null;
        var pointerMoved = false;

        var revealCard = function (card) {
            if (!card || card.classList.contains('is-revealed')) return;
            if (!card.classList.contains('swiper-slide-active')) return;
            var hit = card.querySelector('.draw-card-hit');
            var frontImg = card.querySelector('.draw-card-face--front img');
            card.classList.add('is-revealed');
            card.setAttribute('data-revealed', '1');
            if (hit) {
                hit.setAttribute('aria-label', (frontImg && frontImg.alt) ? frontImg.alt : '카드');
            }
        };

        root.addEventListener('pointerdown', function (e) {
            pointerStart = { x: e.clientX, y: e.clientY };
            pointerMoved = false;
        });
        root.addEventListener('pointermove', function (e) {
            if (!pointerStart) return;
            var dx = Math.abs(e.clientX - pointerStart.x);
            var dy = Math.abs(e.clientY - pointerStart.y);
            if (dx > 10 || dy > 10) pointerMoved = true;
        });
        root.addEventListener('pointerup', function () { pointerStart = null; });
        root.addEventListener('pointercancel', function () { pointerStart = null; });
        root.addEventListener('click', function (e) {
            if (pointerMoved) return;
            var card = e.target.closest('.draw-card');
            if (card) revealCard(card);
        });
        root.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var hit = e.target.closest('.draw-card-hit');
            if (!hit) return;
            e.preventDefault();
            revealCard(hit.closest('.draw-card'));
        });
    };

    var resetStage = function () {
        loadToken += 1;
        destroyDrawSwiper();
        if (stage) stage.classList.add('is-loading');
    };

    var openLayer = function (ballIdx) {
        if (!stage || !swiperWrap || !swiperEl || typeof Swiper === 'undefined') return;
        if (ballIdx == null || ballIdx < 0) return;

        var ballEl = document.querySelector('.draw-ball[data-ball-idx="' + ballIdx + '"]');
        if (ballEl && ballEl.classList.contains('is-opened')) {
            return;
        }

        var token = loadToken + 1;
        loadToken = token;

        modal.classList.add('is-open');
        document.body.classList.add('draw-layer-open');
        modal.setAttribute('aria-hidden', 'false');
        stage.classList.add('is-loading');
        destroyDrawSwiper();

        fetch(pickUrl + '&ball_idx=' + encodeURIComponent(ballIdx) + '&_=' + Date.now(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) { return res.json().then(function (data) { return { res: res, data: data }; }); })
            .then(function (payload) {
                if (token !== loadToken) return;
                if (!payload.res.ok || !payload.data || !payload.data.ok) {
                    throw new Error((payload.data && payload.data.message) ? payload.data.message : '카드를 불러오지 못했습니다.');
                }
                var backUrl = payload.data.cardback || cardbackUrl;
                var slides = payload.data.slides || [];
                swiperWrap.innerHTML = slides.map(function (slide) {
                    return buildSlideHtml(slide, backUrl);
                }).join('');
                swiperHost.hidden = false;
                drawSwiper = new Swiper(swiperEl, {
                    effect: 'cards',
                    grabCursor: true,
                    allowTouchMove: true,
                    cardsEffect: {
                        slideShadows: false
                    }
                });
                drawSwiper.update();
                stage.classList.remove('is-loading');
                if (payload.data.box) {
                    if (payload.data.box.new_box) {
                        balls.forEach(function (b) {
                            b.classList.remove('is-opened');
                            b.removeAttribute('aria-disabled');
                            b.setAttribute('tabindex', '0');
                        });
                    }
                    if (ballEl) {
                        ballEl.classList.add('is-opened');
                        ballEl.setAttribute('aria-disabled', 'true');
                        ballEl.setAttribute('tabindex', '-1');
                    }
                }
                requestAnimationFrame(function () {
                    swiperEl.classList.add('is-ready');
                });
            })
            .catch(function (err) {
                if (token !== loadToken) return;
                stage.classList.remove('is-loading');
                closeLayer();
                alert(err && err.message ? err.message : '카드를 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.');
            });
    };

    var closeLayer = function () {
        modal.classList.remove('is-open');
        document.body.classList.remove('draw-layer-open');
        modal.setAttribute('aria-hidden', 'true');
        resetStage();
    };

    bindCardReveal(swiperEl);

    balls.forEach(function (ball) {
        ball.setAttribute('role', 'button');
        if (ball.classList.contains('is-opened')) {
            ball.setAttribute('aria-disabled', 'true');
            ball.setAttribute('tabindex', '-1');
        } else {
            ball.setAttribute('tabindex', '0');
        }
        ball.addEventListener('click', function () {
            var idx = parseInt(ball.getAttribute('data-ball-idx') || '-1', 10);
            if (idx < 0) return;
            openLayer(idx);
        });
        ball.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            if (ball.classList.contains('is-opened')) return;
            e.preventDefault();
            var idx = parseInt(ball.getAttribute('data-ball-idx') || '-1', 10);
            if (idx >= 0) openLayer(idx);
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeLayer);
    if (backdrop) backdrop.addEventListener('click', closeLayer);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeLayer();
        }
    });
});
</script>
HTML;
$page_head_extra = <<<HTML
<style>
.draw-page {
    padding: 28px 0 56px;
}
.draw-machine-wrap {
    max-width: 980px;
    margin: 0 auto;
}
.draw-machine {
    background: linear-gradient(180deg, #ffd84d 0%, #ffcf2e 100%);
    border: 5px solid #e8b600;
    border-radius: 28px;
    box-shadow: 0 24px 50px rgba(0, 0, 0, 0.16);
    padding: 20px 20px 26px;
}
.draw-machine-head {
    text-align: center;
    margin-bottom: 12px;
}
.draw-machine-logo {
    font-size: 30px;
    line-height: 1;
    letter-spacing: 0.5px;
    margin: 0 0 6px;
    color: #1c3f95;
    text-shadow: -1px -1px 0 #fff, 1px -1px 0 #fff, -1px 1px 0 #fff, 1px 1px 0 #fff;
}
.draw-machine-sub {
    margin: 0;
    font-size: 13px;
    color: #6b5400;
    font-weight: 700;
}
.draw-screen {
    background: #191b26;
    border-radius: 20px;
    padding: 14px;
    border: 3px solid #10121a;
    box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.06);
}
.draw-screen-inner {
    background: linear-gradient(180deg, #f8fbff 0%, #eaf2ff 100%);
    border: 2px solid #b8c8ea;
    border-radius: 14px;
    padding: 14px;
}
.draw-detail-top {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 14px;
    align-items: center;
    margin-bottom: 14px;
}
.draw-detail-thumb {
    height: 220px;
    border-radius: 12px;
    background: linear-gradient(135deg, #67b7ff 0%, #5f7cff 100%);
    border: 1px solid #d7e1f4;
    overflow: hidden;
}
.draw-detail-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.draw-detail-meta h1 {
    margin: 0 0 6px;
    font-size: 22px;
    color: #1f2f56;
}
.draw-meta-line {
    margin: 0 0 4px;
    color: #355188;
    font-weight: 700;
    font-size: 14px;
}
.draw-ball-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 10px;
}
.draw-ball {
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: 999px;
    position: relative;
    background: linear-gradient(180deg, #ff6666 0 49%, #ffffff 51% 100%);
    border: 2px solid #1f2a3f;
    box-shadow: 0 5px 10px rgba(36, 56, 96, 0.25);
    cursor: pointer;
    transition: transform .14s ease, box-shadow .14s ease;
}
.draw-ball:hover:not(.is-opened) {
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 10px 14px rgba(36, 56, 96, 0.3);
}
.draw-ball.is-opened {
    cursor: default;
    opacity: 0.42;
    filter: grayscale(0.35);
    box-shadow: none;
    transform: none;
}
.draw-ball.is-opened::after {
    background: #e8ecf4;
}
.draw-ball::before {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    top: 50%;
    height: 2px;
    background: #1f2a3f;
    transform: translateY(-50%);
}
.draw-ball::after {
    content: "";
    position: absolute;
    left: 50%;
    top: 50%;
    width: 24%;
    height: 24%;
    border-radius: 999px;
    background: #fff;
    border: 2px solid #1f2a3f;
    transform: translate(-50%, -50%);
    box-shadow: inset 0 0 0 1px #dce5ff;
}
.draw-machine-lower {
    margin-top: 18px;
    height: 150px;
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(255, 224, 96, 0.55) 0%, rgba(255, 200, 20, 0.28) 100%);
    border: 2px solid rgba(199, 147, 0, 0.24);
}
.draw-detail-actions {
    margin-top: 16px;
}
.draw-back {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid #c4d4f1;
    color: #2a4a85;
    font-weight: 700;
    text-decoration: none;
    background: #fff;
}
body.draw-layer-open {
    overflow: hidden;
}
.draw-layer {
    position: fixed;
    inset: 0;
    z-index: 1200;
    display: none;
}
.draw-layer.is-open {
    display: block;
}
.draw-layer-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(11, 20, 39, 0.72);
    z-index: 1;
}
.draw-layer-panel {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: min(92vw, 390px);
    z-index: 2;
}
.draw-layer-close {
    position: absolute;
    top: -44px;
    right: 0;
    border: 0;
    width: 34px;
    height: 34px;
    border-radius: 999px;
    background: #23345e;
    color: #fff;
    font-size: 18px;
    cursor: pointer;
}
.draw-layer-stage {
    position: relative;
    width: 100%;
    height: 540px;
    border-radius: 14px;
    overflow: hidden;
    display: grid;
    place-items: center;
}
.draw-layer-loading {
    position: absolute;
    inset: 0;
    z-index: 2;
}
.draw-layer-stage:not(.is-loading) .draw-layer-loading {
    display: none;
}
.draw-layer-swiper-host {
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
}
.draw-layer-swiper-host[hidden] {
    display: none !important;
}
#drawCardsSwiper.draw-cards-swiper {
    width: 300px;
    height: 430px;
}
#drawCardsSwiper.draw-cards-swiper .swiper-slide {
    border-radius: 16px;
    overflow: visible;
    user-select: none;
    -webkit-user-select: none;
}
#drawCardsSwiper .draw-card {
    width: 100%;
    height: 100%;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 16px 26px rgba(0, 0, 0, 0.45);
    overflow: hidden;
}
#drawCardsSwiper .draw-card-hit {
    display: block;
    width: 100%;
    height: 100%;
    border-radius: 16px;
    -webkit-tap-highlight-color: transparent;
}
#drawCardsSwiper .draw-card:not(.is-revealed) .draw-card-hit {
    cursor: pointer;
}
#drawCardsSwiper .draw-card.is-revealed .draw-card-hit {
    cursor: grab;
}
#drawCardsSwiper .draw-card-inner {
    position: relative;
    width: 100%;
    height: 100%;
    transform-style: preserve-3d;
    transition: none;
}
#drawCardsSwiper.draw-cards-swiper.is-ready .draw-card-inner {
    transition: transform 0.45s cubic-bezier(0.4, 0.2, 0.2, 1);
}
#drawCardsSwiper .draw-card.is-revealed .draw-card-inner {
    transform: rotateY(180deg);
}
#drawCardsSwiper .draw-card-face {
    position: absolute;
    inset: 0;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    border-radius: 16px;
    overflow: hidden;
}
#drawCardsSwiper .draw-card-face--front {
    transform: rotateY(180deg);
}
#drawCardsSwiper .draw-card:not(.is-revealed) .draw-card-face--front {
    visibility: hidden;
}
#drawCardsSwiper .swiper-slide-shadow {
    display: none !important;
}
#drawCardsSwiper .draw-card-face img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    pointer-events: none;
}
#drawCardsSwiper .draw-card-hint {
    position: absolute;
    left: 50%;
    bottom: 14px;
    transform: translateX(-50%);
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.72);
    color: #e2e8f0;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: -0.02em;
    pointer-events: none;
    z-index: 2;
    white-space: nowrap;
}
#drawCardsSwiper .draw-card.is-revealed .draw-card-hint {
    display: none;
}
@media (max-width: 980px) {
    .draw-machine-wrap { max-width: 760px; }
    .draw-ball-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
    .draw-page { padding: 16px 0 36px; }
    .draw-machine { border-radius: 20px; padding: 14px 14px 18px; }
    .draw-machine-logo { font-size: 24px; }
    .draw-detail-top {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    .draw-detail-thumb { height: 180px; }
    .draw-ball-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .draw-machine-lower { height: 110px; margin-top: 14px; }
    .draw-layer-panel {
        width: min(94vw, 320px);
    }
    .draw-layer-stage {
        height: 460px;
    }
    #drawCardsSwiper.draw-cards-swiper {
        width: 250px;
        height: 360px;
    }
}
</style>
HTML;

include __DIR__ . '/../include/header.php';
?>

<section class="draw-page">
    <div class="container draw-machine-wrap">
        <div class="draw-machine">
            <div class="draw-machine-head">
                <h1 class="draw-machine-logo">POKAZONE</h1>
                <p class="draw-machine-sub">랜덤 상자 카드 뽑기</p>
            </div>

            <div class="draw-screen">
                <div class="draw-screen-inner">
                    <div class="draw-detail-top">
                        <div class="draw-detail-thumb">
                            <?php if ($img !== ''): ?>
                                <img src="<?php echo htmlspecialchars(public_url($img), ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="<?php echo htmlspecialchars($name !== '' ? $name : '뽑기 팩', ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                        </div>
                        <div class="draw-detail-meta">
                            <h1><?php echo htmlspecialchars($name !== '' ? $name : '이름 없음', ENT_QUOTES, 'UTF-8'); ?></h1>
                            <p class="draw-meta-line">제품번호: <?php echo htmlspecialchars($code_label, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="draw-meta-line">팩 종류: <?php echo htmlspecialchars($pack_label, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="draw-meta-line">총 몬스터볼: <?php echo number_format($pack_count); ?>개 (1회 <?php echo (int) $draw_cards_count; ?>장)</p>
                            <div class="draw-detail-actions">
                                <a class="draw-back" href="/page/draw.php">목록으로 돌아가기</a>
                            </div>
                        </div>
                    </div>

                    <div class="draw-ball-grid">
                        <?php for ($i = 0; $i < $pack_count; $i++):
                            $ball_opened = in_array($i, $draw_opened_slots, true);
                            ?>
                            <div class="draw-ball<?php echo $ball_opened ? ' is-opened' : ''; ?>"
                                 data-ball-idx="<?php echo $i; ?>"
                                 <?php echo $ball_opened ? 'aria-disabled="true"' : ''; ?>
                                 aria-label="<?php echo $ball_opened ? '개봉 완료 몬스터볼' : '몬스터볼 ' . ($i + 1); ?>"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <div class="draw-machine-lower" aria-hidden="true"></div>
        </div>
    </div>
</section>

<div class="draw-layer" id="drawBallLayer" aria-hidden="true">
    <div class="draw-layer-backdrop"></div>
    <div class="draw-layer-panel" role="dialog" aria-modal="true" aria-label="상자 카드 뽑기 슬라이드">
        <button type="button" class="draw-layer-close" id="drawBallLayerClose" aria-label="닫기">×</button>
        <div class="draw-layer-stage is-loading" id="drawCardsStage">
            <div class="draw-layer-loading" aria-hidden="true"></div>
            <div class="draw-layer-swiper-host" id="drawCardsSwiperHost" hidden>
                <div class="swiper draw-cards-swiper"
                     id="drawCardsSwiper"
                     data-swiper-suppress="1">
                    <div class="swiper-wrapper" id="drawCardsSwiperWrapper"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../include/footer.php'; ?>
