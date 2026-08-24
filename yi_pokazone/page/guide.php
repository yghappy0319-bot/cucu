<?php
require_once __DIR__ . '/../lib/_function.php';
$page  = 'guide';
$title = '거래 가이드';
$meta_description = '포켓몬 카드를 안전하게 거래하기 위한 판매·구매·직거래·택배거래 가이드. 사기 예방 체크리스트도 확인하세요.';
$meta_keywords    = '포켓몬카드 거래가이드, 안전거래, 직거래 팁, 택배거래, 사기예방, 포켓몬 TCG';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '거래 가이드', 'url' => '/page/guide.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="policy-section">
    <div class="container">
        <header class="policy-head">
            <span class="policy-eyebrow">TRADE GUIDE</span>
            <h1>안전한 포켓몬 카드 거래,<br>이렇게 하세요</h1>
            <p class="policy-lead">초보 트레이너부터 베테랑 컬렉터까지, 누구나 안심하고 거래할 수 있도록 Pokazone이 단계별로 안내합니다.</p>
        </header>

        <!-- 판매자 & 구매자 탭 섹션 -->
        <div class="guide-section">
            <h2 class="policy-h2">판매자 가이드</h2>
            <ol class="guide-steps">
                <li>
                    <span class="guide-step-no">01</span>
                    <div>
                        <h3>상품 등록하기</h3>
                        <p>카드명, 등급, 상태, 가격을 정확히 기재하세요. <strong>상태는 과장 없이</strong> 앞·뒷면 사진을 함께 올리면 문의가 줄어듭니다.</p>
                    </div>
                </li>
                <li>
                    <span class="guide-step-no">02</span>
                    <div>
                        <h3>가격 책정</h3>
                        <p>Pokazone 시세와 최근 거래가를 참고해 합리적인 가격으로 등록하세요. <strong>가격 제안 받기</strong>를 허용하면 빠른 거래가 가능합니다.</p>
                    </div>
                </li>
                <li>
                    <span class="guide-step-no">03</span>
                    <div>
                        <h3>구매자와 소통</h3>
                        <p>쪽지 또는 댓글로 거래 조건을 협의하세요. 반드시 <strong>Pokazone 내부</strong>에서 대화해 증거를 남기는 것이 좋습니다.</p>
                    </div>
                </li>
                <li>
                    <span class="guide-step-no">04</span>
                    <div>
                        <h3>안전하게 발송</h3>
                        <p>카드는 슬리브 + 탑로더 + 완충재를 갖춰 포장하고, <strong>반송 박스/등기</strong>로 발송하세요. 송장번호는 구매자와 공유합니다.</p>
                    </div>
                </li>
            </ol>
        </div>

        <div class="guide-section">
            <h2 class="policy-h2">구매자 가이드</h2>
            <ol class="guide-steps">
                <li>
                    <span class="guide-step-no">01</span>
                    <div>
                        <h3>상품·판매자 확인</h3>
                        <p>사진의 해상도, 모서리/중앙 정렬, 스크래치 여부를 꼼꼼히 확인하세요. 판매자의 <strong>거래 후기와 평점</strong>도 중요한 신호입니다.</p>
                    </div>
                </li>
                <li>
                    <span class="guide-step-no">02</span>
                    <div>
                        <h3>가격·상태 협의</h3>
                        <p>질문은 구체적으로. "휘어짐 있나요? 코너 화이트닝 있나요?" 등 상태를 <strong>명시적으로</strong> 확인하세요.</p>
                    </div>
                </li>
                <li>
                    <span class="guide-step-no">03</span>
                    <div>
                        <h3>안전결제 이용</h3>
                        <p>고가 카드일수록 <strong>무통장 안전결제(바로구매)</strong>를 이용하세요. 입금액은 포카존에 예치되고 구매확정 후 판매자에게 정산됩니다. 직접 계좌이체는 사기 위험이 크며 환불이 어렵습니다.</p>
                    </div>
                </li>
                <li>
                    <span class="guide-step-no">04</span>
                    <div>
                        <h3>수령 후 검수</h3>
                        <p>개봉 과정을 영상으로 남기면 분쟁 시 유리합니다. 이상이 있으면 <strong>24시간 이내</strong> 판매자에게 연락하세요.</p>
                    </div>
                </li>
            </ol>
        </div>

        <!-- 직거래 vs 택배거래 -->
        <div class="guide-compare">
            <h2 class="policy-h2">직거래 vs 택배거래</h2>
            <div class="guide-compare-grid">
                <div class="guide-compare-card">
                    <h3>🤝 직거래</h3>
                    <ul class="policy-list">
                        <li>사람 많은 공공장소(카페, 지하철역)에서 만나세요</li>
                        <li>현장에서 <strong>상태·수량 검수 후 결제</strong></li>
                        <li>너무 늦은 시간, 외진 장소는 피합니다</li>
                        <li>가능하면 동행자와 함께</li>
                    </ul>
                </div>
                <div class="guide-compare-card">
                    <h3>📦 택배거래</h3>
                    <ul class="policy-list">
                        <li>등기/반값택배 등 <strong>추적 가능</strong>한 방법 사용</li>
                        <li>송장번호는 발송 당일 공유</li>
                        <li>고가 카드는 <strong>무통장 안전결제(바로구매)</strong>로 진행</li>
                        <li>파손 방지를 위해 <strong>탑로더 + 에어캡</strong> 필수</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- 사기 예방 체크리스트 -->
        <div class="guide-warning" id="trade-fraud-checklist">
            <h2 class="policy-h2">⚠️ 사기 예방 체크리스트</h2>
            <ul class="policy-list policy-list-warn">
                <li>가격이 <strong>시세 대비 현저히 낮은</strong> 매물은 의심</li>
                <li>SNS나 외부 메신저로 대화를 유도하는 경우</li>
                <li>"선입금만 받는다", "계좌이체만 가능" 같은 조건</li>
                <li>신규 가입 직후 고가품을 대량 등록한 계정</li>
                <li>프로필 정보 부족, 이전 거래 내역 없음</li>
                <li>급하게 거래를 재촉하거나 이상한 시간대에만 연락</li>
            </ul>
            <p class="policy-note">피해 발생 시 즉시 <a href="/page/inquiry.php">1:1 문의</a>로 신고해 주세요. 수사기관에 증거자료(거래내역, 대화 캡처, 송금 내역)를 제출할 수 있도록 도와드립니다.</p>
        </div>

        <!-- 카드 상태 등급 설명 -->
        <div class="guide-section">
            <h2 class="policy-h2">Pokazone 카드 상태 등급</h2>
            <div class="policy-grid policy-grid-4">
                <div class="policy-card">
                    <h3>S · 민트</h3>
                    <p>갓 개봉한 상태. 긁힘·화이트닝·휘어짐 없음.</p>
                </div>
                <div class="policy-card">
                    <h3>A · 상급</h3>
                    <p>거의 새것. 미세한 사용감 있을 수 있음.</p>
                </div>
                <div class="policy-card">
                    <h3>B · 중급</h3>
                    <p>모서리 화이트닝, 약한 긁힘 존재.</p>
                </div>
                <div class="policy-card">
                    <h3>C · 하급</h3>
                    <p>눈에 띄는 흠집, 휨, 찍힘 있음. 보관·소장용.</p>
                </div>
            </div>
        </div>

        <div class="policy-cta">
            <h2>준비가 끝났다면?</h2>
            <p>지금 바로 거래게시판에서 원하는 카드를 찾거나 판매글을 등록해 보세요.</p>
            <div class="policy-cta-actions">
                <a href="/trade/trade.php" class="btn btn-primary">거래게시판 가기</a>
                <a href="/page/faq.php" class="btn btn-outline">자주 묻는 질문</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
