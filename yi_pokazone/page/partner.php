<?php
require_once __DIR__ . '/../lib/_function.php';

$page  = 'partner';
$title = '제휴문의';
$meta_description = 'Pokazone 광고·제휴·입점·미디어 문의. 귀사의 제안을 담당자에게 바로 전달해 드립니다.';
$meta_keywords    = 'Pokazone 제휴, 광고문의, 입점문의, 파트너십, 포켓몬 카드 플랫폼 제휴';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '제휴문의', 'url' => '/page/partner.php'],
];

$types = [
    'general' => '일반 제휴',
    'ad'      => '광고/마케팅',
    'shop'    => '쇼핑몰 입점',
    'creator' => '크리에이터/인플루언서',
    'media'   => '언론/미디어',
];
include __DIR__ . '/../include/header.php';
?>

<section class="policy-section">
    <div class="container">
        <header class="policy-head">
            <span class="policy-eyebrow">PARTNERSHIP</span>
            <h1>Pokazone과 함께 성장할 파트너를 찾습니다</h1>
            <p class="policy-lead">광고·입점·공동 프로모션·콘텐츠 제휴 등 다양한 협업 기회를 열어두고 있습니다. 아래 폼으로 제안 주시면 담당자가 영업일 기준 3일 이내에 회신드립니다.</p>
        </header>

        <div class="policy-grid policy-grid-3">
            <article class="policy-card">
                <div class="policy-icon">📢</div>
                <h2>광고/프로모션</h2>
                <p>홈 배너·뉴스레터·카테고리 전용 광고 패키지. 포켓몬 TCG 타깃 정확도 1위.</p>
            </article>
            <article class="policy-card">
                <div class="policy-icon">🏬</div>
                <h2>쇼핑몰 입점</h2>
                <p>전문 판매자 등록 및 공식 스토어 개설. 안전결제와 정산 시스템 지원.</p>
            </article>
            <article class="policy-card">
                <div class="policy-icon">🎥</div>
                <h2>크리에이터 협업</h2>
                <p>개봉·리뷰·컬렉션 크리에이터와의 콘텐츠 공동 기획, 이벤트 스폰서십.</p>
            </article>
        </div>

        <div class="partner-form-wrap">
            <form class="post-form" method="post" action="/proc/partner_proc.php" autocomplete="off">
                <h2 class="policy-h2">제휴 제안 보내기</h2>

                <div class="field-row">
                    <div class="field">
                        <label for="pt_type">제휴 유형</label>
                        <select id="pt_type" name="pt_type" required>
                            <?php foreach ($types as $k => $label): ?>
                                <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="pt_company">회사/단체명</label>
                        <input type="text" id="pt_company" name="pt_company" required maxlength="100"
                               placeholder="예) 주식회사 포카존">
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="pt_manager">담당자명</label>
                        <input type="text" id="pt_manager" name="pt_manager" required maxlength="30"
                               placeholder="예) 홍길동">
                    </div>
                    <div class="field">
                        <label for="pt_phone">연락처</label>
                        <input type="tel" id="pt_phone" name="pt_phone" required maxlength="30"
                               placeholder="예) 010-0000-0000">
                    </div>
                </div>

                <div class="field">
                    <label for="pt_email">이메일</label>
                    <input type="email" id="pt_email" name="pt_email" required maxlength="100"
                           placeholder="회신 받을 이메일을 입력해 주세요">
                </div>

                <div class="field">
                    <label for="pt_title">제안 제목</label>
                    <input type="text" id="pt_title" name="pt_title" required maxlength="200"
                           placeholder="예) 포켓몬 신규 세트 출시 기념 공동 프로모션 제안">
                </div>

                <div class="field">
                    <label for="pt_content">제안 내용</label>
                    <textarea id="pt_content" name="pt_content" rows="10" required
                              placeholder="제휴 목적, 기대 효과, 예상 일정, 예산 규모 등을 자유롭게 적어주세요."></textarea>
                </div>

                <div class="field">
                    <label class="checkbox-label">
                        <input type="checkbox" name="agree" value="1" required>
                        <span>개인정보 수집 및 이용에 동의합니다. (이름·연락처·이메일은 제휴문의 회신 목적으로 수집되며 문의 처리 완료 후 1년간 보관됩니다.)</span>
                    </label>
                </div>

                <div class="form-actions">
                    <a href="/" class="btn btn-outline">취소</a>
                    <button type="submit" class="btn btn-primary">제안 보내기</button>
                </div>
            </form>

            <aside class="policy-contact policy-contact-card">
                <h3>직접 연락하고 싶으세요?</h3>
                <ul class="policy-list">
                    <?php
                    $pt_email = site_legal_contact_email();
                    $pt_phone = site_legal_contact_phone();
                    $pt_hours = site_legal_business_hours();
                    $pt_addr  = site_legal_address();
                    ?>
                    <?php if ($pt_email !== ''): ?>
                        <li>📧 <a href="mailto:<?php echo htmlspecialchars($pt_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($pt_email, ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endif; ?>
                    <?php if ($pt_phone !== ''): ?>
                        <li>📞 <?php echo htmlspecialchars($pt_phone, ENT_QUOTES, 'UTF-8'); ?><?php if ($pt_hours !== ''): ?> (<?php echo htmlspecialchars($pt_hours, ENT_QUOTES, 'UTF-8'); ?>)<?php endif; ?></li>
                    <?php elseif ($pt_hours !== ''): ?>
                        <li>🕐 <?php echo htmlspecialchars($pt_hours, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endif; ?>
                    <?php if ($pt_addr !== ''): ?>
                        <li>🏢 <?php echo htmlspecialchars($pt_addr, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endif; ?>
                    <li>💬 <a href="/page/inquiry.php">1:1 문의</a></li>
                </ul>
            </aside>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
