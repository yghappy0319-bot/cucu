<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../trade/lib/_trade_payment.php';
require_once __DIR__ . '/../auction/lib/_auction_order.php';
require_once __DIR__ . '/../lib/_member_cash_withdraw.php';
require_once __DIR__ . '/../trade/lib/_member_trade_sell_suspend.php';

$page  = 'faq';
$title = '자주 묻는 질문';
$trade_fee_pct = platform_fee_trade_percent();
$auction_fee_pct = platform_fee_auction_percent();
$auto_confirm_days = (int) TRADE_PURCHASE_AUTO_CONFIRM_DAYS;
$withdraw_min = (int) MEMBER_CASH_WITHDRAW_MIN;
$sale_cancel_limit = (int) MEMBER_TRADE_SALE_CANCEL_PENALTY_THRESHOLD;
$meta_description = 'Pokazone 이용 중 자주 묻는 질문(FAQ)과 답변. 회원가입, 거래, 결제, 환불, 사기신고 관련 내용을 확인하세요.';
$meta_keywords    = 'Pokazone FAQ, 자주묻는질문, 포켓몬 카드 거래 FAQ, 안전거래';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '자주 묻는 질문', 'url' => '/page/faq.php'],
];

$faq_groups = [
    'account' => [
        'label' => '계정·회원',
        'items' => [
            ['q' => '회원가입은 어떻게 하나요?',
             'a' => '우측 상단 "회원가입" 버튼을 눌러 아이디·비밀번호·닉네임·이메일을 입력하시면 됩니다. 만 14세 이상부터 가입이 가능합니다.'],
            ['q' => '비밀번호를 잊어버렸어요.',
             'a' => '로그인 화면의 "비밀번호 찾기"에서 가입 시 등록한 이메일로 재설정 링크를 받을 수 있습니다. (준비 중)'],
            ['q' => '닉네임은 변경할 수 있나요?',
             'a' => '마이페이지 > 프로필 수정에서 30일에 한 번 변경할 수 있습니다. 중복된 닉네임은 사용할 수 없습니다.'],
            ['q' => '회원탈퇴는 어디서 하나요?',
             'a' => '마이페이지 > 회원정보 하단의 "회원탈퇴"에서 진행할 수 있습니다. 탈퇴 시 작성하신 글·댓글 및 거래내역은 복구되지 않습니다.'],
        ],
    ],
    'trade' => [
        'label' => '거래',
        'items' => [
            ['q' => '카드는 어떻게 등록하나요?',
             'a' => '거래게시판 > "거래글 등록" 버튼을 누르고 상품 종류(카드/미개봉박스), 상태, 가격, 사진을 입력하면 됩니다. 사진은 최소 1장 필수이며 최대 8장까지 업로드 가능합니다.'],
            ['q' => '이미 올린 글의 사진 순서를 바꿀 수 있나요?',
             'a' => '수정 화면에서 썸네일을 드래그하면 순서가 변경됩니다. 첫 번째 사진이 자동으로 대표이미지로 지정됩니다.'],
            ['q' => '가격을 쓰지 않아도 되나요?',
             'a' => '가격을 0원으로 등록하면 "가격제안" 매물로 표시됩니다. 희망가를 밝히지 않고 구매자들의 제안을 받을 수 있습니다.'],
            ['q' => '거래 상태(판매중/거래중/거래완료)는 어떻게 바꾸나요?',
             'a' => '거래글 상세 화면의 "상태 변경" 버튼으로 바꿀 수 있습니다. 안전결제로 입금이 확인되면 자동으로 거래중으로 바뀌고, 거래가 마무리되면 꼭 거래완료로 바꿔주세요.'],
        ],
    ],
    'safety' => [
        'label' => '안전·사기방지',
        'items' => [
            ['q' => '사기를 당한 것 같아요. 어떻게 하나요?',
             'a' => '즉시 1:1 문의로 신고해 주세요. 거래 내역, 계좌이체 증빙, 대화 캡처를 함께 제출하시면 빠른 조사가 가능합니다. 경찰청 사이버수사대(182)에도 함께 접수하시길 권장드립니다.'],
            ['q' => '안전결제는 어떻게 이용하나요?',
             'a' => '거래글 또는 경매에서 <strong>바로구매·안전결제</strong>를 선택하면 무통장 입금 안내가 표시됩니다. '
                 . '신청한 <strong>입금 금액</strong>과 <strong>입금자명</strong>이 일치해야 입금 확인되며, 금액은 포카존에 예치됩니다. '
                 . '배송 후 구매확정 시 판매자에게 정산되며, 배송 확인 후 ' . $auto_confirm_days . '일이 지나면 자동 구매확정될 수 있습니다. '
                 . '자세한 내용은 <a href="/page/terms.php">이용약관</a>을 참고해 주세요.'],
            ['q' => '상대방의 거래 이력은 어디서 확인하나요?',
             'a' => '상대방 닉네임을 클릭하면 판매자 상점에서 등록한 거래글과 커뮤니티 글을 확인할 수 있습니다. 신규 계정이거나 거래 이력이 적으면 주의가 필요합니다.'],
        ],
    ],
    'payment' => [
        'label' => '결제·환불',
        'items' => [
            ['q' => '거래 수수료가 있나요?',
             'a' => '무통장 안전결제(거래·경매) 이용 시, <strong>구매확정 시점</strong>에 결제 금액의 '
                 . '<strong>' . $trade_fee_pct . '%</strong>(경매도 동일 ' . $auction_fee_pct . '%)가 플랫폼 수수료로 차감되고 나머지 금액이 판매자에게 캐시로 정산됩니다. '
                 . '판매금 출금 시에는 별도 수수료가 없습니다. 바로구매 시 관리자가 발행한 쿠폰(정액·정률 할인)을 사용할 수 있으며, 쿠폰 할인액은 플랫폼이 부담합니다.'],
            ['q' => '판매금은 어떻게 받나요?',
             'a' => '구매확정 후 판매자에게 <strong>캐시</strong>로 적립됩니다. 마이페이지 &gt; <strong>판매금 출금</strong>에서 등록한 정산계좌로 출금 신청할 수 있으며, '
                 . '최소 출금 금액은 ₩' . number_format($withdraw_min) . '입니다.'],
            ['q' => '입금 확인 후 판매를 취소하면 어떻게 되나요?',
             'a' => '구매자에게 환불 처리됩니다. 다만 입금 확인 후 판매 취소가 누적 <strong>' . $sale_cancel_limit . '회</strong> 이상이면 '
                 . '거래 판매글 등록이 일시 정지될 수 있으니 신중히 진행해 주세요.'],
            ['q' => '환불 정책이 어떻게 되나요?',
             'a' => 'Pokazone은 거래 중개 플랫폼으로, 환불은 판매자·구매자 간 협의가 원칙입니다. 다만 사기·허위상품이 확인된 경우 회사가 중재에 나섭니다.'],
        ],
    ],
    'etc' => [
        'label' => '기타',
        'items' => [
            ['q' => '광고·제휴 문의는 어디로 하나요?',
             'a' => '<a href="/page/partner.php">제휴문의</a> 페이지에서 담당자 연락처와 요청 내용을 남겨주시면 영업일 기준 3일 이내에 회신드립니다.'],
            ['q' => '앱(iOS/Android)도 있나요?',
             'a' => '현재는 웹만 운영 중이며, 모바일에 최적화되어 있습니다. 네이티브 앱은 2025년 하반기 출시 예정입니다.'],
        ],
    ],
];

