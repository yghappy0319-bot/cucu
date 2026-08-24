<?php
require_once __DIR__ . '/../lib/_function.php';
$page  = 'about';
$title = 'Pokazone 소개';
$meta_description = '포켓몬 카드 수집가와 트레이너를 위한 안전한 거래·시세·커뮤니티 플랫폼 Pokazone을 소개합니다.';
$meta_keywords    = 'Pokazone, 포켓몬카드 거래, 포켓몬카드 시세, 회사소개, 브랜드';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => 'Pokazone 소개', 'url' => '/page/about.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="policy-section">
    <div class="container">
        <header class="policy-head">
            <span class="policy-eyebrow">ABOUT POKAZONE</span>
            <h1>트레이너와 컬렉터를 위한<br>가장 든든한 거래 플랫폼</h1>
            <p class="policy-lead">Pokazone은 포켓몬 TCG 애호가들이 믿고 즐기는 시세·거래·커뮤니티 서비스를 제공합니다.</p>
        </header>

        <div class="policy-grid policy-grid-3">
            <article class="policy-card">
                <div class="policy-icon">🔍</div>
                <h2>실시간 시세</h2>
                <p>최근 거래 체결가와 매물 데이터를 기반으로 카드별 공정 시세를 제공합니다. 등락 트렌드까지 한눈에 확인하세요.</p>
            </article>
            <article class="policy-card">
                <div class="policy-icon">🛡️</div>
                <h2>안심 거래</h2>
                <p>회원 등급제, 사기 이력 관리, 안전결제 연동까지. 직거래든 택배든 걱정 없이 거래할 수 있도록 돕습니다.</p>
            </article>
            <article class="policy-card">
                <div class="policy-icon">💬</div>
                <h2>활발한 커뮤니티</h2>
                <p>자랑, 질문, 꿀팁부터 새 팩 개봉기까지. 전국 트레이너들이 모여 이야기를 나누는 커뮤니티입니다.</p>
            </article>
        </div>

        <div class="policy-split">
            <div>
                <h2 class="policy-h2">우리의 미션</h2>
                <p>포켓몬 카드 문화를 더 건강하고 투명하게. 거래 정보 비대칭을 줄이고, 창작·수집의 즐거움을 키우는 것이 Pokazone의 목표입니다.</p>
                <ul class="policy-list">
                    <li><strong>투명성</strong> — 체결가 공개로 신뢰 가능한 시세 제공</li>
                    <li><strong>안전성</strong> — 사기 예방 시스템과 분쟁 조정 프로세스</li>
                    <li><strong>커뮤니티</strong> — 함께 즐기는 포켓몬 TCG 문화 확산</li>
                </ul>
            </div>
            <div class="policy-stats">
                <div><span class="num">50,000+</span><span>등록 카드</span></div>
                <div><span class="num">12,000+</span><span>활성 회원</span></div>
                <div><span class="num">800+</span><span>일일 거래</span></div>
                <div><span class="num">99.2%</span><span>안전 거래율</span></div>
            </div>
        </div>

        <div class="policy-cta">
            <h2>지금 Pokazone과 함께하세요</h2>
            <p>무료 회원가입 후 모든 서비스를 이용할 수 있습니다.</p>
            <div class="policy-cta-actions">
                <a href="/page/register.php" class="btn btn-primary">회원가입</a>
                <a href="/trade/trade.php" class="btn btn-outline">거래게시판 둘러보기</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
