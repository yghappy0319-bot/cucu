<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/draw_cards.php';

$dp_idx = max(0, (int) ($_GET['dp_idx'] ?? 0));
$count  = max(1, min(20, (int) ($_GET['count'] ?? 5)));

$pick = draw_pick_random_cards($dp_idx, $count);
$cardback = $pick['cardback'];
$slides = $pick['slides'];
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>카드 뽑기</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" crossorigin>
    <style>
        html, body {
            margin: 0;
            width: 100%;
            height: 100%;
            background: #0f172a;
        }
        body {
            display: grid;
            place-items: center;
        }
        .cards-swiper {
            width: 300px;
            height: 430px;
        }
        .cards-swiper .swiper-slide {
            border-radius: 16px;
            overflow: visible;
            user-select: none;
            -webkit-user-select: none;
        }
        .draw-card {
            width: 100%;
            height: 100%;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 16px 26px rgba(0, 0, 0, 0.45);
            overflow: hidden;
        }
        .draw-card-hit {
            display: block;
            width: 100%;
            height: 100%;
            border-radius: 16px;
            -webkit-tap-highlight-color: transparent;
        }
        .draw-card:not(.is-revealed) .draw-card-hit { cursor: pointer; }
        .draw-card.is-revealed .draw-card-hit { cursor: grab; }
        .draw-card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            transition: none;
        }
        .cards-swiper.is-ready .draw-card-inner {
            transition: transform 0.45s cubic-bezier(0.4, 0.2, 0.2, 1);
        }
        .draw-card.is-revealed .draw-card-inner { transform: rotateY(180deg); }
        .draw-card-face {
            position: absolute;
            inset: 0;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            border-radius: 16px;
            overflow: hidden;
        }
        .draw-card-face--front { transform: rotateY(180deg); }
        .draw-card-face img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            pointer-events: none;
        }
        .draw-card-hint {
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
            pointer-events: none;
            z-index: 2;
            white-space: nowrap;
        }
        .draw-card.is-revealed .draw-card-hint { display: none; }
        @media (max-width: 560px) {
            .cards-swiper { width: 250px; height: 360px; }
        }
    </style>
</head>
<body>
    <div class="swiper cards-swiper">
        <div class="swiper-wrapper">
            <?php foreach ($slides as $slide): ?>
                <div class="swiper-slide draw-card" data-revealed="0">
                    <div class="draw-card-hit" role="button" tabindex="0" aria-label="카드 뒤집기">
                        <div class="draw-card-inner">
                            <div class="draw-card-face draw-card-face--back">
                                <img src="<?php echo htmlspecialchars($cardback, ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="카드 뒷면" draggable="false">
                            </div>
                            <div class="draw-card-face draw-card-face--front">
                                <img src="<?php echo htmlspecialchars((string) $slide['front_src'], ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="<?php echo htmlspecialchars((string) $slide['front_alt'], ENT_QUOTES, 'UTF-8'); ?>"
                                     draggable="false">
                            </div>
                        </div>
                        <span class="draw-card-hint">탭하여 카드 확인</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" crossorigin></script>
    <script>
        window.addEventListener('load', function () {
            if (!window.Swiper) return;
            var swiperEl = document.querySelector('.cards-swiper');
            if (!swiperEl) return;
            new Swiper(swiperEl, { effect: 'cards', grabCursor: true, allowTouchMove: true });
            var pointerStart = null, pointerMoved = false;
            var revealCard = function (card) {
                if (!card || card.classList.contains('is-revealed')) return;
                if (!card.classList.contains('swiper-slide-active')) return;
                var hit = card.querySelector('.draw-card-hit');
                var frontImg = card.querySelector('.draw-card-face--front img');
                card.classList.add('is-revealed');
                card.setAttribute('data-revealed', '1');
                if (hit) hit.setAttribute('aria-label', (frontImg && frontImg.alt) ? frontImg.alt : '카드');
            };
            swiperEl.addEventListener('pointerdown', function (e) {
                pointerStart = { x: e.clientX, y: e.clientY };
                pointerMoved = false;
            });
            swiperEl.addEventListener('pointermove', function (e) {
                if (!pointerStart) return;
                if (Math.abs(e.clientX - pointerStart.x) > 10 || Math.abs(e.clientY - pointerStart.y) > 10) {
                    pointerMoved = true;
                }
            });
            swiperEl.addEventListener('pointerup', function () { pointerStart = null; });
            swiperEl.addEventListener('pointercancel', function () { pointerStart = null; });
            swiperEl.addEventListener('click', function (e) {
                if (pointerMoved) return;
                var card = e.target.closest('.draw-card');
                if (card) revealCard(card);
            });
            requestAnimationFrame(function () { swiperEl.classList.add('is-ready'); });
        });
    </script>
</body>
</html>
