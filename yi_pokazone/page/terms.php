<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../trade/lib/_trade_payment.php';
require_once __DIR__ . '/../auction/lib/_auction_order.php';
require_once __DIR__ . '/../lib/_member_cash_withdraw.php';
require_once __DIR__ . '/../trade/lib/_member_trade_sell_suspend.php';
require_once __DIR__ . '/../auction/lib/_auction.php';

$page  = 'terms';
$title = '이용약관';

$__brand              = function_exists('site_setting_get') ? site_setting_get('site_name', 'Pokazone') : 'Pokazone';
$__company            = site_legal_company_name();
$__ceo                = site_legal_ceo_name();
$__biz_no             = site_legal_biz_no();
$__effective          = site_legal_effective_label('terms');
$__trade_fee_pct      = platform_fee_trade_percent();
$__auction_fee_pct    = platform_fee_auction_percent();
$__auto_confirm_days  = (int) TRADE_PURCHASE_AUTO_CONFIRM_DAYS;
$__withdraw_min       = (int) MEMBER_CASH_WITHDRAW_MIN;
$__withdraw_fee_rate  = (int) MEMBER_CASH_WITHDRAW_FEE_RATE;
$__sale_cancel_limit  = (int) MEMBER_TRADE_SALE_CANCEL_PENALTY_THRESHOLD;
$__sell_suspend_hours = (int) MEMBER_TRADE_SELL_SUSPEND_HOURS;
$__auction_refund_days = (int) AUCTION_REFUND_PERIOD_DAYS;
$__attendance_point   = (int) attendance_reward_point();
$__auction_del_point  = (int) AUCTION_DELETE_POINT_PENALTY;

