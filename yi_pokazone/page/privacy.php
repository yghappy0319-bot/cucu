<?php
require_once __DIR__ . '/../lib/_function.php';

$page  = 'privacy';
$title = '개인정보처리방침';

$__brand     = function_exists('site_setting_get') ? site_setting_get('site_name', 'Pokazone') : 'Pokazone';
$__company   = site_legal_company_name();
$__ceo       = site_legal_ceo_name();
$__biz_no    = site_legal_biz_no();
$__effective = site_legal_effective_label('privacy');
$__privacy_email = site_legal_privacy_email();

$meta_description = $__brand . ' 개인정보처리방침 - 수집 항목, 이용 목적, 보관 기간, 이용자의 권리 등을 안내합니다.';
$meta_keywords    = 'Pokazone, 개인정보처리방침, 개인정보보호';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '개인정보처리방침', 'url' => '/page/privacy.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="policy-section">
    <div class="container policy-doc">
        <header class="policy-head">
            <h1>개인정보처리방침</h1>
            <p class="policy-meta">시행일: <?php echo htmlspecialchars($__effective, ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="policy-lead">
                <?php echo htmlspecialchars($__company, ENT_QUOTES, 'UTF-8'); ?>(이하 "회사")는
                <?php echo htmlspecialchars($__brand, ENT_QUOTES, 'UTF-8'); ?> 서비스(이하 "서비스") 이용 과정에서
                이용자의 개인정보를 소중히 여기며, 「개인정보 보호법」 등 관련 법령을 준수합니다.
            </p>
        </header>

        <article>
            <?php
            $legal_block_title = '개인정보처리자';
            include __DIR__ . '/../include/legal_business_info.php';
            ?>

            <h2>1. 개인정보의 수집 항목 및 방법</h2>
            <p>회사는 서비스 제공을 위해 아래와 같이 개인정보를 수집합니다. 필수 항목 미제공 시 일부 서비스 이용이 제한될 수 있습니다.</p>
            <div class="policy-table-wrap">
                <table class="policy-table">
                    <thead>
                        <tr><th>구분</th><th>수집 항목</th><th>수집 방법</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>회원가입 (필수)</td>
                            <td>아이디, 비밀번호, 이름, 닉네임, 이메일, 휴대전화번호</td>
                            <td>회원가입 시 직접 입력</td>
                        </tr>
                        <tr>
                            <td>휴대폰 인증 (필수·해당 시)</td>
                            <td>휴대전화번호, 인증번호, 인증 요청 IP</td>
                            <td>휴대폰 번호 변경·본인확인 시 SMS 인증</td>
                        </tr>
                        <tr>
                            <td>프로필 (선택)</td>
                            <td>프로필 이미지, 자기소개</td>
                            <td>마이페이지에서 입력</td>
                        </tr>
                        <tr>
                            <td>마케팅 수신 (선택)</td>
                            <td>마케팅 정보 수신 동의 여부</td>
                            <td>회원가입 또는 설정 화면에서 선택</td>
                        </tr>
                        <tr>
                            <td>배송지 (필수·해당 시)</td>
                            <td>배송지명, 수령인 이름, 연락처, 우편번호, 주소(도로명·지번), 상세주소, 배송 요청사항</td>
                            <td>배송지 등록·수정 시 입력</td>
                        </tr>
                        <tr>
                            <td>거래·경매 (필수·해당 시)</td>
                            <td>배송지 정보, 입금자명, 결제·정산·환불 내역, 송장번호, 택배사, 거래 채팅 메시지</td>
                            <td>안전결제·경매·배송 처리·채팅 이용 시 입력·자동 기록</td>
                        </tr>
                        <tr>
                            <td>정산·출금 (필수·해당 시)</td>
                            <td>예금주, 은행명, 계좌번호</td>
                            <td>정산계좌 등록·판매금 출금 신청 시 입력</td>
                        </tr>
                        <tr>
                            <td>고객문의 (필수·해당 시)</td>
                            <td>이름, 이메일, 문의 제목·내용, 첨부파일, 접수 IP</td>
                            <td>1:1 문의·제휴문의 시 입력 (비회원 문의 포함)</td>
                        </tr>
                        <tr>
                            <td>알림 (선택)</td>
                            <td>브라우저 푸시 구독 정보(endpoint, 인증키), 기기 User-Agent</td>
                            <td>알림 설정에서 푸시 알림 허용 시 자동 수집</td>
                        </tr>
                        <tr>
                            <td>로그인·보안 (자동)</td>
                            <td>접속 IP, User-Agent, 로그인 성공·실패 기록, 마지막 로그인 일시</td>
                            <td>로그인 및 서비스 이용 과정에서 자동 수집</td>
                        </tr>
                        <tr>
                            <td>서비스 이용 (자동)</td>
                            <td>IP 주소, 쿠키, 접속 일시, 기기·브라우저 정보, 검색·이용 기록</td>
                            <td>서비스 이용 과정에서 자동 수집</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h2>2. 개인정보의 이용 목적</h2>
            <ul class="policy-list">
                <li>회원 식별·가입 의사 확인, 본인확인, 회원관리, 만 14세 이상 가입 여부 확인</li>
                <li>거래게시판·경매·안전결제·배송·정산·출금 등 서비스 제공 및 계약 이행</li>
                <li>거래 메시지·푸시 알림 등 회원 간 소통 및 알림 발송</li>
                <li>쿠폰·포인트·출석체크 등 회원 혜택 제공</li>
                <li>부정이용 방지, 사기 예방, 분쟁 조정, 민원·문의 처리</li>
                <li>공지사항 전달, 서비스 개선, 이용 통계·품질 향상</li>
                <li>마케팅·이벤트·광고성 정보 안내(별도 동의한 회원에 한함)</li>
            </ul>

            <h2>3. 개인정보의 보관 및 이용 기간</h2>
            <p>회사는 원칙적으로 개인정보 수집·이용 목적이 달성되면 지체 없이 파기합니다. 다만 관계 법령에 따라 아래 정보를 일정 기간 보관할 수 있습니다.</p>
            <ul class="policy-list">
                <li>계약 또는 청약철회 등에 관한 기록: 5년 (전자상거래법)</li>
                <li>대금결제 및 재화 등의 공급에 관한 기록: 5년 (전자상거래법)</li>
                <li>소비자의 불만 또는 분쟁처리에 관한 기록: 3년 (전자상거래법)</li>
                <li>표시·광고에 관한 기록: 6개월 (전자상거래법)</li>
                <li>로그인 기록: 3개월 (통신비밀보호법)</li>
            </ul>
            <p>회원 탈퇴 시에도 위 법령에 따른 보관 의무가 있는 정보, 분쟁 처리·부정이용 방지를 위해 필요한 최소한의 정보는 해당 기간 동안 별도 보관 후 파기합니다. 마케팅 수신 동의 철회 시에는 즉시 광고성 정보 발송을 중단합니다.</p>

            <h2>4. 개인정보의 제3자 제공</h2>
            <p>회사는 원칙적으로 이용자의 개인정보를 외부에 제공하지 않습니다. 다만 아래의 경우에는 예외로 합니다.</p>
            <ul class="policy-list">
                <li>이용자가 사전에 동의한 경우</li>
                <li>법령에 근거하거나 수사기관의 적법한 절차에 따른 요청이 있는 경우</li>
                <li>배송·결제·본인확인 등 서비스 제공을 위해 필요한 범위에서 업무 위탁이 이루어지는 경우</li>
            </ul>

            <h2>5. 개인정보 처리 위탁</h2>
            <p>회사는 원활한 서비스 제공을 위해 다음과 같이 개인정보 처리 업무를 위탁할 수 있습니다.</p>
            <div class="policy-table-wrap">
                <table class="policy-table">
                    <thead>
                        <tr><th>수탁 업무</th><th>수탁자</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>클라우드·파일 저장(거래·경매·커뮤니티·문의 첨부 등)</td><td>Amazon Web Services (서울 리전 등)</td></tr>
                        <tr><td>휴대폰 인증 SMS 발송</td><td>DirectSend 등 문자 발송 대행사</td></tr>
                        <tr><td>배송 조회</td><td>스마트택배(SweetTracker) 등 배송조회 API 제공사</td></tr>
                        <tr><td>브라우저 푸시 알림 발송</td><td>Google·Mozilla 등 푸시 서비스 제공사</td></tr>
                    </tbody>
                </table>
            </div>
            <p>회사는 위탁계약 등을 통해 개인정보가 안전하게 관리되도록 필요한 사항을 규정하며, 위탁 업무 내용이나 수탁자가 변경되는 경우 본 방침을 통해 고지합니다.</p>

            <h2>6. 개인정보의 국외 이전</h2>
            <p>회사는 원칙적으로 이용자의 개인정보를 국외에 보관·처리하지 않습니다. 다만 클라우드·푸시 알림 등 위탁 서비스 이용 과정에서 수탁자의 해외 서버를 거칠 수 있으며, 이 경우 관련 법령이 요구하는 절차를 준수합니다.</p>

            <h2>7. 개인정보의 파기절차 및 방법</h2>
            <ol>
                <li><strong>파기절차</strong>: 보유 기간이 경과하거나 처리 목적이 달성된 개인정보는 내부 방침 및 관련 법령에 따라 파기합니다.</li>
                <li><strong>파기방법</strong>: 전자적 파일은 복구·재생이 불가능한 방법으로 삭제하고, 종이 문서는 분쇄 또는 소각합니다.</li>
            </ol>

            <h2>8. 이용자의 권리와 행사방법</h2>
            <p>이용자는 언제든지 자신의 개인정보에 대해 열람·정정·삭제·처리정지를 요청할 수 있으며, 회원탈퇴를 통해 이용계약 해지를 요청할 수 있습니다. 마이페이지<?php if ($__privacy_email !== ''): ?>, <a href="mailto:<?php echo htmlspecialchars($__privacy_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($__privacy_email, ENT_QUOTES, 'UTF-8'); ?></a><?php endif; ?>, 또는 <a href="/page/inquiry.php">1:1 문의</a>를 통해 행사할 수 있습니다.</p>
            <p>마케팅 수신 동의는 회원가입 이후에도 철회할 수 있으며, 철회 이전에 발송된 안내의 효력에는 영향을 주지 않습니다.</p>

            <h2>9. 쿠키의 운영 및 거부</h2>
            <p>회사는 로그인 유지, 서비스 이용 편의 등을 위해 쿠키를 사용할 수 있습니다. 이용자는 브라우저 설정을 통해 쿠키 저장을 거부할 수 있으나, 일부 서비스 이용에 제한이 있을 수 있습니다.</p>

            <h2>10. 개인정보의 안전성 확보 조치</h2>
            <ul class="policy-list">
                <li>개인정보 접근 권한 관리 및 접근 통제</li>
                <li>비밀번호 등 중요 정보의 암호화 저장</li>
                <li>보안 프로그램 설치 및 주기적 점검</li>
                <li>개인정보 처리 직원에 대한 최소화 교육</li>
            </ul>

            <h2>11. 만 14세 미만 아동의 개인정보</h2>
            <p>회사는 만 14세 미만 아동의 회원가입을 허용하지 않으며, 만 14세 미만 아동의 개인정보를 고의로 수집하지 않습니다. 해당 사실이 확인되면 지체 없이 삭제 등 필요한 조치를 합니다.</p>

            <h2>12. 개인정보 보호책임자</h2>
            <div class="policy-contact">
                <p><strong>개인정보 보호책임자</strong></p>
                <ul class="policy-list">
                    <li>성명: <?php echo htmlspecialchars($__ceo, ENT_QUOTES, 'UTF-8'); ?></li>
                    <li>직책: 대표</li>
                    <?php if ($__privacy_email !== ''): ?>
                        <li>이메일: <a href="mailto:<?php echo htmlspecialchars($__privacy_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($__privacy_email, ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endif; ?>
                    <li>문의: <a href="/page/inquiry.php">1:1 문의</a></li>
                </ul>
                <p class="policy-note">개인정보 보호 관련 문의·불만·피해구제는 위 채널 또는 서비스 내 1:1 문의를 이용해 주세요.</p>
            </div>

            <h2>13. 개인정보처리방침의 변경</h2>
            <p>본 방침이 변경되는 경우 변경 내용과 시행일을 서비스 공지사항 등을 통해 최소 7일 전(이용자에게 불리한 변경은 30일 전)에 고지합니다.</p>

            <div class="policy-supplement">
                <h3>부칙</h3>
                <p>이 방침은 <?php echo htmlspecialchars($__effective, ENT_QUOTES, 'UTF-8'); ?>부터 시행합니다.</p>
                <p>2025년 1월 1일 시행되었던 종전 방침은 본 방침으로 대체됩니다.</p>
            </div>
        </article>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