$current = isset($_GET['cat']) && isset($faq_groups[$_GET['cat']]) ? $_GET['cat'] : 'account';

// JSON-LD FAQPage
$qa = [];
foreach ($faq_groups as $g) {
    foreach ($g['items'] as $it) {
        $qa[] = [
            '@type'          => 'Question',
            'name'           => $it['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => strip_tags($it['a']),
            ],
        ];
    }
}
$meta_jsonld = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => $qa,
];
include __DIR__ . '/../include/header.php';
?>

<section class="policy-section">
    <div class="container">
        <header class="policy-head">
            <span class="policy-eyebrow">FAQ</span>
            <h1>자주 묻는 질문</h1>
            <p class="policy-lead">Pokazone 이용 중 많이 문의 주시는 내용을 모았습니다. 원하는 답을 찾지 못하셨다면 <a href="/page/inquiry.php">1:1 문의</a>로 연락 주세요.</p>
        </header>

        <div class="faq-layout">
            <aside class="faq-nav" aria-label="FAQ 카테고리">
                <ul>
                    <?php foreach ($faq_groups as $k => $g): ?>
                        <li>
                            <a href="?cat=<?php echo $k; ?>#faq-<?php echo $k; ?>"
                               class="<?php echo $current === $k ? 'is-active' : ''; ?>">
                                <?php echo htmlspecialchars($g['label']); ?>
                                <span class="faq-count"><?php echo count($g['items']); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>

            <div class="faq-content">
                <?php foreach ($faq_groups as $k => $g): ?>
                    <section id="faq-<?php echo $k; ?>" class="faq-group">
                        <h2 class="policy-h2"><?php echo htmlspecialchars($g['label']); ?></h2>
                        <?php foreach ($g['items'] as $idx => $it): ?>
                            <details class="faq-item" <?php echo ($current === $k && $idx === 0) ? 'open' : ''; ?>>
                                <summary>
                                    <span class="faq-q-mark" aria-hidden="true">Q</span>
                                    <span class="faq-q-text"><?php echo htmlspecialchars($it['q']); ?></span>
                                    <span class="faq-arrow" aria-hidden="true">▾</span>
                                </summary>
                                <div class="faq-a">
                                    <span class="faq-a-mark" aria-hidden="true">A</span>
                                    <p><?php echo $it['a']; ?></p>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="policy-cta">
            <h2>원하는 답을 못 찾으셨나요?</h2>
            <p>1:1 문의로 보내주시면 영업일 기준 24시간 이내 답변드립니다.</p>
            <div class="policy-cta-actions">
                <a href="/page/inquiry.php" class="btn btn-primary">1:1 문의하기</a>
                <a href="/page/notice.php" class="btn btn-outline">공지사항 보기</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
