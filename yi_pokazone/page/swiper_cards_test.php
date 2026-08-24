<!doctype html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swiper Cards Test</title>
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
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 16px 26px rgba(0, 0, 0, 0.45);
            user-select: none;
            -webkit-user-select: none;
        }
        .cards-swiper .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            pointer-events: none;
        }
        @media (max-width: 560px) {
            .cards-swiper {
                width: 250px;
                height: 360px;
            }
        }
    </style>
</head>
<body>
    <div class="swiper cards-swiper">
        <div class="swiper-wrapper">
            <?php for ($i = 0; $i < 7; $i++): ?>
                <div class="swiper-slide">
                    <img src="/assets/img/cardback.png" alt="카드 뒷면 <?php echo $i + 1; ?>" draggable="false">
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" crossorigin></script>
    <script>
        window.addEventListener('load', function () {
            if (!window.Swiper) return;
            new Swiper('.cards-swiper', {
                effect: 'cards',
                grabCursor: true
            });
        });
    </script>
</body>
</html>