$meta_description = $__brand . ' 서비스 이용약관 - 수수료, 안전결제, 캐시 정산, 경매 이용 조건 등을 안내합니다.';
$meta_keywords    = 'Pokazone, 이용약관, 서비스약관, 거래수수료, 안전결제';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '이용약관', 'url' => '/page/terms.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="policy-section">
    <div class="container policy-doc">
        <header class="policy-head">
            <h1>이용약관</h1>
            <p class="policy-meta">시행일: <?php echo htmlspecialchars($__effective, ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="policy-lead">
                <?php echo htmlspecialchars($__company, ENT_QUOTES, 'UTF-8'); ?>(이하 "회사")가 운영하는
                <?php echo htmlspecialchars($__brand, ENT_QUOTES, 'UTF-8'); ?>(이하 "서비스") 이용에 관한 조건을 안내합니다.
            </p>
        </header>

        <article>
            <?php include __DIR__ . '/../include/legal_business_info.php'; ?>

            <h2>제1조 (목적)</h2>
            <p>이 약관은 회사가 제공하는 <?php echo htmlspecialchars($__brand, ENT_QUOTES, 'UTF-8'); ?> 및 관련 제반 서비스의 이용조건·절차, 회사와 회원 간의 권리·의무 및 책임사항, 기타 필요한 사항을 규정함을 목적으로 합니다.</p>

            <h2>제2조 (용어의 정의)</h2>
            <ol>
                <li><strong>"회원"</strong>이란 이 약관에 동의하고 회사와 이용계약을 체결하여 서비스를 이용하는 자를 말합니다.</li>
                <li><strong>"아이디(ID)"</strong>란 회원 식별과 서비스 이용을 위해 회원이 정하고 회사가 승인하는 문자·숫자의 조합을 말합니다.</li>
                <li><strong>"거래"</strong>란 회원 상호 간에 이루어지는 포켓몬 카드 및 관련 상품의 매매·교환 행위를 말합니다.</li>
                <li><strong>"안전결제"</strong>란 회사가 제공하는 무통장 입금 기반의 결제·예치·정산 절차를 말합니다.</li>
                <li><strong>"구매확정"</strong>이란 구매자가 상품 수령·검수 후 거래 대금의 판매자 지급을 확정하는 행위를 말합니다.</li>
                <li><strong>"캐시"</strong>란 안전결제 거래·경매 정산 등으로 회원에게 적립되는 서비스 내 정산 수단을 말하며, 현금이 아닙니다.</li>
                <li><strong>"포인트"</strong>란 출석체크·이벤트 등으로 지급되는 서비스 내 재화로, 회사가 정한 범위에서만 사용할 수 있습니다.</li>
                <li><strong>"쿠폰"</strong>이란 회사가 발행하여 바로구매 결제 시 할인에 사용할 수 있는 전자적 이용권을 말합니다.</li>
                <li><strong>"경매"</strong>란 회원이 등록한 상품에 대해 다른 회원이 입찰하고, 낙찰된 금액으로 거래가 이루어지는 서비스를 말합니다.</li>
                <li><strong>"게시물"</strong>이란 회원이 서비스에 게시한 글, 사진, 동영상, 파일, 링크 등 일체의 정보를 말합니다.</li>
            </ol>

            <h2>제3조 (약관의 게시와 개정)</h2>
            <ol>
                <li>회사는 이 약관의 내용을 회원이 쉽게 확인할 수 있도록 서비스 내에 게시합니다.</li>
                <li>회사는 관련 법령을 위배하지 않는 범위에서 이 약관을 개정할 수 있습니다.</li>
                <li>회사가 약관을 개정하는 경우 적용일자 및 개정사유를 명시하여 시행일 7일 전부터 공지합니다. 회원에게 불리한 변경은 최소 30일 전에 공지합니다.</li>
                <li>회원이 개정 약관 시행일 이후에도 서비스를 계속 이용하는 경우 개정 약관에 동의한 것으로 봅니다.</li>
            </ol>

            <h2>제4조 (이용계약의 체결)</h2>
            <ol>
                <li>이용계약은 이용자가 약관에 동의하고 회원가입을 신청하며, 회사가 이를 승낙함으로써 체결됩니다.</li>
                <li>회원가입은 만 14세 이상만 가능합니다.</li>
                <li>회사는 다음 각 호에 해당하는 경우 승낙을 거부하거나 사후에 이용계약을 해지할 수 있습니다.
                    <ul>
                        <li>이전에 회원자격을 상실한 적이 있는 경우</li>
                        <li>실명이 아니거나 타인의 명의를 이용한 경우</li>
                        <li>허위 정보를 기재하거나 필수 정보를 누락한 경우</li>
                        <li>기타 회사가 정한 요건을 충족하지 못한 경우</li>
                    </ul>
                </li>
            </ol>

            <h2>제5조 (회원정보의 변경)</h2>
            <p>회원은 마이페이지를 통해 본인의 정보를 열람·수정할 수 있습니다. 다만 서비스 운영·본인확인을 위해 필요한 일부 항목은 수정이 제한될 수 있습니다.</p>

            <h2>제6조 (회원의 의무)</h2>
            <ol>
                <li>회원은 관계법령, 이 약관, 서비스 이용안내 및 회사의 공지사항을 준수해야 합니다.</li>
                <li>회원은 다음 행위를 해서는 안 됩니다.
                    <ul>
                        <li>허위 정보 등록, 타인 정보 도용</li>
                        <li>허위·기만적 거래, 사기, 불법 상품 거래</li>
                        <li>서비스 외부 메신저·계좌로 유도하여 안전결제를 회피하는 행위</li>
                        <li>타인의 저작권·초상권 등 권리 침해</li>
                        <li>회사 또는 제3자의 명예 훼손, 업무 방해</li>
                        <li>자동화 수단을 이용한 비정상적 접근·입찰·결제</li>
                    </ul>
                </li>
            </ol>

            <h2>제7조 (회사의 의무)</h2>
            <p>회사는 관련 법령과 이 약관이 정하는 바에 따라 안정적인 서비스 제공을 위해 노력하며, 개인정보 보호를 위해 개인정보처리방침을 수립·공개하고 이를 준수합니다.</p>

            <h2>제8조 (서비스의 종류)</h2>
            <p>회사가 제공하는 주요 서비스는 다음과 같습니다.</p>
            <ul class="policy-list">
                <li>거래게시판: 회원 간 카드·관련 상품 매매 게시 및 채팅</li>
                <li>안전결제: 무통장 입금, 예치, 배송·구매확정, 정산</li>
                <li>경매: 입찰, 낙찰, 결제, 배송, 구매확정</li>
                <li>커뮤니티, 공지, 1:1 문의 등 부가 서비스</li>
                <li>출석체크·포인트, 쿠폰, 판매금 출금 등 회원 혜택·정산 서비스</li>
            </ul>
            <p>회사는 운영상·기술상 필요에 따라 서비스의 전부 또는 일부를 변경·중단할 수 있으며, 중요한 변경은 사전에 공지합니다.</p>

            <h2>제9조 (통신판매중개 및 거래에 관한 책임)</h2>
            <ol>
                <li>회사는 통신판매중개자로서 회원 간 거래를 위한 온라인 장소와 안전결제 등 부가 기능을 제공할 뿐, 거래 당사자가 아닙니다.</li>
                <li>상품의 품질, 하자, 진정성, 배송 지연, 회원 간 분쟁 등 거래 자체에 대한 책임은 원칙적으로 판매자와 구매자에게 있습니다.</li>
                <li>회원은 거래 전 상품 상태·가격·배송 조건을 충분히 확인해야 하며, 사기가 의심되는 경우 즉시 회사에 신고해야 합니다.</li>
                <li>회사는 사기·반복 위반·운영정책 위반이 확인된 회원에 대해 경고, 이용정지, 영구 제재, 경매 이용 제한 등의 조치를 할 수 있습니다.</li>
            </ol>

            <h2>제10조 (안전결제 및 예치금)</h2>
            <ol>
                <li>안전결제를 이용하는 경우 구매자가 신청한 금액과 입금자명이 일치하는 무통장 입금이 확인되면, 해당 금액은 회사가 보관하는 예치금으로 처리됩니다.</li>
                <li>입금 금액·입금자명이 신청 내용과 다를 경우 자동 입금 확인이 되지 않을 수 있으며, 이로 인한 지연·취소에 대한 책임은 회원에게 있습니다.</li>
                <li>예치된 금액은 구매확정 시 판매자에게 정산되거나, 취소·환불 절차에 따라 구매자에게 반환됩니다.</li>
                <li>회사는 안전결제 미이용 거래(직거래, 외부 계좌이체 등)에 대하여 중개·예치·환불 의무를 부담하지 않습니다.</li>
            </ol>

            <h2>제11조 (수수료 및 정산)</h2>
            <ol>
                <li>거래게시판 안전결제 건에 대해 회사는 구매확정 시 결제 금액의 <strong><?php echo $__trade_fee_pct; ?>%</strong>를 플랫폼 수수료로 차감하고, 잔액을 판매자에게 캐시로 정산합니다.</li>
                <li>경매 건에 대해서도 구매확정 시 결제 금액의 <strong><?php echo $__auction_fee_pct; ?>%</strong>를 플랫폼 수수료로 차감하고, 잔액을 판매자에게 캐시로 정산합니다.</li>
                <li>쿠폰 할인이 적용된 거래의 경우, 판매자 정산은 쿠폰 적용 전 금액(원 결제 금액)을 기준으로 하며 할인액은 회사가 부담합니다.</li>
                <li>수수료율, 정산 방식 등은 서비스 화면 또는 공지사항에 표시되며, 관련 법령이 허용하는 범위에서 변경될 수 있습니다. 불리한 변경은 사전 공지합니다.</li>
                <li>판매금 출금 시 현재 별도 출금 수수료는 <strong><?php echo $__withdraw_fee_rate > 0 ? $__withdraw_fee_rate . '%' : '없습니다'; ?></strong>.</li>
            </ol>

            <h2>제12조 (구매확정 및 자동 구매확정)</h2>
            <ol>
                <li>구매자는 상품 수령·검수 후 구매확정을 진행해야 하며, 구매확정이 완료되면 예치금이 판매자에게 정산됩니다.</li>
                <li>배송 확인 후 구매자가 구매확정을 하지 않은 경우, 회사는 <strong><?php echo $__auto_confirm_days; ?>일</strong>이 경과한 때 자동으로 구매확정 처리할 수 있습니다.</li>
                <li>구매확정(자동 구매확정 포함) 이후에는 원칙적으로 거래 취소·환불이 제한되며, 하자·미배송 등 분쟁이 있는 경우 회사의 분쟁 처리 절차에 따릅니다.</li>
            </ol>

            <h2>제13조 (캐시 및 출금)</h2>
            <ol>
                <li>캐시는 현금이 아니며, 회사가 정한 용도와 조건에 따라 사용·출금할 수 있습니다.</li>
                <li>판매자는 마이페이지에서 캐시 출금을 신청할 수 있으며, 최소 출금 금액은 <strong>₩<?php echo number_format($__withdraw_min); ?></strong>입니다.</li>
                <li>출금은 회원이 등록한 정산계좌로 이체되며, 처리 기간·제한 사항은 서비스 화면 및 공지사항에 따릅니다.</li>
                <li>부정한 방법으로 적립된 캐시는 회수·출금 제한될 수 있습니다.</li>
            </ol>

            <h2>제14조 (쿠폰 및 포인트)</h2>
            <ol>
                <li>쿠폰은 회사 또는 회사가 지정한 방식으로 발행되며, 유효기간·사용 조건·할인 한도는 쿠폰별로 다를 수 있습니다.</li>
                <li>쿠폰은 원칙적으로 현금으로 환급·양도되지 않으며, 사용 조건을 충족하지 못하면 사용할 수 없습니다.</li>
                <li>포인트는 출석체크(1일 <?php echo $__attendance_point; ?>P 등), 이벤트 등 회사가 정한 방식으로 지급되며, 회사가 지정한 기능에 사용됩니다.</li>
                <li>포인트는 현금으로 환급되지 않으며, 회사가 정한 정책에 따라 소멸·조정될 수 있습니다.</li>
            </ol>

            <h2>제15조 (경매 이용)</h2>
            <ol>
                <li>경매는 판매자가 등록한 상품에 대해 회원이 입찰하고, 경매 종료 시 최고가 입찰자가 낙찰되는 방식으로 운영됩니다.</li>
                <li>낙찰자는 회사가 정한 기한 내 안전결제를 완료해야 하며, 판매자는 배송 등 이행 의무를 집니다.</li>
                <li>경매 거래의 구매확정·수수료·정산은 제11조·제12조를 준용합니다.</li>
                <li>회사는 경매 운영정책 위반, 허위 입찰, 낙찰 후 미결제 등에 대해 입찰 제한, 경매 이용 정지, 포인트 차감(경매 삭제 시 <?php echo $__auction_del_point; ?>P 등) 등의 조치를 할 수 있습니다.</li>
            </ol>

            <h2>제16조 (환불·취소 및 분쟁 해결)</h2>
            <ol>
                <li>안전결제 이용 전 또는 입금 확인 전에는 구매자·판매자가 신청 취소를 할 수 있습니다.</li>
                <li>입금 확인 후 판매자가 판매를 취소하는 경우 구매자에게 환불 처리되며, 판매자에게는 운영정책에 따른 제재가 적용될 수 있습니다. 입금 확인 후 판매 취소가 누적 <strong><?php echo $__sale_cancel_limit; ?>회</strong> 이상인 경우 판매글 등록이 <strong><?php echo $__sell_suspend_hours; ?>시간</strong> 일시 정지될 수 있습니다.</li>
                <li>상품 하자, 미배송, 허위 매물 등 분쟁이 발생한 경우 회원은 1:1 문의 등을 통해 증빙자료와 함께 신고해야 하며, 회사는 합리적 범위에서 중재·조치할 수 있습니다.</li>
                <li>회사의 환불·중재는 안전결제·예치금 범위 내에서 이루어지며, 안전결제 미이용 거래에 대해서는 회원 간 합의가 원칙입니다.</li>
            </ol>

            <h2>제17조 (서비스 이용제한)</h2>
            <p>회원이 이 약관 또는 운영정책을 위반하거나 서비스 운영을 방해하는 경우, 회사는 사전 통지 없이 경고, 기능 제한, 일시정지, 영구 이용정지, 캐시·포인트 회수 등 필요한 조치를 할 수 있습니다.</p>

            <h2>제18조 (책임제한)</h2>
            <ol>
                <li>회사는 천재지변, 시스템 장애, 통신사·결제기관·택배사 등 제3자의 귀책, 기타 불가항력으로 서비스를 제공할 수 없는 경우 책임을 지지 않습니다.</li>
                <li>회사는 회원의 귀책사유로 인한 서비스 이용 장애에 대해 책임을 지지 않습니다.</li>
                <li>회사는 회원이 게재한 게시물의 정확성·신뢰성에 대해 보증하지 않습니다.</li>
                <li>무료로 제공되는 서비스에 대해서는 관련 법령에 특별한 규정이 없는 한 손해배상 책임을 부담하지 않습니다.</li>
            </ol>

            <h2>제19조 (준거법 및 재판관할)</h2>
            <p>이 약관은 대한민국 법령에 따르며, 서비스 이용과 관련하여 분쟁이 발생한 경우 민사소송법 등 관련 법령에 따른 관할 법원에 제소합니다.</p>

            <div class="policy-supplement">
                <h3>부칙</h3>
                <p>이 약관은 <?php echo htmlspecialchars($__effective, ENT_QUOTES, 'UTF-8'); ?>부터 시행합니다.</p>
                <p>2025년 1월 1일 시행되었던 종전 약관은 본 약관으로 대체됩니다.</p>
            </div>
        </article>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
