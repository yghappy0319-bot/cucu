<?php
require_once __DIR__ . '/../lib/_function.php';

$draw_me = login_member();

$draw_rows = [];
if (db_table_exists('tb_draw_product')) {
    $draw_rs = db_query("
        SELECT dp_idx, dp_name, dp_code, dp_image_path, dp_pack_type, dp_pack_count
        FROM tb_draw_product
        WHERE dp_status = 1
        ORDER BY dp_sort DESC, dp_idx DESC
        LIMIT 12
    ");
    if ($draw_rs) {
        while ($r = db_assoc($draw_rs)) {
            $draw_rows[] = $r;
        }
    }
}

$page  = 'draw';
$title = '뽑기';
$meta_description = 'Pokazone 뽑기 페이지';
$meta_keywords    = '포카존, 뽑기';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '뽑기', 'url' => '/page/draw.php'],
];
$hide_footer_menu = true;
$draw_logged_in_js = $draw_me ? 'true' : 'false';
$page_footer_extra = <<<HTML
<script>
window.addEventListener('load', function () {
    if ({$draw_logged_in_js}) return;
    document.querySelectorAll('.draw-item[href*="draw_view.php"]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            var href = el.getAttribute('href') || '/page/draw.php';
            alert('로그인 후 이용 가능합니다.');
            window.location.href = '/login.php?return=' + encodeURIComponent(href);
        });
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
    padding: 12px;
}
.draw-machine-lower {
    margin-top: 18px;
    height: 150px;
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(255, 224, 96, 0.55) 0%, rgba(255, 200, 20, 0.28) 100%);
    border: 2px solid rgba(199, 147, 0, 0.24);
}
.draw-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}
.draw-item {
    display: block;
    background: #fff;
    border: 1px solid #d7e1f4;
    border-radius: 10px;
    padding: 10px 8px;
    text-align: center;
    text-decoration: none;
    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
}
.draw-item:hover {
    transform: translateY(-2px);
    border-color: #a9bee9;
    box-shadow: 0 10px 16px rgba(66, 96, 158, 0.18);
}
.draw-pack {
    border-radius: 8px;
    background: linear-gradient(135deg, #67b7ff 0%, #5f7cff 100%);
    margin-bottom: 8px;
    position: relative;
    overflow: hidden;
}
.draw-pack::before {
    content: "";
    position: absolute;
    inset: 8px;
    border: 1px solid rgba(255, 255, 255, 0.45);
    border-radius: 6px;
}
.draw-pack--b {
    background: linear-gradient(135deg, #95d46a 0%, #38b56e 100%);
}
.draw-pack--c {
    background: linear-gradient(135deg, #f9be45 0%, #ff8a3c 100%);
}
.draw-pack--d {
    background: linear-gradient(135deg, #b792ff 0%, #7c5cf0 100%);
}
.draw-pack.has-image {
    background: #f2f5fb;
}
.draw-pack-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.draw-price {
    font-size: 11px;
    margin: 0;
    color: #29437d;
    font-weight: 800;
}
.draw-name {
    margin: 0 0 4px;
    font-size: 12px;
    color: #1f2f56;
    font-weight: 700;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.draw-code {
    margin: 0;
    font-size: 11px;
    color: #4f6a9f;
    font-weight: 700;
}
.draw-pack-type {
    margin: 4px 0 0;
    font-size: 10px;
    color: #6b7faa;
    font-weight: 700;
}
@media (max-width: 980px) {
    .draw-machine-wrap {
        max-width: 760px;
    }
}
@media (max-width: 560px) {
    .draw-page {
        padding: 16px 0 36px;
    }
    .draw-machine {
        border-radius: 20px;
        padding: 14px 14px 18px;
    }
    .draw-machine-logo {
        font-size: 24px;
    }
    .draw-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .draw-pack {
        height: 112px;
    }
    .draw-machine-lower {
        height: 110px;
        margin-top: 14px;
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
                <p class="draw-machine-sub">랜덤 카드 뽑기</p>
            </div>

            <div class="draw-screen">
                <div class="draw-screen-inner">
                    <div class="draw-grid">
                        <?php if (!empty($draw_rows)): ?>
                            <?php foreach ($draw_rows as $i => $row):
                                $variant = ['a', 'b', 'c', 'd'][$i % 4];
                                $name = trim((string) ($row['dp_name'] ?? ''));
                                $code = trim((string) ($row['dp_code'] ?? ''));
                                $img = trim((string) ($row['dp_image_path'] ?? ''));
                                $pack_type = trim((string) ($row['dp_pack_type'] ?? 'expansion'));
                                $pack_count = (int) ($row['dp_pack_count'] ?? 0);
                                if ($pack_count <= 0) {
                                    $pack_count = $pack_type === 'highclass' ? 10 : ($pack_type === 'enhanced' ? 20 : 30);
                                }
                                $pack_label = $pack_type === 'highclass' ? '하이클래스팩' : ($pack_type === 'enhanced' ? '강화확장팩' : '확장팩');
                                $label = $code !== '' ? $code : ('#' . (int) ($row['dp_idx'] ?? 0));
                                ?>
                                <a class="draw-item" href="/page/draw_view.php?idx=<?php echo (int) ($row['dp_idx'] ?? 0); ?>">
                                    <div class="draw-pack draw-pack--<?php echo $variant; ?><?php echo $img !== '' ? ' has-image' : ''; ?>">
                                        <?php if ($img !== ''): ?>
                                            <img class="draw-pack-image"
                                                 src="<?php echo htmlspecialchars(public_url($img), ENT_QUOTES, 'UTF-8'); ?>"
                                                 alt="<?php echo htmlspecialchars($name !== '' ? $name : '뽑기 카드', ENT_QUOTES, 'UTF-8'); ?>"
                                                 loading="lazy" decoding="async">
                                        <?php endif; ?>
                                    </div>
                                    <p class="draw-name"><?php echo htmlspecialchars($name !== '' ? $name : '이름 없음', ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="draw-code"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="draw-pack-type"><?php echo htmlspecialchars($pack_label . ' · ' . $pack_count . '개', ENT_QUOTES, 'UTF-8'); ?></p>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <?php for ($i = 1; $i <= 12; $i++):
                            $variant = ['a', 'b', 'c', 'd'][($i - 1) % 4];
                            ?>
                            <div class="draw-item">
                                <div class="draw-pack draw-pack--<?php echo $variant; ?>"></div>
                                <p class="draw-name">샘플 카드 <?php echo $i; ?></p>
                                <p class="draw-code">s<?php echo $i; ?></p>
                                <p class="draw-pack-type">확장팩 · 30개</p>
                            </div>
                        <?php endfor; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="draw-machine-lower" aria-hidden="true"></div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
